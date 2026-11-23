<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\People;

use APS\Common\Text;
use OCA\RecursosHumanos\Access\Access;
use OCA\RecursosHumanos\Accounts;
use OCA\RecursosHumanos\Db\Person;
use OCA\RecursosHumanos\Db\PersonLogEntry;
use OCA\RecursosHumanos\Db\PersonLogMapper;
use OCA\RecursosHumanos\Db\PersonMapper;
use OCA\RecursosHumanos\Db\RegimeLine;
use OCA\RecursosHumanos\Db\RegimeLineMapper;
use OCA\RecursosHumanos\Day;
use OCA\RecursosHumanos\Forbidden;
use OCA\RecursosHumanos\Ledger\Grants;
use OCA\RecursosHumanos\Ledger\Ledger;
use OCA\RecursosHumanos\Refused;
use OCA\RecursosHumanos\Requests\Deciders;
use OCA\RecursosHumanos\Requests\Requests;
use OCA\RecursosHumanos\ResponseDefinitions;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\TTransactional;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\DB\Exception;
use OCP\IDBConnection;
use OCP\IUser;
use OCP\IUserManager;

/**
 * People (recursos-humanos#1, People): the person record and its «Falta completar» state.
 *
 * - **Every enabled account** is in «Personal»; one without a record is «Falta completar» until HR
 *   completes it, and so is a record still missing what HR sets by hand (a mid-year joiner's
 *   Administrative days, Grants). A retired (disabled) account stays listed only while it has a
 *   record, and a record outlives its account: nothing is ever deleted.
 * - **Completing a record grants the year** (Grants), and the record shows its Movements,
 *   this year's Balances and Available days, and who decides the person's Requests.
 * - **The RUT keys the record** (ADR 0006): checked by its verifier digit and unique in the
 *   establishment, with the legal nombre y apellidos.
 * - **A Regime change is a new dated line**; the earlier ones stay.
 * - **The record keeps its own trail**: who completed it and who changed its Regime, when and why.
 * - **Only HR writes**; everyone reads their own record and HR everyone's (Access).
 *
 * @psalm-import-type RecursosHumanosPerson from ResponseDefinitions
 * @psalm-import-type RecursosHumanosPersonItem from ResponseDefinitions
 * @psalm-import-type RecursosHumanosTrailStep from ResponseDefinitions
 */
class People {
	use TTransactional;

	/** The trail's kinds of step. */
	private const COMPLETED = 'completed';
	private const REGIME = 'regime';
	/** The columns' length. */
	private const NAME_LENGTH = 200;

	private const ONLY_HR = 'Solo Recursos Humanos completa y cambia las fichas del personal.';
	private const NO_PERSON = 'No encontramos a esa persona.';
	private const NO_ACCOUNT = 'No encontramos esa cuenta.';
	private const ALREADY = 'Esta persona ya tiene su ficha completa.';
	private const NAMES = 'Escribe los nombres y los apellidos.';
	private const LONG_NAMES = 'Los nombres y los apellidos van en a lo más 200 caracteres cada uno.';
	private const TAKEN = 'Ese RUT ya es de otra persona del establecimiento.';
	private const START = 'Elige la fecha de ingreso.';
	private const PRIOR = 'El servicio público anterior va en meses: un número entero desde 0.';
	private const REGIME_KIND = 'Elige el régimen: Estatuto APS u Honorarios / sin derecho.';
	private const REGIME_FROM = 'Elige la fecha desde la que rige el régimen.';
	private const SAME_DATE = 'Ya hay un régimen desde esa fecha.';
	private const AFTER_START = 'El régimen debe regir desde la fecha de ingreso o antes.';
	private const BEFORE_FIRST = 'Esa fecha es anterior al primer régimen de la ficha.';
	private const SAME_REGIME = 'Ese ya es su régimen en esa fecha.';
	private const REASON = 'Escribe el motivo: queda en el recorrido de la ficha.';
	private const NO_JEFATURA = 'Esa jefatura no es una cuenta del establecimiento.';
	private const OWN_JEFATURA = 'Una persona no puede ser su propia jefatura.';

	public function __construct(
		private PersonMapper $people,
		private RegimeLineMapper $regimes,
		private PersonLogMapper $log,
		private Grants $grants,
		private Ledger $ledger,
		private Requests $requests,
		private Deciders $deciders,
		private Access $access,
		private Accounts $accounts,
		private IUserManager $users,
		private ITimeFactory $time,
		private IDBConnection $db,
	) {
	}

	/**
	 * «Personal», by viewer: every account for HR, sorted by name; only their own for anyone else.
	 *
	 * @return list<RecursosHumanosPersonItem>
	 */
	public function listFor(string $viewer): array {
		$today = $this->today();
		if (!$this->access->readsEveryone($viewer)) {
			return [$this->item($viewer, $this->users->get($viewer), $this->people->byUid($viewer), $this->regimes->of($viewer), $today)];
		}

		$records = [];
		foreach ($this->people->all() as $person) {
			$records[$person->getUid()] = $person;
		}
		$lines = [];
		foreach ($this->regimes->all() as $line) {
			$lines[$line->getUid()][] = $line;
		}
		$accounts = [];
		$this->users->callForAllUsers(static function (IUser $user) use (&$accounts, $records): void {
			if ($user->isEnabled() || isset($records[$user->getUID()])) {
				$accounts[$user->getUID()] = $user;
			}
		});
		foreach (array_keys($records) as $uid) {
			$accounts[$uid] ??= null;
		}

		$items = [];
		foreach ($accounts as $uid => $user) {
			$items[] = $this->item((string)$uid, $user, $records[$uid] ?? null, $lines[$uid] ?? [], $today);
		}
		usort($items, static fn (array $a, array $b): int => strcmp(Text::fold($a['name']), Text::fold($b['name'])) ?: strcmp($a['uid'], $b['uid']));
		return $items;
	}

	/**
	 * One record, by RUT: the viewer's own, or anyone's for HR.
	 *
	 * @return RecursosHumanosPerson
	 * @throws DoesNotExistException no such RUT, or not the viewer's to read
	 */
	public function one(string $viewer, string $rut): array {
		return $this->view($this->visible($viewer, $rut));
	}

	/**
	 * The viewer's own record, as «Mis días» shows it; null while HR has not completed it.
	 *
	 * @return RecursosHumanosPerson|null
	 */
	public function mine(string $viewer): ?array {
		$person = $this->people->byUid($viewer);
		return $person === null ? null : $this->view($person);
	}

	/**
	 * «Completar ficha»: HR writes the record of an account that has none, with its first Regime
	 * line, and the year's grants due by today.
	 *
	 * @return RecursosHumanosPerson
	 * @throws Forbidden not an HR officer
	 * @throws DoesNotExistException no such account
	 * @throws Refused a field is missing or wrong, the Regime starts after the start date, the RUT is
	 *                 someone else's, or the record exists
	 */
	public function complete(string $actor, string $uid, string $rut, string $givenNames, string $surnames, string $startDate,
		string $regime, string $regimeFrom, int $priorMonths, bool $shift, ?string $jefatura): array {
		$this->mustWrite($actor);
		if (!$this->users->userExists($uid)) {
			throw new DoesNotExistException(self::NO_ACCOUNT);
		}
		if ($this->people->byUid($uid) !== null) {
			throw new Refused(self::ALREADY);
		}

		$person = new Person();
		$person->setUid($uid);
		$person->setGivenNames($this->name($givenNames));
		$person->setSurnames($this->name($surnames));
		$person->setRut(Rut::canonical($rut));
		$person->setStartDate(Day::of($startDate, self::START)->format('Y-m-d'));
		if ($priorMonths < 0) {
			throw new Refused(self::PRIOR);
		}
		$person->setPriorMonths($priorMonths);
		$person->setShift($shift);
		$person->setJefatura($this->jefatura($uid, $jefatura));
		$line = $this->line($uid, $regime, $regimeFrom);
		if ($line->getStartsOn() > $person->getStartDate()) {
			throw new Refused(self::AFTER_START);
		}

		try {
			$this->atomic(function () use ($person, $line, $actor): void {
				$this->people->insert($person);
				$this->regimes->insert($line);
				$this->log->insert($this->step($line, self::COMPLETED, $actor, null));
				$this->grants->due($person, [$line], $this->today());
			}, $this->db);
		} catch (Exception $e) {
			if ($e->getReason() !== Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				throw $e;
			}
			// The RUT is someone else's, or another HR officer completed this record first.
			throw new Refused($this->people->byUid($uid) === null ? self::TAKEN : self::ALREADY);
		}
		return $this->view($person);
	}

	/**
	 * «Cambiar régimen»: a new dated Regime line with its reason; the earlier lines stay, and so do
	 * the Movements written under them. The grants the new line makes due by today are written at
	 * once. A date before the first line corrects the record and belongs to the Recount
	 * (recursos-humanos#18).
	 *
	 * @return RecursosHumanosPerson
	 * @throws Forbidden not an HR officer
	 * @throws DoesNotExistException no such RUT
	 * @throws Refused a field is missing or wrong, the date is before the first line, the Regime
	 *                 is already the one in force then, or a line already starts on that date
	 */
	public function changeRegime(string $actor, string $rut, string $regime, string $from, string $reason): array {
		$this->mustWrite($actor);
		$person = $this->visible($actor, $rut);
		$line = $this->line($person->getUid(), $regime, $from);
		$current = Regime::inForce($this->regimes->of($person->getUid()), $line->getStartsOn())?->value;
		if ($current === null) {
			throw new Refused(self::BEFORE_FIRST);
		}
		if ($current === $line->getRegime()) {
			throw new Refused(self::SAME_REGIME);
		}
		$reason = trim($reason);
		if ($reason === '') {
			throw new Refused(self::REASON);
		}

		try {
			$this->atomic(function () use ($person, $line, $actor, $reason): void {
				$this->regimes->insert($line);
				$this->log->insert($this->step($line, self::REGIME, $actor, $reason));
				$this->grants->due($person, $this->regimes->of($person->getUid()), $this->today());
			}, $this->db);
		} catch (Exception $e) {
			if ($e->getReason() !== Exception::REASON_UNIQUE_CONSTRAINT_VIOLATION) {
				throw $e;
			}
			throw new Refused(self::SAME_DATE);
		}
		return $this->view($person);
	}

	/**
	 * «Completar ficha» for a mid-year joiner: HR sets this year's Administrative days.
	 *
	 * @return RecursosHumanosPerson
	 * @throws Forbidden not an HR officer
	 * @throws DoesNotExistException no such RUT
	 * @throws Refused not a joiner whose days HR sets, already set, or out of range
	 */
	public function setAdministrativeDays(string $actor, string $rut, float $days): array {
		$this->mustWrite($actor);
		$person = $this->visible($actor, $rut);
		$this->grants->setAdministrativeDays($person, $this->regimes->of($person->getUid()), $actor, $days, $this->today());
		return $this->view($person);
	}

	private function mustWrite(string $actor): void {
		if (!$this->access->writes($actor)) {
			throw new Forbidden(self::ONLY_HR);
		}
	}

	/** @throws DoesNotExistException */
	private function visible(string $viewer, string $rut): Person {
		try {
			$person = $this->people->byRut(Rut::canonical($rut));
		} catch (Refused) {
			$person = null;
		}
		if ($person === null || ($person->getUid() !== $viewer && !$this->access->readsEveryone($viewer))) {
			throw new DoesNotExistException(self::NO_PERSON);
		}
		return $person;
	}

	/** @return RecursosHumanosPerson */
	private function view(Person $person): array {
		$uid = $person->getUid();
		$lines = $this->regimes->of($uid);
		$today = $this->today();
		$movements = $this->ledger->of($uid);
		return $this->item($uid, $this->users->get($uid), $person, $lines, $today) + [
			'startDate' => $person->getStartDate(),
			'priorMonths' => $person->getPriorMonths(),
			'shift' => (bool)$person->getShift(),
			'regimes' => array_map(static fn (RegimeLine $line): array => ['regime' => $line->getRegime(), 'from' => $line->getStartsOn()], $lines),
			'trail' => array_map(fn (PersonLogEntry $entry): array => $this->trailStep($entry), $this->log->of($uid)),
			'days' => $this->grants->summary($person, $lines, $movements, $this->requests->inFlight($uid, Day::year($today)), $today),
			'decider' => $this->deciders->view($this->deciders->of($person)),
			'movements' => array_map($this->ledger->view(...), $movements),
		];
	}

	/**
	 * @param list<RegimeLine> $lines oldest first
	 * @return RecursosHumanosPersonItem
	 */
	private function item(string $uid, ?IUser $user, ?Person $person, array $lines, string $today): array {
		$jefatura = $person?->getJefatura();
		return [
			'uid' => $uid,
			'name' => $user?->getDisplayName() ?? $person?->getGivenNames() . ' ' . $person?->getSurnames(),
			'cargos' => $user === null ? [] : $this->access->cargosOf($user),
			'complete' => $person !== null && $this->grants->missing($person, $lines, $today) === [],
			'rut' => $person?->getRut(),
			'givenNames' => $person?->getGivenNames(),
			'surnames' => $person?->getSurnames(),
			'regime' => Regime::inForce($lines, $today)?->value,
			'jefatura' => $jefatura === null ? null : $this->accounts->view($jefatura),
		];
	}

	/** @return RecursosHumanosTrailStep */
	private function trailStep(PersonLogEntry $entry): array {
		$detail = json_decode($entry->getDetail() ?? '', true);
		return [
			'kind' => $entry->getKind(),
			'actor' => $this->accounts->view($entry->getActor()),
			'at' => $entry->getAt(),
			'regime' => $detail['regime'] ?? null,
			'from' => $detail['from'] ?? null,
			'reason' => $entry->getNote(),
		];
	}

	/** The trail step that writes $line: the record's completion with its first line, or a Regime change. */
	private function step(RegimeLine $line, string $kind, string $actor, ?string $reason): PersonLogEntry {
		$entry = new PersonLogEntry();
		$entry->setUid($line->getUid());
		$entry->setKind($kind);
		$entry->setActor($actor);
		$entry->setAt($this->time->getTime());
		$entry->setDetail(json_encode(['regime' => $line->getRegime(), 'from' => $line->getStartsOn()], JSON_THROW_ON_ERROR));
		$entry->setNote($reason);
		return $entry;
	}

	/** @throws Refused */
	private function line(string $uid, string $regime, string $from): RegimeLine {
		$line = new RegimeLine();
		$line->setUid($uid);
		$line->setRegime((Regime::tryFrom($regime) ?? throw new Refused(self::REGIME_KIND))->value);
		$line->setStartsOn(Day::of($from, self::REGIME_FROM)->format('Y-m-d'));
		return $line;
	}

	/** @throws Refused */
	private function name(string $typed): string {
		$name = trim((string)preg_replace('/\s+/u', ' ', $typed));
		if ($name === '') {
			throw new Refused(self::NAMES);
		}
		if (mb_strlen($name) > self::NAME_LENGTH) {
			throw new Refused(self::LONG_NAMES);
		}
		return $name;
	}

	/** @throws Refused */
	private function jefatura(string $uid, ?string $jefatura): ?string {
		if ($jefatura === null || $jefatura === '') {
			return null;
		}
		if ($jefatura === $uid) {
			throw new Refused(self::OWN_JEFATURA);
		}
		if (!$this->users->userExists($jefatura)) {
			throw new Refused(self::NO_JEFATURA);
		}
		return $jefatura;
	}

	private function today(): string {
		return Day::today($this->time);
	}
}
