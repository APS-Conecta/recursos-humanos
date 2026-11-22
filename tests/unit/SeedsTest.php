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
}
