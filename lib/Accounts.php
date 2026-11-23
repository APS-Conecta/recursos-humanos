<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos;

use OCP\IUserManager;

/**
 * An account as the API names one: its uid and the name a person reads, the uid itself once the
 * account is gone (a record outlives its account).
 *
 * @psalm-import-type RecursosHumanosAccount from ResponseDefinitions
 */
class Accounts {
	public function __construct(
		private IUserManager $users,
	) {
	}

	/** @return RecursosHumanosAccount */
	public function view(string $uid): array {
		return ['uid' => $uid, 'name' => $this->name($uid)];
	}

	public function name(string $uid): string {
		return $this->users->getDisplayName($uid) ?? $uid;
	}
}
