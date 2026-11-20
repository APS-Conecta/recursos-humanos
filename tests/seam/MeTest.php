<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Tests\Seam;

/**
 * `GET me`: who the caller is to «Recursos Humanos». The roles come from the cargo groups the
 * suite's Provisionador creates (gestion-aio teams.py): an HR officer is a member of Recursos
 * Humanos' cargo group, the Director and the Subdirector of theirs (CONTEXT.md).
 */
class MeTest extends SeamTestCase {
	private const NO_ROLE = ['hrOfficer' => false, 'director' => false, 'subdirector' => false];

	public function testAStaffMemberHasNoRole(): void {
		$staff = self::person();

		$answer = self::ocs($staff, 'GET', '/apps/recursos_humanos/api/v1/me');

		self::assertSame(200, $answer['status']);
		self::assertSame(['uid' => $staff, 'roles' => self::NO_ROLE], $answer['data']);
	}

	public function testRecursosHumanosCargoMakesAnHrOfficer(): void {
		$officer = self::person();
		self::joins($officer, 'role-recursos-humanos');

		$roles = self::ocs($officer, 'GET', '/apps/recursos_humanos/api/v1/me')['data']['roles'];

		self::assertSame(array_replace(self::NO_ROLE, ['hrOfficer' => true]), $roles);
	}

	public function testTheDirectorAndTheSubdirectorComeFromTheirCargoGroups(): void {
		$director = self::person();
		self::joins($director, 'role-director-cesfam');
		$subdirector = self::person();
		self::joins($subdirector, 'role-subdirector-jefe-tecnico');

		self::assertSame(array_replace(self::NO_ROLE, ['director' => true]), self::ocs($director, 'GET', '/apps/recursos_humanos/api/v1/me')['data']['roles']);
		self::assertSame(array_replace(self::NO_ROLE, ['subdirector' => true]), self::ocs($subdirector, 'GET', '/apps/recursos_humanos/api/v1/me')['data']['roles']);
	}

	public function testNobodyReadsItWithoutSigningIn(): void {
		self::assertSame(401, self::ocs(null, 'GET', '/apps/recursos_humanos/api/v1/me')['status']);
	}
}
