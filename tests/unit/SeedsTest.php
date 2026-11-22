<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * The data the app ships parses (recursos-humanos#1, Testing Decisions): a seed file is edited by
 * hand for each release, and a day it gets wrong would be skipped or counted without a word.
 */
class SeedsTest extends TestCase {
	private const SEEDS = __DIR__ . '/../../seeds';
	private const FIELDS = ['rule', 'version', 'title', 'summary', 'value', 'basis', 'from', 'establishmentRule'];
	private const UNITS = ['habiles', 'corridos'];

	public function testEachHolidayListNamesRealDaysOfItsOwnYearInOrder(): void {
		$files = glob(self::SEEDS . '/holidays-*.json') ?: [];
		self::assertNotEmpty($files, 'the app ships the national Public holiday list');
		foreach ($files as $file) {
			$name = basename($file);
			self::assertMatchesRegularExpression('/^holidays-\d{4}\.json$/', $name, 'one file per year: holidays-<year>.json');
			$year = substr($name, 9, 4);
			$list = json_decode((string)file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
			self::assertIsList($list, "$name is a list of {date, name}");
			$dates = [];
			foreach ($list as $holiday) {
				self::assertSame(['date', 'name'], array_keys($holiday), "$name: each entry is {date, name}");
				$day = DateTimeImmutable::createFromFormat('!Y-m-d', $holiday['date']);
				self::assertTrue($day !== false && $day->format('Y-m-d') === $holiday['date'], "$name: {$holiday['date']} is not a real day");
				self::assertStringStartsWith("$year-", $holiday['date'], "$name: {$holiday['date']} is another year's");
				self::assertNotSame('', trim($holiday['name']), "$name: {$holiday['date']} has no name");
				$dates[] = $holiday['date'];
			}
			$sorted = array_values(array_unique($dates));
			sort($sorted);
			self::assertSame($sorted, $dates, "$name: each day once, in date order");
		}
	}

	/** ADR 0002: each version is dated and numbered in order, and its value has the shape the app reads. */
	public function testEachRuleVersionIsDatedNumberedAndShapedForTheApp(): void {
		$shapes = [
			// Steps from 0 years, in rising order, each a whole number of days.
			'annualLeaveDays' => static function (array $value): bool {
				$steps = $value['steps'] ?? null;
				if (!is_array($steps) || !array_is_list($steps) || $steps === []) {
					return false;
				}
				$years = -1;
				foreach ($steps as $step) {
					if (!is_int($step['years'] ?? null) || !is_int($step['days'] ?? null) || $step['days'] <= 0
						|| $step['years'] <= $years || ($years === -1 && $step['years'] !== 0)) {
						return false;
					}
					$years = $step['years'];
				}
				return in_array($value['unit'] ?? null, self::UNITS, true);
			},
			'annualLeaveEligibility' => static fn (array $value): bool => is_int($value['months'] ?? null) && $value['months'] >= 0,
			'administrativeDays' => static fn (array $value): bool => is_int($value['days'] ?? null) && $value['days'] >= 0
				&& in_array($value['unit'] ?? null, self::UNITS, true),
			'administrativeDaysMidYear' => static fn (array $value): bool => array_key_exists('days', $value) && ($value['days'] === null || is_int($value['days'])),
			'trainingDays' => static fn (array $value): bool => is_int($value['days'] ?? null) && $value['days'] >= 0
				&& in_array($value['unit'] ?? null, self::UNITS, true),
		];
		$versions = json_decode((string)file_get_contents(self::SEEDS . '/rule-versions.json'), true, flags: JSON_THROW_ON_ERROR);
		self::assertIsList($versions, 'rule-versions.json is a list of Rule versions');

		$last = [];
		foreach ($versions as $version) {
			self::assertSame(self::FIELDS, array_keys($version), 'each Rule version has exactly ' . implode(', ', self::FIELDS));
			$rule = $version['rule'];
			$at = "$rule v{$version['version']}";
			self::assertArrayHasKey($rule, $shapes, "$at: the app reads no rule by that name");
			self::assertTrue($shapes[$rule]($version['value']), "$at: its value is not the shape the app reads");
			$day = DateTimeImmutable::createFromFormat('!Y-m-d', $version['from']);
			self::assertTrue($day !== false && $day->format('Y-m-d') === $version['from'], "$at: «from» is not a real day");
			foreach (['title', 'summary', 'basis'] as $words) {
				self::assertNotSame('', trim($version[$words]), "$at: no $words");
			}
			self::assertIsBool($version['establishmentRule'], "$at: establishmentRule is true or false");
			[$number, $from] = $last[$rule] ?? [0, ''];
			self::assertSame($number + 1, $version['version'], "$at: a rule's versions count 1, 2, 3… in the file");
			self::assertGreaterThan($from, $version['from'], "$at: a later version starts later");
			$last[$rule] = [$version['version'], $version['from']];
		}
		self::assertSame(array_keys($shapes), array_values(array_intersect(array_keys($shapes), array_keys($last))), 'every rule the app reads ships a version');
	}
}
