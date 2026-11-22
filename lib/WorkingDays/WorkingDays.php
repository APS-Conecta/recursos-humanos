<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\WorkingDays;

use OCA\RecursosHumanos\Day;
use OCA\RecursosHumanos\Refused;
use OCA\RecursosHumanos\ResponseDefinitions;
use OCP\AppFramework\Db\DoesNotExistException;

/**
 * WorkingDays (recursos-humanos#1, Modules): the Public holidays, and the count of a date range
 * in días hábiles or días corridos.
 *
 * - **The national list ships with the app**, one seed file per year (`seeds/holidays-<year>.json`,
 *   as Estadística ships its Metas); a later year arrives as another file in an update. The
 *   regional and local days, and the online refresh, are recursos-humanos#17's.
 * - **A range in días hábiles is counted only within years the app has the list for**: without
 *   the year's holidays, the count would be wrong and nothing would say so. Días corridos need
 *   no list.
 * - **A count writes nothing**: it is the preview «Nueva solicitud» shows as the dates change.
 * - **A skipped day keeps its reason**: a Saturday or Sunday is skipped as the weekend, with the
 *   holiday's name when it is one too; a weekday holiday is skipped as the holiday.
 *
 * @psalm-import-type RecursosHumanosCount from ResponseDefinitions
 * @psalm-import-type RecursosHumanosHoliday from ResponseDefinitions
 */
class WorkingDays {
	private const SEEDS = __DIR__ . '/../../seeds';
	private const WEEKEND = 'weekend';
	private const HOLIDAY = 'holiday';

	private const FROM = 'Elige la fecha de inicio.';
	private const TO = 'Elige la fecha de término.';
	private const BACKWARDS = 'La fecha de término es anterior a la de inicio.';
	private const UNIT = 'Elige cómo se cuentan los días: hábiles o corridos.';
	private const NO_LIST = 'Todavía no está la lista de días feriados de %d.';

	/** @var array<int, array<string, string>|null> each year read so far: its holidays' names by date, or null for no list */
	private array $lists = [];

	/**
	 * The year's Public holidays, by date.
	 *
	 * @return list<RecursosHumanosHoliday>
	 * @throws DoesNotExistException the app has no list for that year
	 */
	public function holidays(int $year): array {
		$list = $this->listOf($year) ?? throw new DoesNotExistException(sprintf(self::NO_LIST, $year));
		return array_map(static fn (string $date, string $name): array => ['date' => $date, 'name' => $name], array_keys($list), $list);
	}

	/**
	 * The days $from to $to count, both included, and the days they skip, in total and per year.
	 *
	 * @return RecursosHumanosCount
	 * @throws Refused
	 */
	public function count(string $from, string $to, string $unit): array {
		$first = Day::of($from, self::FROM);
		$last = Day::of($to, self::TO);
		$unit = Unit::tryFrom($unit) ?? throw new Refused(self::UNIT);
		if ($last < $first) {
			throw new Refused(self::BACKWARDS);
		}
		$years = [];
		for ($year = (int)$first->format('Y'); $year <= (int)$last->format('Y'); $year++) {
			if ($unit === Unit::Habiles && $this->listOf($year) === null) {
				throw new Refused(sprintf(self::NO_LIST, $year));
			}
			$years[$year] = 0.0;
		}

		$counted = [];
		$skipped = [];
		for ($day = $first; $day <= $last; $day = $day->modify('+1 day')) {
			$date = $day->format('Y-m-d');
			$year = (int)$day->format('Y');
			if ($unit === Unit::Habiles) {
				$holiday = $this->listOf($year)[$date] ?? null;
				$weekend = (int)$day->format('N') >= 6;
				if ($weekend || $holiday !== null) {
					$skipped[] = ['date' => $date, 'reason' => $weekend ? self::WEEKEND : self::HOLIDAY, 'name' => $holiday];
					continue;
				}
			}
			$counted[] = $date;
			$years[$year]++;
		}
		return [
			'days' => (float)count($counted),
			'counted' => $counted,
			'skipped' => $skipped,
			'years' => array_map(static fn (int $year, float $days): array => ['year' => $year, 'days' => $days], array_keys($years), $years),
		];
	}

	/** @return array<string, string>|null the year's holidays' names by date, or null when the app ships no list for it */
	private function listOf(int $year): ?array {
		if (!array_key_exists($year, $this->lists)) {
			$file = sprintf('%s/holidays-%d.json', self::SEEDS, $year);
			$this->lists[$year] = is_file($file)
				? array_column(json_decode((string)file_get_contents($file), true, flags: JSON_THROW_ON_ERROR), 'name', 'date')
				: null;
		}
		return $this->lists[$year];
	}
}
