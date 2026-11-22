<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos;

use DateTimeImmutable;
use DateTimeZone;

/**
 * A day as the API takes and answers it: YYYY-MM-DD, a calendar day with no time. It is read
 * in UTC, so stepping a day at a time never meets a clock change (Chile's falls at midnight).
 */
final class Day {
	/**
	 * The typed day, or $refusal when it is not a real YYYY-MM-DD day.
	 *
	 * @throws Refused
	 */
	public static function of(string $typed, string $refusal): DateTimeImmutable {
		$day = DateTimeImmutable::createFromFormat('!Y-m-d', $typed, new DateTimeZone('UTC'));
		if ($day === false || $day->format('Y-m-d') !== $typed) {
			throw new Refused($refusal);
		}
		return $day;
	}
}
