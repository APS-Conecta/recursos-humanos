<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Ledger;

use OCA\RecursosHumanos\RuleBook\RuleBook;

/** The Legal leave types that keep a Balance (CONTEXT.md); later tickets add the others. */
enum LeaveType: string {
	case AnnualLeave = 'annualLeave';
	case AdministrativeDay = 'administrativeDay';
	case Training = 'training';

	/** The rule that grants it, whose value also says how its days count. */
	public function rule(): string {
		return match ($this) {
			self::AnnualLeave => RuleBook::ANNUAL_LEAVE_DAYS,
			self::AdministrativeDay => RuleBook::ADMINISTRATIVE_DAYS,
			self::Training => RuleBook::TRAINING_DAYS,
		};
	}
}
