<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Ledger;

use OCA\RecursosHumanos\Day;
use OCA\RecursosHumanos\Db\Movement;
use OCA\RecursosHumanos\Db\Person;
use OCA\RecursosHumanos\Db\PersonMapper;
use OCA\RecursosHumanos\Db\RegimeLine;
use OCA\RecursosHumanos\Db\RegimeLineMapper;
use OCA\RecursosHumanos\Db\RuleVersion;
use OCA\RecursosHumanos\People\Regime;
use OCA\RecursosHumanos\People\Service;
use OCA\RecursosHumanos\Refused;
use OCA\RecursosHumanos\ResponseDefinitions;
use OCA\RecursosHumanos\RuleBook\RuleBook;

/**
 * The yearly grants (recursos-humanos#5): the days a person under the Estatuto APS receives each
 * year, written as Movements on the day they are due, each naming its Rule version.
 *
 * - **Annual leave**, by Years of service, from the day service passes the first year, then each
 *   1 January; it rises on the day service reaches a step.
 * - **Administrative days** each 1 January. A mid-year joiner gets what the establishment rule
 *   says; while it names no number, HR sets it case by case, and the record is «Falta completar».
 * - **Training**, from 1 January or the start date.
 * - **On honorarios, nothing**: a grant needs the Estatuto APS in force on its day.
 * - **Written once**: completing a record, the hourly job and occ all ask, and the Ledger's
 *   once-keys keep one of each. A grant ahead of the day asked about waits for its day.
 *
 * @psalm-import-type RecursosHumanosDays from ResponseDefinitions
 */
class Grants {
	/** What HR still has to set before a record is complete. */
	public const ADMINISTRATIVE_DAYS = 'administrativeDays';

	private const ALREADY_SET = 'Los días administrativos de %d ya están fijados.';
	private const ONLY_MID_YEAR = 'Solo se fijan a mano los días administrativos de quien ingresó a mitad de año.';
	private const RANGE = 'Los días administrativos van de 0 a %s, en días enteros o medios días.';

	public function __construct(
		private Ledger $ledger,
		private RuleBook $rules,
		private PersonMapper $people,
		private RegimeLineMapper $regimes,
	) {
	}

	/**
	 * Writes the grants of $day's year that are due by $day and the person lacks.
	 *
	 * @param list<RegimeLine> $lines the person's, oldest first
	 * @return int how many were written
	 */
	public function due(Person $person, array $lines, string $day): int {
		$written = 0;
		foreach ($this->owed($person, (int)substr($day, 0, 4)) as $grant) {
			if ($grant->getEffectiveOn() > $day || $grant->getQuantity() <= 0
				|| Regime::inForce($lines, $grant->getEffectiveOn()) !== Regime::EstatutoAps) {
				continue;
			}
			if ($this->ledger->writeOnce($grant, self::onceKey($grant))) {
				$written++;
			}
		}
		return $written;
	}

	/** @return int how many grants due by $day were written, for everyone with a record */
	public function dueForEveryone(string $day): int {
		$lines = [];
		foreach ($this->regimes->all() as $line) {
			$lines[$line->getUid()][] = $line;
		}
		$written = 0;
		foreach ($this->people->all() as $person) {
			$written += $this->due($person, $lines[$person->getUid()] ?? [], $day);
		}
		return $written;
	}

	/**
	 * What HR still has to set in the person's record: a mid-year joiner's Administrative days.
	 *
	 * @param list<RegimeLine> $lines oldest first
	 * @return list<'administrativeDays'>
	 */
	public function missing(Person $person, array $lines, string $today): array {
		$year = (int)substr($today, 0, 4);
		$start = $person->getStartDate();
		$joiner = $start > "$year-01-01" && $start <= "$year-12-31";
		return $joiner && $this->midYearDays($person) === null
			&& Regime::inForce($lines, max($start, $today)) === Regime::EstatutoAps
			&& !$this->ledger->holds($person->getUid(), self::key(LeaveType::AdministrativeDay, $year))
			? [self::ADMINISTRATIVE_DAYS] : [];
	}

	/**
	 * HR sets a mid-year joiner's Administrative days for this year: from 0 to the year's days, in
	 * whole or half days, once.
	 *
	 * @param list<RegimeLine> $lines oldest first
	 * @throws Refused not a joiner whose days HR sets, already set, or out of range
	 */
	public function setAdministrativeDays(Person $person, array $lines, string $actor, float $days, string $today): void {
		$year = (int)substr($today, 0, 4);
		if ($this->ledger->holds($person->getUid(), self::key(LeaveType::AdministrativeDay, $year))) {
			throw new Refused(sprintf(self::ALREADY_SET, $year));
		}
		if ($this->missing($person, $lines, $today) === []) {
			throw new Refused(self::ONLY_MID_YEAR);
		}
		$most = (float)$this->rules->value($this->rules->at(RuleBook::ADMINISTRATIVE_DAYS, $today))['days'];
		if ($days < 0 || $days > $most || fmod($days * 2, 1.0) !== 0.0) {
			throw new Refused(sprintf(self::RANGE, self::number($most)));
		}
		$grant = $this->movement($person, LeaveType::AdministrativeDay, MovementKind::Grant, max($person->getStartDate(), $today), $days,
			$this->rules->at(RuleBook::ADMINISTRATIVE_DAYS_MID_YEAR, $person->getStartDate()),
			sprintf('Días administrativos %d fijados por Recursos Humanos: ingresó a mitad de año.', $year));
		$grant->setActor($actor);
		if (!$this->ledger->writeOnce($grant, self::onceKey($grant))) {
			throw new Refused(sprintf(self::ALREADY_SET, $year));
		}
	}

	/**
	 * «Mis días»: this year's Balances, Years of service, when Annual leave starts and its next rise.
	 *
	 * @param list<RegimeLine> $lines oldest first
	 * @param list<Movement> $movements the person's
	 * @return RecursosHumanosDays
	 */
	public function summary(Person $person, array $lines, array $movements, string $today): array {
		$start = $person->getStartDate();
		$prior = $person->getPriorMonths();
		$annualLeaveFrom = $this->annualLeaveFrom($person);
		$nextRise = null;
		foreach ($this->rules->value($this->rules->at(RuleBook::ANNUAL_LEAVE_DAYS, $today))['steps'] as $step) {
			$on = Service::reached($start, $prior, $step['years'] * 12);
			if ($step['years'] > 0 && $on > $today) {
				$nextRise = ['on' => $on, 'years' => $step['years'], 'days' => (float)$step['days']];
				break;
			}
		}
		return [
			'year' => (int)substr($today, 0, 4),
			'serviceMonths' => Service::months($start, $prior, $today),
			'annualLeaveFrom' => $annualLeaveFrom > $today ? $annualLeaveFrom : null,
			'nextRise' => $nextRise,
			'balances' => $this->ledger->balances($movements, (int)substr($today, 0, 4)),
			'missing' => $this->missing($person, $lines, $today),
		];
	}

	/** @return list<Movement> every grant the person is owed in $year, whatever its day or Regime */
	private function owed(Person $person, int $year): array {
		$first = "$year-01-01";
		$last = "$year-12-31";
		$start = $person->getStartDate();
		$prior = $person->getPriorMonths();
		if ($start > $last) {
			return [];
		}
		$owed = [];

		$from = max($first, $this->annualLeaveFrom($person));
		if ($from <= $last) {
			$rule = $this->rules->at(RuleBook::ANNUAL_LEAVE_DAYS, $from);
			$months = Service::months($start, $prior, $from);
			$days = $this->annualLeaveDays($rule, $months);
			$owed[] = $this->movement($person, LeaveType::AnnualLeave, MovementKind::Grant, $from, $days, $rule,
				sprintf('Feriado legal %d: %s días hábiles por %s de servicio.', $year, self::number($days), Service::text($months)));
			foreach ($this->rules->value($rule)['steps'] as $step) {
				$on = Service::reached($start, $prior, $step['years'] * 12);
				if ($on <= $from || $on > $last) {
					continue;
				}
				$riseRule = $this->rules->at(RuleBook::ANNUAL_LEAVE_DAYS, $on);
				$after = $this->annualLeaveDays($riseRule, Service::months($start, $prior, $on));
				$before = $this->annualLeaveDays($riseRule, Service::months($start, $prior, Day::after($on, -1)));
				$owed[] = $this->movement($person, LeaveType::AnnualLeave, MovementKind::ServiceRise, $on, $after - $before, $riseRule,
					sprintf('Cumple %d años de servicio: el feriado legal %d sube a %s días hábiles.', $step['years'], $year, self::number($after)));
			}
		}

		if ($start <= $first) {
			$rule = $this->rules->at(RuleBook::ADMINISTRATIVE_DAYS, $first);
			$owed[] = $this->movement($person, LeaveType::AdministrativeDay, MovementKind::Grant, $first, (float)$this->rules->value($rule)['days'], $rule,
				sprintf('Días administrativos %d.', $year));
		} elseif (($days = $this->midYearDays($person)) !== null) {
			$owed[] = $this->movement($person, LeaveType::AdministrativeDay, MovementKind::Grant, $start, $days,
				$this->rules->at(RuleBook::ADMINISTRATIVE_DAYS_MID_YEAR, $start), sprintf('Días administrativos %d, desde su ingreso.', $year));
		}

		$from = max($first, $start);
		$rule = $this->rules->at(RuleBook::TRAINING_DAYS, $from);
		$owed[] = $this->movement($person, LeaveType::Training, MovementKind::Grant, $from, (float)$this->rules->value($rule)['days'], $rule,
			sprintf('Capacitación %d%s.', $year, $start > $first ? ', desde su ingreso' : ''));
		return $owed;
	}

	/** The day Annual leave starts: when service passes the rule's months, never before the start date. */
	private function annualLeaveFrom(Person $person): string {
		$start = $person->getStartDate();
		$months = (int)$this->rules->value($this->rules->at(RuleBook::ANNUAL_LEAVE_ELIGIBILITY, $start))['months'];
		return max($start, Day::after(Service::reached($start, $person->getPriorMonths(), $months)));
	}

	/** The Annual leave days of a year for $months of service: the highest step reached. */
	private function annualLeaveDays(RuleVersion $rule, int $months): float {
		$days = 0.0;
		foreach ($this->rules->value($rule)['steps'] as $step) {
			if ($months >= $step['years'] * 12) {
				$days = (float)$step['days'];
			}
		}
		return $days;
	}

	/** A mid-year joiner's Administrative days by the establishment rule; null when HR sets them. */
	private function midYearDays(Person $person): ?float {
		$days = $this->rules->value($this->rules->at(RuleBook::ADMINISTRATIVE_DAYS_MID_YEAR, $person->getStartDate()))['days'];
		return $days === null ? null : (float)$days;
	}

	private function movement(Person $person, LeaveType $type, MovementKind $kind, string $day, float $quantity, RuleVersion $rule, string $reason): Movement {
		$movement = new Movement();
		$movement->setUid($person->getUid());
		$movement->setLeaveType($type->value);
		$movement->setPeriodYear((int)substr($day, 0, 4));
		$movement->setEffectiveOn($day);
		$movement->setKind($kind->value);
		$movement->setQuantity($quantity);
		$movement->setReason($reason);
		$movement->setRuleId($rule->getRuleId());
		$movement->setRuleVersion($rule->getVersion());
		return $movement;
	}

	/** A grant's once-key: one grant of a Leave type per year, one service rise per day. */
	private static function onceKey(Movement $grant): string {
		$type = LeaveType::from($grant->getLeaveType());
		return $grant->getKind() === MovementKind::ServiceRise->value
			? self::key($type, $grant->getPeriodYear(), MovementKind::ServiceRise, $grant->getEffectiveOn())
			: self::key($type, $grant->getPeriodYear());
	}

	private static function key(LeaveType $type, int $year, MovementKind $kind = MovementKind::Grant, ?string $day = null): string {
		return implode(':', array_filter([$type->value, (string)$year, $kind->value, $day]));
	}

	/** 15.0 → «15», 3.5 → «3,5». */
	private static function number(float $days): string {
		return str_replace('.', ',', (string)round($days, 1));
	}
}
