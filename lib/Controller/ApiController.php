<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Controller;

use OCA\RecursosHumanos\Access\Access;
use OCA\RecursosHumanos\AppInfo\Application;
use OCA\RecursosHumanos\Forbidden;
use OCA\RecursosHumanos\People\People;
use OCA\RecursosHumanos\Refused;
use OCA\RecursosHumanos\Requests\Deciders;
use OCA\RecursosHumanos\Requests\Requests;
use OCA\RecursosHumanos\ResponseDefinitions;
use OCA\RecursosHumanos\RuleBook\RuleBook;
use OCA\RecursosHumanos\WorkingDays\WorkingDays;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\OpenAPI;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\OCSController;
use OCP\IRequest;

/**
 * «Recursos Humanos» over OCS: API v1, the one its own pages read and outside systems use
 * (recursos-humanos#1, API contract). v1 only grows; a change that would break callers ships as
 * v2 beside it. Consumers send `OCS-APIRequest: true`, which is also the CSRF protection, and get
 * the OCS envelope back; a session or a Nextcloud app password signs them in. Every route is
 * attribute-routed, open to any signed-in person and rate-limited (MetadataTest holds all three);
 * who may see what is checked inside each action, through Access. A refusal answers
 * aps-common's client error shape, `{message}`, with the Spanish reason the screens show.
 *
 * @psalm-import-type RecursosHumanosDelegation from ResponseDefinitions
 * @psalm-import-type RecursosHumanosHoliday from ResponseDefinitions
 * @psalm-import-type RecursosHumanosPerson from ResponseDefinitions
 * @psalm-import-type RecursosHumanosPersonItem from ResponseDefinitions
 * @psalm-import-type RecursosHumanosPreview from ResponseDefinitions
 * @psalm-import-type RecursosHumanosRequest from ResponseDefinitions
 * @psalm-import-type RecursosHumanosRuleVersion from ResponseDefinitions
 */
#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
class ApiController extends OCSController {
	/** Reads: a ceiling against a runaway client, not a quota. */
	private const READ_LIMIT = 120;
	/** Writes: a ceiling against a runaway client. */
	private const WRITE_LIMIT = 30;
	private const PERIOD = 60;

	public function __construct(
		IRequest $request,
		private Access $access,
		private People $people,
		private WorkingDays $workingDays,
		private RuleBook $rules,
		private Requests $requests,
		private Deciders $deciders,
		private ?string $userId,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Who the caller is to «Recursos Humanos»: their account, the roles their cargo groups and the
	 * person records give, and their own record with this year's Balances and Available days, Years
	 * of service and who decides their Requests («Mis días»)
	 *
	 * @return DataResponse<Http::STATUS_OK, array{uid: string, roles: array{hrOfficer: bool, director: bool, subdirector: bool, jefatura: bool}, person: ?RecursosHumanosPerson}, array{}>
	 *
	 * 200: The caller, their roles and their record, null until HR completes it
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: self::READ_LIMIT, period: self::PERIOD)]
	#[ApiRoute(verb: 'GET', url: '/api/v1/me')]
	public function me(): DataResponse {
		$uid = (string)$this->userId;
		return new DataResponse(['uid' => $uid, 'roles' => $this->access->rolesOf($uid), 'person' => $this->people->mine($uid)]);
	}

	/**
	 * «Personal»: every account with its person record, sorted by name, those without one marked
	 * incomplete («Falta completar»); anyone but an HR officer gets only their own
	 *
	 * @return DataResponse<Http::STATUS_OK, array{people: list<RecursosHumanosPersonItem>}, array{}>
	 *
	 * 200: The people the caller may see
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: self::READ_LIMIT, period: self::PERIOD)]
	#[ApiRoute(verb: 'GET', url: '/api/v1/people')]
	public function people(): DataResponse {
		return new DataResponse(['people' => $this->people->listFor((string)$this->userId)]);
	}

	/**
	 * One person's record, by RUT, with their Regime lines and the record's trail: the caller's
	 * own, or anyone's for an HR officer
	 *
	 * @param string $rut The person's RUT with its verifier digit, with or without dots
	 * @return DataResponse<Http::STATUS_OK, array{person: RecursosHumanosPerson}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: The record
	 * 404: No record has that RUT, or it is not the caller's to read
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: self::READ_LIMIT, period: self::PERIOD)]
	#[ApiRoute(verb: 'GET', url: '/api/v1/people/{rut}')]
	public function person(string $rut): DataResponse {
		return $this->answer(fn (): array => ['person' => $this->people->one((string)$this->userId, $rut)]);
	}

	/**
	 * «Completar ficha»: an HR officer writes the record of an account that has none, with its first Regime line
	 *
	 * @param string $uid The account's uid
	 * @param string $rut The RUT with its verifier digit, with or without dots; unique in the establishment
	 * @param string $givenNames The legal nombres
	 * @param string $surnames The legal apellidos
	 * @param string $startDate The start date, YYYY-MM-DD
	 * @param 'estatutoAps'|'honorarios' $regime The Regime
	 * @param string $regimeFrom The date the Regime applies from, YYYY-MM-DD
	 * @param int $priorMonths Prior public service, in months
	 * @param bool $shift Whether the person works shifts
	 * @param string|null $jefatura The Jefatura's uid; none sends the person's Requests to the Director
	 * @return DataResponse<Http::STATUS_CREATED, array{person: RecursosHumanosPerson}, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_UNPROCESSABLE_ENTITY, array{message: string}, array{}>
	 *
	 * 201: The completed record
	 * 403: The caller is not an HR officer
	 * 404: No such account
	 * 422: A field is missing or wrong, the Regime starts after the start date, the RUT is someone else's, or the account already has its record
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: self::WRITE_LIMIT, period: self::PERIOD)]
	#[ApiRoute(verb: 'POST', url: '/api/v1/people')]
	public function completeRecord(string $uid, string $rut, string $givenNames, string $surnames, string $startDate, string $regime,
		string $regimeFrom, int $priorMonths = 0, bool $shift = false, ?string $jefatura = null): DataResponse {
		return $this->answer(fn (): array => ['person' => $this->people->complete((string)$this->userId, $uid, $rut, $givenNames, $surnames,
			$startDate, $regime, $regimeFrom, $priorMonths, $shift, $jefatura)], Http::STATUS_CREATED);
	}

	/**
	 * «Cambiar régimen»: a new dated Regime line with its reason; the earlier lines stay
	 *
	 * @param string $rut The person's RUT with its verifier digit, with or without dots
	 * @param 'estatutoAps'|'honorarios' $regime The new Regime
	 * @param string $from The date it applies from, YYYY-MM-DD
	 * @param string $reason Why, for the record's trail
	 * @return DataResponse<Http::STATUS_CREATED, array{person: RecursosHumanosPerson}, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_UNPROCESSABLE_ENTITY, array{message: string}, array{}>
	 *
	 * 201: The record with its new line
	 * 403: The caller is not an HR officer
	 * 404: No record has that RUT
	 * 422: A field is missing or wrong, the date is before the first line, the Regime is already the one in force then, or a line already starts on that date
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: self::WRITE_LIMIT, period: self::PERIOD)]
	#[ApiRoute(verb: 'POST', url: '/api/v1/people/{rut}/regimes')]
	public function changeRegime(string $rut, string $regime, string $from, string $reason): DataResponse {
		return $this->answer(fn (): array => ['person' => $this->people->changeRegime((string)$this->userId, $rut, $regime, $from, $reason)],
			Http::STATUS_CREATED);
	}

	/**
	 * «Completar ficha» for a mid-year joiner: an HR officer sets this year's Administrative days,
	 * which the establishment rule leaves to HR
	 *
	 * @param string $rut The person's RUT with its verifier digit, with or without dots
	 * @param float $days From 0 to the year's Administrative days, in whole or half days
	 * @return DataResponse<Http::STATUS_CREATED, array{person: RecursosHumanosPerson}, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_UNPROCESSABLE_ENTITY, array{message: string}, array{}>
	 *
	 * 201: The record with its Administrative days granted
	 * 403: The caller is not an HR officer
	 * 404: No record has that RUT
	 * 422: The person is not a mid-year joiner whose days HR sets, they are already set, or the number is out of range
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: self::WRITE_LIMIT, period: self::PERIOD)]
	#[ApiRoute(verb: 'POST', url: '/api/v1/people/{rut}/administrative-days')]
	public function setAdministrativeDays(string $rut, float $days): DataResponse {
		return $this->answer(fn (): array => ['person' => $this->people->setAdministrativeDays((string)$this->userId, $rut, $days)],
			Http::STATUS_CREATED);
	}

	/**
	 * Normativa: every Rule version the app applies, with its value, legal basis and the day it takes effect
	 *
	 * @return DataResponse<Http::STATUS_OK, array{rules: list<RecursosHumanosRuleVersion>}, array{}>
	 *
	 * 200: The Rule versions, by title
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: self::READ_LIMIT, period: self::PERIOD)]
	#[ApiRoute(verb: 'GET', url: '/api/v1/rules')]
	public function rules(): DataResponse {
		return new DataResponse(['rules' => $this->rules->all()]);
	}

	/**
	 * The Public holidays of a year: the national list the app ships, by date
	 *
	 * @param int $year The year
	 * @return DataResponse<Http::STATUS_OK, array{holidays: list<RecursosHumanosHoliday>}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: The year's holidays
	 * 404: The app has no list for that year yet
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: self::READ_LIMIT, period: self::PERIOD)]
	#[ApiRoute(verb: 'GET', url: '/api/v1/holidays')]
	public function holidays(int $year): DataResponse {
		return $this->answer(fn (): array => ['holidays' => $this->workingDays->holidays($year)]);
	}

	/**
	 * A Request's count before it is sent, as «Nueva solicitud» shows it while the dates change: the
	 * days counted, the days skipped with their reason and holiday name, and the days per year.
	 * With a Leave type it counts in that type's unit and adds, for the caller, what a send would
	 * refuse on and Available in each year of the range; it writes nothing
	 *
	 * @param string $from The first day, YYYY-MM-DD
	 * @param string $to The last day, YYYY-MM-DD, counted too
	 * @param 'habiles'|'corridos'|null $unit días hábiles skip Saturdays, Sundays and Public holidays; días corridos count every day. Required without a Leave type; with one, it must be that type's
	 * @param 'annualLeave'|'administrativeDay'|null $leaveType The Leave type asked for, which sets the unit
	 * @return DataResponse<Http::STATUS_OK, RecursosHumanosPreview, array{}>|DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, array{message: string}, array{}>
	 *
	 * 200: The count and its unit, with the reasons a send would be refused (none: it may be sent)
	 * 422: A date, the unit or the Leave type is missing or wrong, the unit is not the Leave type's, the range ends before it starts, or, in días hábiles, the app has no Public holiday list for one of its years
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: self::READ_LIMIT, period: self::PERIOD)]
	#[ApiRoute(verb: 'GET', url: '/api/v1/requests/preview')]
	public function previewRequest(string $from, string $to, ?string $unit = null, ?string $leaveType = null): DataResponse {
		return $this->answer(fn (): array => $this->requests->preview((string)$this->userId, $from, $to, $unit, $leaveType));
	}

	/**
	 * «Mis solicitudes»: the caller's own Requests, newest first, each with its trail and the Rule
	 * versions it was counted with
	 *
	 * @return DataResponse<Http::STATUS_OK, array{requests: list<RecursosHumanosRequest>}, array{}>
	 *
	 * 200: The caller's Requests
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: self::READ_LIMIT, period: self::PERIOD)]
	#[ApiRoute(verb: 'GET', url: '/api/v1/requests')]
	public function myRequests(): DataResponse {
		return new DataResponse(['requests' => $this->requests->mine((string)$this->userId)]);
	}

	/**
	 * «Nueva solicitud»: the caller asks for days of a Leave type; the Request waits for its
	 * decider, who is notified. It is refused when the caller's record is incomplete, they are on
	 * honorarios, Annual leave has not started for them, nobody can decide it, it counts no day,
	 * it overlaps another Request in flight, or it is above Available in one of its years
	 *
	 * @param 'annualLeave'|'administrativeDay' $leaveType The Leave type
	 * @param string $from The first day, YYYY-MM-DD
	 * @param string $to The last day, YYYY-MM-DD, counted too
	 * @param string $comment For the decider, up to 1000 characters
	 * @return DataResponse<Http::STATUS_CREATED, array{request: RecursosHumanosRequest}, array{}>|DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, array{message: string}, array{}>
	 *
	 * 201: The pending Request
	 * 422: A field is missing or wrong, or a check refuses it, with every reason
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: self::WRITE_LIMIT, period: self::PERIOD)]
	#[ApiRoute(verb: 'POST', url: '/api/v1/requests')]
	public function askRequest(string $leaveType, string $from, string $to, string $comment = ''): DataResponse {
		return $this->answer(fn (): array => ['request' => $this->requests->ask((string)$this->userId, $leaveType, $from, $to, $comment)],
			Http::STATUS_CREATED);
	}

	/**
	 * One Request with its trail and the Rule versions it was counted with: for the person, its
	 * decider, whoever acted on it, and HR officers
	 *
	 * @param int $id The Request's id
	 * @return DataResponse<Http::STATUS_OK, array{request: RecursosHumanosRequest}, array{}>|DataResponse<Http::STATUS_NOT_FOUND, array{message: string}, array{}>
	 *
	 * 200: The Request
	 * 404: No such Request, or it is not the caller's to read
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: self::READ_LIMIT, period: self::PERIOD)]
	#[ApiRoute(verb: 'GET', url: '/api/v1/requests/{id}', requirements: ['id' => '\d+'])]
	public function request(int $id): DataResponse {
		return $this->answer(fn (): array => ['request' => $this->requests->one((string)$this->userId, $id)]);
	}

	/**
	 * «Por decidir»: the pending Requests the caller decides, oldest first, each with Available
	 * before it, and the Delegation in force
	 *
	 * @return DataResponse<Http::STATUS_OK, array{requests: list<RecursosHumanosRequest>, delegation: ?RecursosHumanosDelegation}, array{}>
	 *
	 * 200: The caller's queue, empty when they decide nobody's Requests
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: self::READ_LIMIT, period: self::PERIOD)]
	#[ApiRoute(verb: 'GET', url: '/api/v1/decisions')]
	public function decisions(): DataResponse {
		return new DataResponse($this->requests->toDecide((string)$this->userId));
	}

	/**
	 * «Aprobar»: the decider approves a pending Request, recorded in their capacity and, for a
	 * Jefatura, by the Delegation in force; it then waits for HR to register it, and the person is
	 * notified
	 *
	 * @param int $id The Request's id
	 * @return DataResponse<Http::STATUS_CREATED, array{request: RecursosHumanosRequest}, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_UNPROCESSABLE_ENTITY, array{message: string}, array{}>
	 *
	 * 201: The approved Request
	 * 403: The caller may read it but does not decide it
	 * 404: No such Request, or it is not the caller's to read
	 * 422: It is already decided, or a Jefatura decides and HR has recorded no Delegation
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: self::WRITE_LIMIT, period: self::PERIOD)]
	#[ApiRoute(verb: 'POST', url: '/api/v1/requests/{id}/approval', requirements: ['id' => '\d+'])]
	public function approveRequest(int $id): DataResponse {
		return $this->answer(fn (): array => ['request' => $this->requests->approve((string)$this->userId, $id)], Http::STATUS_CREATED);
	}

	/**
	 * «Rechazar»: the decider rejects a pending Request, always with a written reason the person
	 * reads; Annual leave too, by the establishment rule that allows it, which the Request then
	 * cites
	 *
	 * @param int $id The Request's id
	 * @param string $reason Why, up to 1000 characters
	 * @return DataResponse<Http::STATUS_CREATED, array{request: RecursosHumanosRequest}, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_UNPROCESSABLE_ENTITY, array{message: string}, array{}>
	 *
	 * 201: The rejected Request
	 * 403: The caller may read it but does not decide it
	 * 404: No such Request, or it is not the caller's to read
	 * 422: The reason is missing or too long, it is already decided, a Jefatura decides and HR has recorded no Delegation, or the Rule version in force forbids rejecting Annual leave
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: self::WRITE_LIMIT, period: self::PERIOD)]
	#[ApiRoute(verb: 'POST', url: '/api/v1/requests/{id}/rejection', requirements: ['id' => '\d+'])]
	public function rejectRequest(int $id, string $reason): DataResponse {
		return $this->answer(fn (): array => ['request' => $this->requests->reject((string)$this->userId, $id, $reason)], Http::STATUS_CREATED);
	}

	/**
	 * «Ajustes › Delegación»: the Director's Delegation in force, which lets the Jefaturas decide
	 * on the Director's behalf
	 *
	 * @return DataResponse<Http::STATUS_OK, array{delegation: ?RecursosHumanosDelegation}, array{}>
	 *
	 * 200: The Delegation in force, null until HR records one
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: self::READ_LIMIT, period: self::PERIOD)]
	#[ApiRoute(verb: 'GET', url: '/api/v1/settings/delegation')]
	public function delegation(): DataResponse {
		$delegation = $this->deciders->delegation();
		return new DataResponse(['delegation' => $delegation === null ? null : $this->deciders->delegationView($delegation)]);
	}

	/**
	 * «Guardar delegación»: an HR officer records the Delegation in force by its resolution's number
	 * and date; the earlier ones stay, and the decisions taken by them keep citing them
	 *
	 * @param string $number The resolution's number, up to 64 characters
	 * @param string $date The resolution's date, YYYY-MM-DD, up to today
	 * @return DataResponse<Http::STATUS_CREATED, array{delegation: RecursosHumanosDelegation}, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_UNPROCESSABLE_ENTITY, array{message: string}, array{}>
	 *
	 * 201: The Delegation now in force
	 * 403: The caller is not an HR officer
	 * 422: The number is missing or too long, or the date is not a day up to today
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: self::WRITE_LIMIT, period: self::PERIOD)]
	#[ApiRoute(verb: 'POST', url: '/api/v1/settings/delegation')]
	public function recordDelegation(string $number, string $date): DataResponse {
		return $this->answer(fn (): array => ['delegation' => $this->deciders->delegationView($this->deciders->record((string)$this->userId, $number, $date))],
			Http::STATUS_CREATED);
	}

	/**
	 * What $action answers, or the refusal it threw as aps-common's `{message}`.
	 *
	 * @param callable(): array<string, mixed> $action
	 * @return DataResponse<Http::STATUS_OK|Http::STATUS_CREATED, array<string, mixed>, array{}>|DataResponse<Http::STATUS_FORBIDDEN|Http::STATUS_NOT_FOUND|Http::STATUS_UNPROCESSABLE_ENTITY, array{message: string}, array{}>
	 */
	private function answer(callable $action, int $status = Http::STATUS_OK): DataResponse {
		try {
			return new DataResponse($action(), $status);
		} catch (Forbidden $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_FORBIDDEN);
		} catch (DoesNotExistException $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_NOT_FOUND);
		} catch (Refused $e) {
			return new DataResponse(['message' => $e->getMessage()], Http::STATUS_UNPROCESSABLE_ENTITY);
		}
	}
}
