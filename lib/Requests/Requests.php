<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Requests;

use OCA\RecursosHumanos\Access\Access;
use OCA\RecursosHumanos\Accounts;
use OCA\RecursosHumanos\Day;
use OCA\RecursosHumanos\Db\Delegation;
use OCA\RecursosHumanos\Db\PersonMapper;
use OCA\RecursosHumanos\Db\RegimeLineMapper;
use OCA\RecursosHumanos\Db\Request;
use OCA\RecursosHumanos\Db\RequestMapper;
use OCA\RecursosHumanos\Db\RequestStep;
use OCA\RecursosHumanos\Db\RequestStepMapper;
use OCA\RecursosHumanos\Forbidden;
use OCA\RecursosHumanos\Ledger\Grants;
use OCA\RecursosHumanos\Ledger\LeaveType;
use OCA\RecursosHumanos\Ledger\Ledger;
use OCA\RecursosHumanos\People\Regime;
use OCA\RecursosHumanos\Refused;
use OCA\RecursosHumanos\ResponseDefinitions;
use OCA\RecursosHumanos\RuleBook\RuleBook;
use OCA\RecursosHumanos\Spanish;
use OCA\RecursosHumanos\WorkingDays\Unit;
use OCA\RecursosHumanos\WorkingDays\WorkingDays;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\TTransactional;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\IDBConnection;
use OCP\IUserManager;

/**
 * Requests (recursos-humanos#1, Modules): a person asks for days of a Leave type, and its decider
 * approves or rejects it. Registration (recursos-humanos#7), withdrawal and Cancellation (#8)
 * come next.
 *
 * - **Checks at submission**, the same the preview lists while the form changes: a complete
 *   record under the Estatuto APS on those days; Annual leave only from more than a year of
 *   service; someone to decide; at least one day counted; no overlap with another Request in
 *   flight; and never above Available, year by year (stories 2, 10). A failed check refuses the
 *   send and is a line in the preview's errors, so the form keeps its count.
 * - **Available** is the Balance minus the days of the person's Requests in flight (CONTEXT.md),
 *   each day off its own year.
 * - **Each Request cites the Rule versions it was counted with** (ADR 0002); a rejected Annual
 *   leave also cites the establishment rule that allows the rejection (ADR 0005).
 * - **Only the decider decides, and only once**; the trail records the capacity and, for a
 *   Jefatura, the Delegation, which must be on record.
 * - **Who reads a Request**: the person, its decider, whoever acted on it, and HR.
 *
 * @psalm-import-type RecursosHumanosAvailable from ResponseDefinitions
 * @psalm-import-type RecursosHumanosCount from ResponseDefinitions
 * @psalm-import-type RecursosHumanosDelegation from ResponseDefinitions
 * @psalm-import-type RecursosHumanosPreview from ResponseDefinitions
 * @psalm-import-type RecursosHumanosRequest from ResponseDefinitions
 * @psalm-import-type RecursosHumanosRequestStep from ResponseDefinitions
 * @psalm-import-type RecursosHumanosYearCount from ResponseDefinitions
 */
class Requests {
	use TTransactional;

	/** What a person may ask for so far; the other Leave types arrive with recursos-humanos#10. */
	private const ASKED = [LeaveType::AnnualLeave, LeaveType::AdministrativeDay];
	/** A comment's or a reason's length. */
	private const TEXT_LENGTH = 1000;

	private const LEAVE_TYPE = 'Elige el tipo de permiso: feriado legal o días administrativos.';
	private const UNIT_OF_TYPE = '%s: se cuenta en %s.';
	private const LONG_COMMENT = 'El comentario va en a lo más 1000 caracteres.';
	private const NO_RECORD = 'Recursos Humanos todavía no completa tu ficha: cuando lo haga, podrás pedir días.';
	private const MISSING_DAYS = 'Recursos Humanos aún no fija tus días administrativos de %d: ingresaste a mitad de año.';
	private const HONORARIOS = 'Estás a honorarios: sin feriado legal ni días administrativos.';
	private const NOT_YET = 'Tendrás feriado legal desde el %s, al cumplir más de un año de servicio.';
	private const NOBODY = 'Nadie puede decidir tu solicitud: no tienes jefatura directa y el establecimiento no tiene Director/a en la app. Avísale a Recursos Humanos.';
	private const NO_DAYS = 'En esas fechas no hay días hábiles: son fines de semana o días feriados.';
	private const OVERLAP = 'Ya tienes una solicitud en esas fechas: %s, %s.';
	private const ABOVE = '%d: pides %s y tu disponible es %s: saldo %s%s.';
	private const ABOVE_IN_FLIGHT = ' menos %s en solicitudes en curso';
	private const NO_REQUEST = 'No encontramos esa solicitud.';
	private const NOT_DECIDER = 'Esta solicitud no la decides tú: la decide la jefatura directa de la persona o, sin jefatura, el Director/a.';
	private const DECIDED = 'Esta solicitud ya está decidida.';
	private const NO_DELEGATION = 'Recursos Humanos todavía no registra la delegación del Director/a: sin ella, la jefatura no puede decidir.';
	private const REASON = 'Escribe el motivo del rechazo: la persona lo ve en su solicitud.';
	private const LONG_REASON = 'El motivo va en a lo más 1000 caracteres.';
	private const NOT_REJECTABLE = 'El feriado legal no puede rechazarse: solo el Director/a puede moverlo dentro del año.';

	/** @var array<int, RecursosHumanosCount> each Request's count, as read so far */
	private array $counts = [];

	public function __construct(
		private RequestMapper $requests,
		private RequestStepMapper $steps,
		private PersonMapper $people,
		private RegimeLineMapper $regimes,
		private Grants $grants,
		private Ledger $ledger,
		private WorkingDays $workingDays,
		private RuleBook $rules,
		private Deciders $deciders,
		private Access $access,
		private Notices $notices,
		private Accounts $accounts,
		private IUserManager $users,
		private ITimeFactory $time,
		private IDBConnection $db,
	) {
	}

	/**
	 * The count «Nueva solicitud» shows as the dates change, and the unit it counted in. With a
	 * Leave type, it counts in that type's unit, and adds what a send would refuse on and
	 * Available in each year of the range. It writes nothing.
	 *
	 * @return RecursosHumanosPreview
	 * @throws Refused malformed input: a date, the unit or the Leave type
	 */
	public function preview(string $viewer, string $from, string $to, ?string $unit, ?string $leaveType): array {
		if ($leaveType === null || $leaveType === '') {
			return $this->workingDays->count($from, $to, (string)$unit) + ['unit' => (string)$unit, 'errors' => [], 'available' => []];
		}
		$type = self::type($leaveType);
		$typeUnit = $this->ledger->unit($type, $from);
		if ($unit !== null && $unit !== '' && $unit !== $typeUnit->value) {
			throw new Refused(sprintf(self::UNIT_OF_TYPE, $type->title(), Spanish::unit($typeUnit)));
		}
		$count = $this->workingDays->count($from, $to, $typeUnit->value);
		[$errors, $available] = $this->check($viewer, $type, $typeUnit, $count, $from, $to);
		return $count + ['unit' => $typeUnit->value, 'errors' => $errors, 'available' => $available];
	}

	/**
	 * A person asks for days of a Leave type: checked as the preview checks it, then pending
	 * before its decider, who hears of it.
	 *
	 * @return RecursosHumanosRequest
	 * @throws Refused malformed input, or a check refuses it
	 */
	public function ask(string $viewer, string $leaveType, string $from, string $to, string $comment): array {
		$type = self::type($leaveType);
		$unit = $this->ledger->unit($type, $from);
		$count = $this->workingDays->count($from, $to, $unit->value);
		$comment = trim($comment);
		if (mb_strlen($comment) > self::TEXT_LENGTH) {
			throw new Refused(self::LONG_COMMENT);
		}

		$request = $this->atomic(function () use ($viewer, $type, $unit, $count, $from, $to, $comment): Request {
			// Two sends at the same moment are checked one after the other.
			$this->people->lock($viewer);
			[$errors] = $this->check($viewer, $type, $unit, $count, $from, $to);
			if ($errors !== []) {
				throw new Refused(implode(' ', $errors));
			}
			$request = new Request();
			$request->setUid($viewer);
			$request->setLeaveType($type->value);
			$request->setStartsOn($from);
			$request->setEndsOn($to);
			$request->setDays($count['days']);
			$request->setState(RequestState::Pending->value);
			$request->setComment($comment === '' ? null : $comment);
			$request->setRuleVersions(json_encode($this->cite($type, $from, $to), JSON_THROW_ON_ERROR));
			$this->requests->insert($request);
			$this->steps->insert($this->step($request, StepKind::Asked, $viewer, null, null, null));
			return $request;
		}, $this->db);

		$person = $this->people->byUid($viewer);
		if ($person !== null) {
			$this->notices->asked($request, $unit, $this->accounts->name($viewer), $this->deciders->of($person));
		}
		return $this->views([$request])[0];
	}

	/** @return list<RecursosHumanosRequest> the viewer's own Requests, newest first */
	public function mine(string $viewer): array {
		return $this->views($this->requests->of($viewer));
	}

	/**
	 * @return RecursosHumanosRequest
	 * @throws DoesNotExistException no such Request, or not the viewer's to read
	 */
	public function one(string $viewer, int $id): array {
		return $this->views([$this->visible($viewer, $id)])[0];
	}

	/**
	 * «Por decidir»: the pending Requests the viewer decides, oldest first, with the Delegation in force.
	 *
	 * @return array{requests: list<RecursosHumanosRequest>, delegation: ?RecursosHumanosDelegation}
	 */
	public function toDecide(string $viewer): array {
		$deciders = [];
		$mine = [];
		foreach ($this->requests->inStates([RequestState::Pending->value]) as $request) {
			$uid = $request->getUid();
			if (!array_key_exists($uid, $deciders)) {
				$person = $this->people->byUid($uid);
				$deciders[$uid] = $person === null ? null : $this->deciders->of($person);
			}
			if ($deciders[$uid]?->includes($viewer) === true) {
				$mine[] = $request;
			}
		}
		$delegation = $this->deciders->delegation();
		return ['requests' => $this->views($mine), 'delegation' => $delegation === null ? null : $this->deciders->delegationView($delegation)];
	}

	/**
	 * @return RecursosHumanosRequest
	 * @throws DoesNotExistException no such Request, or not the viewer's to read
	 * @throws Forbidden the viewer does not decide it
	 * @throws Refused already decided, or a Jefatura decides with no Delegation on record
	 */
	public function approve(string $viewer, int $id): array {
		return $this->decide($viewer, $id, StepKind::Approved, null);
	}

	/**
	 * @return RecursosHumanosRequest
	 * @throws DoesNotExistException no such Request, or not the viewer's to read
	 * @throws Forbidden the viewer does not decide it
	 * @throws Refused no reason, already decided, a Jefatura decides with no Delegation on record,
	 *                 or the Rule version in force forbids rejecting Annual leave
	 */
	public function reject(string $viewer, int $id, string $reason): array {
		return $this->decide($viewer, $id, StepKind::Rejected, $reason);
	}

	/** @return array<string, float> the days of the person's Requests in flight in $year, by Leave type */
	public function inFlight(string $uid, int $year): array {
		return $this->inFlightOf($this->requests->of($uid), null)[$year] ?? [];
	}

	/** @return RecursosHumanosRequest */
	private function decide(string $viewer, int $id, StepKind $kind, ?string $reason): array {
		$request = $this->visible($viewer, $id);
		$person = $this->people->byUid($request->getUid()) ?? throw new DoesNotExistException(self::NO_REQUEST);
		$decider = $this->deciders->of($person);
		if (!$decider->includes($viewer)) {
			throw new Forbidden(self::NOT_DECIDER);
		}
		if ($request->getState() !== RequestState::Pending->value) {
			throw new Refused(self::DECIDED);
		}
		$delegation = $decider->capacity === Capacity::Delegation ? ($this->deciders->delegation() ?? throw new Refused(self::NO_DELEGATION)) : null;
		$cited = $request->cited();
		if ($kind === StepKind::Rejected) {
			$reason = trim((string)$reason);
			if ($reason === '') {
				throw new Refused(self::REASON);
			}
			if (mb_strlen($reason) > self::TEXT_LENGTH) {
				throw new Refused(self::LONG_REASON);
			}
			if ($request->getLeaveType() === LeaveType::AnnualLeave->value) {
				$rule = $this->rules->at(RuleBook::ANNUAL_LEAVE_REJECTION, Day::today($this->time));
				if ($rule->value()['rejectable'] !== true) {
					throw new Refused(self::NOT_REJECTABLE);
				}
				$cited[] = ['rule' => $rule->getRuleId(), 'version' => $rule->getVersion()];
			}
		}

		$state = $kind === StepKind::Approved ? RequestState::Approved : RequestState::Rejected;
		$this->atomic(function () use ($request, $state, $cited, $kind, $viewer, $decider, $delegation, $reason): void {
			// Another decider may have got there first.
			if (!$this->requests->move($request->getId(), RequestState::Pending->value, $state->value)) {
				throw new Refused(self::DECIDED);
			}
			if ($cited !== $request->cited()) {
				$request->setRuleVersions(json_encode($cited, JSON_THROW_ON_ERROR));
				$this->requests->update($request);
			}
			$this->steps->insert($this->step($request, $kind, $viewer, $decider->capacity, $delegation?->getId(), $reason));
		}, $this->db);

		$this->notices->decided($request, $this->accounts->name($viewer), $kind, $decider->capacity, $reason);
		return $this->one($viewer, $id);
	}

	/**
	 * What a send would refuse on, and Available in each year the range touches.
	 *
	 * @param RecursosHumanosCount $count
	 * @return array{list<string>, list<RecursosHumanosAvailable>}
	 */
	private function check(string $uid, LeaveType $type, Unit $unit, array $count, string $from, string $to): array {
		$person = $this->people->byUid($uid);
		if ($person === null) {
			return [[self::NO_RECORD], []];
		}
		$lines = $this->regimes->of($uid);
		$today = Day::today($this->time);
		if ($this->grants->missing($person, $lines, $today) !== []) {
			return [[sprintf(self::MISSING_DAYS, Day::year($today))], []];
		}
		if (Regime::inForce($lines, $from) !== Regime::EstatutoAps || Regime::inForce($lines, $to) !== Regime::EstatutoAps) {
			return [[self::HONORARIOS], []];
		}
		$annualLeaveFrom = $this->grants->annualLeaveFrom($person);
		if ($type === LeaveType::AnnualLeave && $from < $annualLeaveFrom) {
			return [[sprintf(self::NOT_YET, Spanish::day($annualLeaveFrom))], []];
		}

		$errors = [];
		$decider = $this->deciders->of($person);
		if ($decider->capacity !== Capacity::External && $decider->uids === []) {
			$errors[] = self::NOBODY;
		}
		if ($count['days'] <= 0) {
			$errors[] = self::NO_DAYS;
		}
		$theirs = $this->requests->of($uid);
		foreach ($theirs as $other) {
			if (RequestState::from($other->getState())->inFlight() && $other->getStartsOn() <= $to && $other->getEndsOn() >= $from) {
				$errors[] = sprintf(self::OVERLAP, mb_strtolower(LeaveType::from($other->getLeaveType())->title()),
					Spanish::range($other->getStartsOn(), $other->getEndsOn()));
				break;
			}
		}
		$available = $this->available($uid, $type, $count['years'], $theirs, null);
		foreach ($available as $i => $year) {
			$asked = $count['years'][$i]['days'];
			if ($asked > $year['available']) {
				$errors[] = sprintf(self::ABOVE, $year['year'], Spanish::days($asked, $unit), Spanish::number(max(0.0, $year['available'])),
					Spanish::number($year['balance']), $year['inFlight'] > 0 ? sprintf(self::ABOVE_IN_FLIGHT, Spanish::number($year['inFlight'])) : '');
			}
		}
		return [$errors, $available];
	}

	/**
	 * Available in each of $years for days of $type: the Balance minus the days of the person's
	 * Requests in flight, $except left out.
	 *
	 * @param list<RecursosHumanosYearCount> $years
	 * @param list<Request> $theirs the person's Requests
	 * @return list<RecursosHumanosAvailable>
	 */
	private function available(string $uid, LeaveType $type, array $years, array $theirs, ?int $except): array {
		$movements = $this->ledger->of($uid);
		$inFlight = $this->inFlightOf($theirs, $except);
		$lines = [];
		foreach ($years as $line) {
			$year = $line['year'];
			$balance = array_column($this->ledger->balances($movements, $year, $inFlight[$year] ?? []), null, 'leaveType')[$type->value];
			$lines[] = ['year' => $year, 'balance' => $balance['balance'], 'inFlight' => $inFlight[$year][$type->value] ?? 0.0, 'available' => $balance['available']];
		}
		return $lines;
	}

	/**
	 * @param list<Request> $requests
	 * @return array<int, array<string, float>> the days in flight by year, then by Leave type
	 */
	private function inFlightOf(array $requests, ?int $except): array {
		$days = [];
		foreach ($requests as $request) {
			if ($request->getId() === $except || !RequestState::from($request->getState())->inFlight()) {
				continue;
			}
			$type = $request->getLeaveType();
			foreach ($this->count($request)['years'] as $line) {
				$days[$line['year']][$type] = ($days[$line['year']][$type] ?? 0.0) + $line['days'];
			}
		}
		return $days;
	}

	/** @return RecursosHumanosCount the Request's range, counted in its Leave type's unit */
	private function count(Request $request): array {
		return $this->counts[$request->getId()] ??= $this->workingDays->count($request->getStartsOn(), $request->getEndsOn(),
			$this->ledger->unit(LeaveType::from($request->getLeaveType()), $request->getStartsOn())->value);
	}

	/** @return list<array{rule: string, version: int}> the versions of the type's rules in force on the range's first and last day */
	private function cite(LeaveType $type, string $from, string $to): array {
		$cited = [];
		foreach ($type->countedWith() as $rule) {
			foreach ([$from, $to] as $day) {
				$version = $this->rules->at($rule, $day)->getVersion();
				$cited["$rule/$version"] = ['rule' => $rule, 'version' => $version];
			}
		}
		return array_values($cited);
	}

	/** @throws DoesNotExistException no such Request, or not the viewer's to read */
	private function visible(string $viewer, int $id): Request {
		$request = $this->requests->byId($id);
		if ($request === null || !$this->reads($viewer, $request)) {
			throw new DoesNotExistException(self::NO_REQUEST);
		}
		return $request;
	}

	private function reads(string $viewer, Request $request): bool {
		if ($request->getUid() === $viewer || $this->access->readsEveryone($viewer)) {
			return true;
		}
		$person = $this->people->byUid($request->getUid());
		if ($person !== null && $this->deciders->of($person)->includes($viewer)) {
			return true;
		}
		foreach ($this->steps->of([$request->getId()])[$request->getId()] as $step) {
			if ($step->getActor() === $viewer) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param list<Request> $requests
	 * @return list<RecursosHumanosRequest>
	 */
	private function views(array $requests): array {
		$trails = $this->steps->of(array_map(static fn (Request $request): int => $request->getId(), $requests));
		$ids = [];
		foreach ($trails as $trail) {
			foreach ($trail as $step) {
				$ids[] = $step->getDelegationId();
			}
		}
		$delegations = $this->deciders->delegations(array_values(array_unique(array_filter($ids, static fn (?int $id): bool => $id !== null))));
		$theirs = [];
		$views = [];
		foreach ($requests as $request) {
			$uid = $request->getUid();
			$theirs[$uid] ??= $this->requests->of($uid);
			$views[] = $this->view($request, $trails[$request->getId()], $delegations, $theirs[$uid]);
		}
		return $views;
	}

	/**
	 * @param list<RequestStep> $trail
	 * @param array<int, Delegation> $delegations by id
	 * @param list<Request> $theirs the person's Requests
	 * @return RecursosHumanosRequest
	 */
	private function view(Request $request, array $trail, array $delegations, array $theirs): array {
		$uid = $request->getUid();
		$type = LeaveType::from($request->getLeaveType());
		$state = RequestState::from($request->getState());
		$count = $this->count($request);
		$person = $this->people->byUid($uid);
		$user = $this->users->get($uid);
		$cited = [];
		foreach ($request->cited() as $rule) {
			$version = $this->rules->version($rule['rule'], $rule['version']);
			if ($version !== null) {
				$cited[] = RuleBook::view($version);
			}
		}
		return [
			'id' => $request->getId(),
			'person' => [
				'uid' => $uid,
				'name' => $this->accounts->name($uid),
				'rut' => (string)$person?->getRut(),
				'cargos' => $user === null ? [] : $this->access->cargosOf($user),
			],
			'leaveType' => $type->value,
			'from' => $request->getStartsOn(),
			'to' => $request->getEndsOn(),
			'unit' => $this->ledger->unit($type, $request->getStartsOn())->value,
			'days' => $request->getDays(),
			'years' => $count['years'],
			'counted' => $count['counted'],
			'skipped' => $count['skipped'],
			'state' => $state->value,
			'comment' => $request->getComment(),
			'decider' => $state === RequestState::Pending && $person !== null ? $this->deciders->view($this->deciders->of($person)) : null,
			'available' => $state->inFlight() ? $this->available($uid, $type, $count['years'], $theirs, $request->getId()) : [],
			'ruleVersions' => $cited,
			'trail' => array_map(fn (RequestStep $step): array => $this->stepView($step, $delegations), $trail),
		];
	}

	/**
	 * @param array<int, Delegation> $delegations by id
	 * @return RecursosHumanosRequestStep
	 */
	private function stepView(RequestStep $step, array $delegations): array {
		$delegation = $delegations[$step->getDelegationId() ?? 0] ?? null;
		return [
			'kind' => $step->getKind(),
			'actor' => $this->accounts->view($step->getActor()),
			'at' => $step->getAt(),
			'capacity' => $step->getCapacity(),
			'delegation' => $delegation === null ? null : $this->deciders->delegationView($delegation),
			'note' => $step->getNote(),
		];
	}

	private function step(Request $request, StepKind $kind, string $actor, ?Capacity $capacity, ?int $delegation, ?string $note): RequestStep {
		$step = new RequestStep();
		$step->setRequestId($request->getId());
		$step->setKind($kind->value);
		$step->setActor($actor);
		$step->setCapacity($capacity?->value);
		$step->setDelegationId($delegation);
		$step->setAt($this->time->getTime());
		$step->setNote($note);
		return $step;
	}

	/** @throws Refused a Leave type nobody may ask for yet */
	private static function type(string $leaveType): LeaveType {
		$type = LeaveType::tryFrom($leaveType);
		if ($type === null || !in_array($type, self::ASKED, true)) {
			throw new Refused(self::LEAVE_TYPE);
		}
		return $type;
	}
}
