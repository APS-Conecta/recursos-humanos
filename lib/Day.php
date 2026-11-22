<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos;

use DateTimeImmutable;
use DateTimeZone;
use OCP\AppFramework\Utility\ITimeFactory;

/**
 * A day as the API takes and answers it: YYYY-MM-DD, a calendar day with no time. It is read
 * in UTC, so stepping a day at a time never meets a clock change (Chile's falls at midnight).
 */
final class Day {
	/** The establishment's day, as the Year close reads it (recursos-humanos#1, Limits). */
	private const ZONE = 'America/Santiago';

	/** Today in the establishment, YYYY-MM-DD. */
	public static function today(ITimeFactory $time): string {
		return $time->getDateTime('now', new DateTimeZone(self::ZONE))->format('Y-m-d');
	}

	/** The day after a YYYY-MM-DD day, or $count days later. */
	public static function after(string $day, int $count = 1): string {
		return (new DateTimeImmutable($day, new DateTimeZone('UTC')))->modify("$count day")->format('Y-m-d');
	}

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
