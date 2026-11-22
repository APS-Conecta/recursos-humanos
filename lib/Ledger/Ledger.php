<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Ledger;

use OCA\RecursosHumanos\Db\Movement;
use OCA\RecursosHumanos\Db\MovementMapper;
use OCA\RecursosHumanos\ResponseDefinitions;
use OCA\RecursosHumanos\RuleBook\RuleBook;
use OCP\DB\Exception;
use OCP\IUserManager;

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
		private IUserManager $users,
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
	 * that granted the days.
	 *
	 * @param list<Movement> $movements the person's
	 * @return list<RecursosHumanosBalance>
	 */
	public function balances(array $movements, int $period): array {
		$balances = [];
		foreach (LeaveType::cases() as $type) {
			$balances[$type->value] = ['leaveType' => $type->value, 'unit' => $type->unit()->value, 'balance' => 0.0, 'granted' => 0.0];
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
		return array_values($balances);
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
			'actor' => $actor === null ? null : ['uid' => $actor, 'name' => $this->users->getDisplayName($actor) ?? $actor],
			'reason' => $movement->getReason(),
			'rule' => $rule === null ? null : [
				'rule' => $rule,
				'version' => (int)$movement->getRuleVersion(),
				'title' => $this->rules->title($rule, (int)$movement->getRuleVersion()),
			],
		];
	}
}
