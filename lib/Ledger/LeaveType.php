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

	/** How a person reads it. */
	public function title(): string {
		return match ($this) {
			self::AnnualLeave => 'Feriado legal',
			self::AdministrativeDay => 'Días administrativos',
			self::Training => 'Capacitación',
		};
	}

	/**
	 * The rules a Request of this type is counted with (ADR 0002): the one that grants it and
	 * says its unit, and any its submission is checked against.
	 *
	 * @return list<string>
	 */
	public function countedWith(): array {
		return match ($this) {
			self::AnnualLeave => [RuleBook::ANNUAL_LEAVE_DAYS, RuleBook::ANNUAL_LEAVE_ELIGIBILITY],
			default => [$this->rule()],
		};
	}

	/** The rule that grants it, whose value also says how its days count. */
	public function rule(): string {
		return match ($this) {
			self::AnnualLeave => RuleBook::ANNUAL_LEAVE_DAYS,
			self::AdministrativeDay => RuleBook::ADMINISTRATIVE_DAYS,
			self::Training => RuleBook::TRAINING_DAYS,
		};
	}
}
