<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos;

use OCA\RecursosHumanos\WorkingDays\Unit;

/**
 * How the app writes numbers, days and ranges for a person to read, as the screens write them
 * (src/days.js): «3,5», «2 de marzo de 2026», «del 2 al 6 de marzo de 2026», «5 días hábiles».
 */
final class Spanish {
	private const MONTHS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

	/** 15.0 → «15», 3.5 → «3,5». */
	public static function number(float $days): string {
		return str_replace('.', ',', (string)round($days, 1));
	}

	/** 2026-03-02 → «2 de marzo de 2026». */
	public static function day(string $day): string {
		return sprintf('%d de %s de %s', (int)substr($day, 8, 2), self::MONTHS[(int)substr($day, 5, 2) - 1], substr($day, 0, 4));
	}

	/** «el 2 de marzo de 2026», «del 2 al 6 de marzo de 2026», «del 30 de marzo al 3 de abril de 2026». */
	public static function range(string $from, string $to): string {
		if ($from === $to) {
			return 'el ' . self::day($from);
		}
		$prefix = match (true) {
			substr($from, 0, 7) === substr($to, 0, 7) => (string)(int)substr($from, 8, 2),
			substr($from, 0, 4) === substr($to, 0, 4) => substr(self::day($from), 0, -8),
			default => self::day($from),
		};
		return sprintf('del %s al %s', $prefix, self::day($to));
	}

	/** 5 → «5 días hábiles», 1 → «1 día corrido». */
	public static function days(float $days, Unit $unit): string {
		return self::number($days) . ' ' . ($days === 1.0 ? match ($unit) {
			Unit::Habiles => 'día hábil',
			Unit::Corridos => 'día corrido',
		} : self::unit($unit));
	}

	/** How a unit's days are called: «días hábiles», «días corridos». */
	public static function unit(Unit $unit): string {
		return match ($unit) {
			Unit::Habiles => 'días hábiles',
			Unit::Corridos => 'días corridos',
		};
	}
}
