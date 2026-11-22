<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\People;

use OCA\RecursosHumanos\Db\RegimeLine;

/** A person's Regime (CONTEXT.md): under the Estatuto APS, or on honorarios without Annual leave or Administrative days. */
enum Regime: string {
	case EstatutoAps = 'estatutoAps';
	case Honorarios = 'honorarios';

	/**
	 * The Regime in force on $day: the latest line starting on or before it; null before the first.
	 *
	 * @param list<RegimeLine> $lines oldest first
	 */
	public static function inForce(array $lines, string $day): ?self {
		$regime = null;
		foreach ($lines as $line) {
			if ($line->getStartsOn() <= $day) {
				$regime = self::from($line->getRegime());
			}
		}
		return $regime;
	}
}
