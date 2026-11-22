<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Controller;

use OCA\RecursosHumanos\Access\Access;
use OCA\RecursosHumanos\AppInfo\Application;
use OCA\RecursosHumanos\Forbidden;
use OCA\RecursosHumanos\People\People;
use OCA\RecursosHumanos\Refused;
use OCA\RecursosHumanos\ResponseDefinitions;
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
 * @psalm-import-type RecursosHumanosCount from ResponseDefinitions
 * @psalm-import-type RecursosHumanosHoliday from ResponseDefinitions
 * @psalm-import-type RecursosHumanosPerson from ResponseDefinitions
 * @psalm-import-type RecursosHumanosPersonItem from ResponseDefinitions
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
		private ?string $userId,
	) {
		parent::__construct(Application::APP_ID, $request);
	}

	/**
	 * Who the caller is to «Recursos Humanos»: their account and the roles their cargo groups give
	 *
	 * @return DataResponse<Http::STATUS_OK, array{uid: string, roles: array{hrOfficer: bool, director: bool, subdirector: bool}}, array{}>
	 *
	 * 200: The caller and their roles
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: self::READ_LIMIT, period: self::PERIOD)]
	#[ApiRoute(verb: 'GET', url: '/api/v1/me')]
	public function me(): DataResponse {
		$uid = (string)$this->userId;
		return new DataResponse(['uid' => $uid, 'roles' => $this->access->rolesOf($uid)]);
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
	 * days counted, the days skipped with their reason and holiday name, and the days per year; it
	 * writes nothing
	 *
	 * @param string $from The first day, YYYY-MM-DD
	 * @param string $to The last day, YYYY-MM-DD, counted too
	 * @param 'habiles'|'corridos' $unit días hábiles skip Saturdays, Sundays and Public holidays; días corridos count every day
	 * @return DataResponse<Http::STATUS_OK, RecursosHumanosCount, array{}>|DataResponse<Http::STATUS_UNPROCESSABLE_ENTITY, array{message: string}, array{}>
	 *
	 * 200: The count
	 * 422: A date or the unit is missing or wrong, the range ends before it starts, or, in días hábiles, the app has no Public holiday list for one of its years
	 */
	#[NoAdminRequired]
	#[UserRateLimit(limit: self::READ_LIMIT, period: self::PERIOD)]
	#[ApiRoute(verb: 'GET', url: '/api/v1/requests/preview')]
	public function previewRequest(string $from, string $to, string $unit): DataResponse {
		return $this->answer(fn (): array => $this->workingDays->count($from, $to, $unit));
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
