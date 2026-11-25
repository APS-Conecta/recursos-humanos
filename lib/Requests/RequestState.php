<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Requests;

/** Where a Request is in its lifecycle (recursos-humanos#1, Requests); later tickets add registration, withdrawal and cancellation. */
enum RequestState: string {
	/** Waiting for its decider. */
	case Pending = 'pending';
	/** Approved, waiting for HR to register it; only then does it change a Balance. */
	case Approved = 'approved';
	/** Rejected with a written reason. */
	case Rejected = 'rejected';

	/** Whether its days still come off Available (CONTEXT.md): waiting, or approved but not registered. */
	public function inFlight(): bool {
		return $this === self::Pending || $this === self::Approved;
	}
}
