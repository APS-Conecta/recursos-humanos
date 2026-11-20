<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Access;

use OCP\IGroupManager;

/**
 * Who a person is to «Recursos Humanos» (recursos-humanos#1, Access). The roles that come from
 * Nextcloud groups are read here, from the cargo groups the suite's Provisionador creates
 * (gestion-aio teams.py): the HR officer, the Director and the Subdirector. Jefatura, Stand-in and
 * the Overview groups come from the app's own data in later tickets, and every page and API check
 * goes through this class.
 */
class Access {
	public const HR_OFFICER_GROUP = 'role-recursos-humanos';
	public const DIRECTOR_GROUP = 'role-director-cesfam';
	public const SUBDIRECTOR_GROUP = 'role-subdirector-jefe-tecnico';

	public function __construct(
		private IGroupManager $groups,
	) {
	}

	/** @return array{hrOfficer: bool, director: bool, subdirector: bool} */
	public function rolesOf(string $uid): array {
		return [
			'hrOfficer' => $this->groups->isInGroup($uid, self::HR_OFFICER_GROUP),
			'director' => $this->groups->isInGroup($uid, self::DIRECTOR_GROUP),
			'subdirector' => $this->groups->isInGroup($uid, self::SUBDIRECTOR_GROUP),
		];
	}
}
