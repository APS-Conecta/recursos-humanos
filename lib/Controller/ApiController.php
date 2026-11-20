<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Controller;

use OCA\RecursosHumanos\Access\Access;
use OCA\RecursosHumanos\AppInfo\Application;
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
 * who may see what is checked inside each action, through Access.
 */
#[OpenAPI(scope: OpenAPI::SCOPE_DEFAULT)]
class ApiController extends OCSController {
	/** Reads: a ceiling against a runaway client, not a quota. */
	private const READ_LIMIT = 120;
	private const PERIOD = 60;

	public function __construct(
		IRequest $request,
		private Access $access,
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
}
