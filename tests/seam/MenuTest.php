<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Tests\Seam;

/** «Recursos Humanos» in the suite menu, for everyone: every person asks for days and sees their own. */
class MenuTest extends SeamTestCase {
	public function testTheMenuOffersRecursosHumanosToEveryone(): void {
		$staff = self::person();

		$entries = self::ocs($staff, 'GET', '/core/navigation/apps')['data'];

		self::assertContains('recursos_humanos', array_column($entries, 'id'));
	}
}
