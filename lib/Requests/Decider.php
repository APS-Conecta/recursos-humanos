<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Requests;

/** Who decides a person's Requests, and in what capacity: the accounts any of which may decide. */
final class Decider {
	/** @param list<string> $uids none for the Director's own Requests, or when the establishment has no Director */
	public function __construct(
		public readonly Capacity $capacity,
		public readonly array $uids,
	) {
	}

	public function includes(string $uid): bool {
		return in_array($uid, $this->uids, true);
	}
}
