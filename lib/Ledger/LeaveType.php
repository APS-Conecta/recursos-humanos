<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Ledger;

use OCA\RecursosHumanos\WorkingDays\Unit;

/** The Legal leave types that keep a Balance (CONTEXT.md); later tickets add the others. */
enum LeaveType: string {
	case AnnualLeave = 'annualLeave';
	case AdministrativeDay = 'administrativeDay';
	case Training = 'training';

	/** How its days count: Training too in días hábiles, until the law says (recursos-humanos#1, Limits). */
	public function unit(): Unit {
		return Unit::Habiles;
	}
}
