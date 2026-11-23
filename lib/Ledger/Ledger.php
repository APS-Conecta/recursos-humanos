<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Ledger;

use OCA\RecursosHumanos\Accounts;
use OCA\RecursosHumanos\Db\Movement;
use OCA\RecursosHumanos\Db\MovementMapper;
use OCA\RecursosHumanos\ResponseDefinitions;
use OCA\RecursosHumanos\RuleBook\RuleBook;
use OCA\RecursosHumanos\WorkingDays\Unit;
use OCP\DB\Exception;

/**
 * Ledger (recursos-humanos#1, Modules; ADR 0001): the Movements, and the Balances that are their
 * sum per person, Leave type and period, never stored.
 *
 * - **A Movement is only ever added**: none is edited or deleted.
 * - **A once-only Movement** (a year's grant, a service rise) carries its key, and the database
 *   refuses a second one for the person, so two runs at the same moment cannot both write it.
 *
 * @psalm-import-type RecursosHumanosBalance from ResponseDefinitions
 * @psalm-import-type RecursosHumanosMovement from ResponseDefinitions
 */
class Ledger {
	/** @var array<string, array<string, true>> who holds each once-key, as read so far */
	private array $holders = [];

	public function __construct(
		private MovementMapper $movements,
		private RuleBook $rules,
		private Accounts $accounts,
	) {
	}

	/** Writes $movement unless its person already holds $onceKey; true when written. */
	public function writeOnce(Movement $movement, string $onceKey): bool {
		$uid = $movement->getUid();
		if ($this->holds($uid, $onceKey)) {
			return false;
		}
		$movement->setOnceKey($onceKey);
		try {
			$this->movements->insert($movement);
		} catch (Exception $e) {
			if ($e->getReason() !== Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				throw $e;
			}
			return false;
		}
		$this->holders[$onceKey][$uid] = true;
		return true;
	}

	/** Whether the person has the once-only Movement $onceKey; one query per key for everyone. */
	public function holds(string $uid, string $onceKey): bool {
		$this->holders[$onceKey] ??= array_fill_keys($this->movements->holders($onceKey), true);
		return isset($this->holders[$onceKey][$uid]);
	}

	/** @return list<Movement> the person's Movements, newest first */
	public function of(string $uid): array {
		return $this->movements->of($uid);
	}

	/**
	 * The person's Balance of each Leave type in $period: the sum of its Movements, and of those
	 * that granted the days, in the unit its rule counts in that year; and what is Available, the
	 * Balance minus the days of the Requests in flight (CONTEXT.md).
	 *
	 * @param list<Movement> $movements the person's
	 * @param array<string, float> $inFlight the days of the person's Requests in flight in $period, by Leave type
	 * @return list<RecursosHumanosBalance>
	 */
	public function balances(array $movements, int $period, array $inFlight = []): array {
		$balances = [];
		foreach (LeaveType::cases() as $type) {
			$balances[$type->value] = ['leaveType' => $type->value, 'unit' => $this->unit($type, "$period-01-01")->value, 'balance' => 0.0, 'granted' => 0.0, 'available' => 0.0];
		}
		foreach ($movements as $movement) {
			if ($movement->getPeriodYear() !== $period || !isset($balances[$movement->getLeaveType()])) {
				continue;
			}
			$balances[$movement->getLeaveType()]['balance'] += $movement->getQuantity();
			if (in_array($movement->getKind(), [MovementKind::Grant->value, MovementKind::ServiceRise->value], true)) {
				$balances[$movement->getLeaveType()]['granted'] += $movement->getQuantity();
			}
		}
		foreach ($balances as $type => $balance) {
			$balances[$type]['available'] = $balance['balance'] - ($inFlight[$type] ?? 0.0);
		}
		return array_values($balances);
	}

	/** The unit a Leave type's days count in on $day, as the rule that grants it says. */
	public function unit(LeaveType $type, string $day): Unit {
		return Unit::from($this->rules->at($type->rule(), $day)->value()['unit']);
	}

	/** @return RecursosHumanosMovement */
	public function view(Movement $movement): array {
		$actor = $movement->getActor();
		$rule = $movement->getRuleId();
		return [
			'id' => $movement->getId(),
			'leaveType' => $movement->getLeaveType(),
			'period' => $movement->getPeriodYear(),
			'date' => $movement->getEffectiveOn(),
			'kind' => $movement->getKind(),
			'quantity' => $movement->getQuantity(),
			'actor' => $actor === null ? null : $this->accounts->view($actor),
			'reason' => $movement->getReason(),
			'ruleVersion' => $rule === null ? null : [
				'rule' => $rule,
				'version' => (int)$movement->getRuleVersion(),
				'title' => $this->rules->title($rule, (int)$movement->getRuleVersion()),
			],
		];
	}
}
