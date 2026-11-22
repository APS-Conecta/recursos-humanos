<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\People;

/**
 * Years of service (CONTEXT.md): the months from a person's start date, plus their prior public
 * service. A month is reached on the same day of a later month, or on that month's last day when
 * it is shorter (31 January + 1 month = 28 February).
 */
final class Service {
	/** The whole months of service on $day; before the start date, only the prior service. */
	public static function months(string $start, int $prior, string $day): int {
		if ($day < $start) {
			return $prior;
		}
		[$startYear, $startMonth] = array_map('intval', explode('-', $start));
		[$year, $month] = array_map('intval', explode('-', $day));
		$months = ($year - $startYear) * 12 + $month - $startMonth;
		if (self::plus($start, $months) > $day) {
			$months--;
		}
		return $months + $prior;
	}

	/** The day service reaches $months. */
	public static function reached(string $start, int $prior, int $months): string {
		return self::plus($start, $months - $prior);
	}

	/** 179 → «14 años y 11 meses», as the screens and the Movements write it. */
	public static function text(int $months): string {
		$years = intdiv($months, 12);
		$rest = $months % 12;
		$parts = [];
		if ($years > 0 || $rest === 0) {
			$parts[] = $years . ($years === 1 ? ' año' : ' años');
		}
		if ($rest > 0) {
			$parts[] = $rest . ($rest === 1 ? ' mes' : ' meses');
		}
		return implode(' y ', $parts);
	}

	/** $day moved by $months whole months, the day of the month kept or cut to the month's last. */
	private static function plus(string $day, int $months): string {
		[$year, $month, $date] = array_map('intval', explode('-', $day));
		$index = $year * 12 + $month - 1 + $months;
		$year = intdiv($index, 12);
		$month = $index % 12 + 1;
		$last = (int)date('t', gmmktime(0, 0, 0, $month, 1, $year));
		return sprintf('%04d-%02d-%02d', $year, $month, min($date, $last));
	}
}
