<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Access;

use OCP\IGroupManager;
use OCP\IUser;

/**
 * Who a person is to «Recursos Humanos» (recursos-humanos#1, Access). The roles that come from
 * Nextcloud groups are read here, from the cargo groups the suite's Provisionador creates
 * (gestion-aio teams.py): the HR officer, the Director and the Subdirector. Jefatura, Stand-in and
 * the Overview groups come from the app's own data in later tickets, and every page and API check
 * goes through this class. A person's cargos, which «Personal» shows and searches, are read here too.
 */
class Access {
	public const HR_OFFICER_GROUP = 'role-recursos-humanos';
	public const DIRECTOR_GROUP = 'role-director-cesfam';
	public const SUBDIRECTOR_GROUP = 'role-subdirector-jefe-tecnico';
	/** Every cargo group the Provisionador creates is role-<cargo>. */
	private const CARGO_GROUP = 'role-';

	public function __construct(
		private IGroupManager $groups,
	) {
	}

	/** @return array{hrOfficer: bool, director: bool, subdirector: bool} */
	public function rolesOf(string $uid): array {
		return [
			'hrOfficer' => $this->writes($uid),
			'director' => $this->groups->isInGroup($uid, self::DIRECTOR_GROUP),
			'subdirector' => $this->groups->isInGroup($uid, self::SUBDIRECTOR_GROUP),
		];
	}

	/** Whether $viewer reads every person's record, not only their own: the HR officers. */
	public function readsEveryone(string $viewer): bool {
		return $this->writes($viewer);
	}

	/** Whether $viewer writes person records: only HR does. */
	public function writes(string $viewer): bool {
		return $this->groups->isInGroup($viewer, self::HR_OFFICER_GROUP);
	}

	/** @return list<string> the names of the person's cargo groups, sorted */
	public function cargosOf(IUser $user): array {
		$cargos = [];
		foreach ($this->groups->getUserGroups($user) as $group) {
			if (str_starts_with($group->getGID(), self::CARGO_GROUP)) {
				$cargos[] = $group->getDisplayName();
			}
		}
		sort($cargos);
		return $cargos;
	}
}
