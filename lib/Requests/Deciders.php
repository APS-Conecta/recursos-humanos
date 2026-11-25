<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Requests;

use OCA\RecursosHumanos\Access\Access;
use OCA\RecursosHumanos\Accounts;
use OCA\RecursosHumanos\Day;
use OCA\RecursosHumanos\Db\Delegation;
use OCA\RecursosHumanos\Db\DelegationMapper;
use OCA\RecursosHumanos\Db\Person;
use OCA\RecursosHumanos\Forbidden;
use OCA\RecursosHumanos\Refused;
use OCA\RecursosHumanos\ResponseDefinitions;
use OCP\AppFramework\Utility\ITimeFactory;

/**
 * Deciders (recursos-humanos#1, Modules): who decides a person's Requests today, and in what
 * capacity, and the Director's Delegation that lets a Jefatura decide.
 *
 * - **The assigned Jefatura decides «por delegación del Director/a»**; a Jefatura who is the
 *   Director decides as Director.
 * - **Without a Jefatura, the Director decides «como Director/a»**: any account in the Director's
 *   cargo group.
 * - **The Director's own Requests are decided outside the app**: nobody decides them here, and HR
 *   registers them with the outside document (recursos-humanos#13, as are the Stand-ins, an absent
 *   Jefatura and the Subdirector «(S)»).
 * - **The decider is read when asked**, never stored on the Request: a new Jefatura decides the
 *   pending Requests at once. The trail keeps who decided, and in what capacity.
 * - **The Delegation** is recorded by HR with the resolution's number and date; a new one is a new
 *   row, and the latest is in force.
 *
 * @psalm-import-type RecursosHumanosDecider from ResponseDefinitions
 * @psalm-import-type RecursosHumanosDelegation from ResponseDefinitions
 */
class Deciders {
	private const NUMBER_LENGTH = 64;

	private const ONLY_HR = 'Solo Recursos Humanos registra la delegación.';
	private const NUMBER = 'Escribe el número de la resolución.';
	private const LONG_NUMBER = 'El número de la resolución va en a lo más 64 caracteres.';
	private const DATE = 'Elige la fecha de la resolución.';
	private const FUTURE = 'La fecha de la resolución no puede ser posterior a hoy.';

	public function __construct(
		private Access $access,
		private DelegationMapper $delegations,
		private Accounts $accounts,
		private ITimeFactory $time,
	) {
	}

	public function of(Person $person): Decider {
		$uid = $person->getUid();
		if ($this->access->isDirector($uid)) {
			return new Decider(Capacity::External, []);
		}
		$jefatura = $person->getJefatura();
		if ($jefatura !== null) {
			return new Decider($this->access->isDirector($jefatura) ? Capacity::Director : Capacity::Delegation, [$jefatura]);
		}
		return new Decider(Capacity::Director, $this->access->directors());
	}

	/** @return RecursosHumanosDecider */
	public function view(Decider $decider): array {
		return ['capacity' => $decider->capacity->value, 'accounts' => array_map($this->accounts->view(...), $decider->uids)];
	}

	/** The Delegation in force: the latest HR recorded. */
	public function delegation(): ?Delegation {
		return $this->delegations->latest();
	}

	/**
	 * The Delegations decisions were taken by, as their trail steps name them.
	 *
	 * @param list<int> $ids
	 * @return array<int, Delegation> by id
	 */
	public function delegations(array $ids): array {
		return $this->delegations->byIds($ids);
	}

	/**
	 * HR records the Delegation in force, by its resolution's number and date.
	 *
	 * @throws Forbidden not an HR officer
	 * @throws Refused the number is missing or too long, or the date is not a day up to today
	 */
	public function record(string $actor, string $number, string $date): Delegation {
		if (!$this->access->writes($actor)) {
			throw new Forbidden(self::ONLY_HR);
		}
		$number = trim($number);
		if ($number === '') {
			throw new Refused(self::NUMBER);
		}
		if (mb_strlen($number) > self::NUMBER_LENGTH) {
			throw new Refused(self::LONG_NUMBER);
		}
		$day = Day::of($date, self::DATE)->format('Y-m-d');
		if ($day > Day::today($this->time)) {
			throw new Refused(self::FUTURE);
		}
		$delegation = new Delegation();
		$delegation->setNumber($number);
		$delegation->setGrantedOn($day);
		$delegation->setRecordedBy($actor);
		$delegation->setRecordedAt($this->time->getTime());
		return $this->delegations->insert($delegation);
	}

	/** @return RecursosHumanosDelegation */
	public function delegationView(Delegation $delegation): array {
		return [
			'number' => $delegation->getNumber(),
			'date' => $delegation->getGrantedOn(),
			'recordedBy' => $this->accounts->view($delegation->getRecordedBy()),
			'recordedAt' => $delegation->getRecordedAt(),
		];
	}
}
