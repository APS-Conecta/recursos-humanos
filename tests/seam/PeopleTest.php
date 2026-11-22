<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Tests\Seam;

/**
 * «Personal» and «Completar ficha» (recursos-humanos#3): HR completes each person's record, keyed
 * by the RUT (ADR 0006); an account without one is «Falta completar»; a Regime change is a new
 * dated line; only HR writes, and nobody else reads anyone's record but their own.
 */
class PeopleTest extends SeamTestCase {
	private const PEOPLE = '/apps/recursos_humanos/api/v1/people';
	private const HR = 'role-recursos-humanos';

	private static string $officer;

	public static function setUpBeforeClass(): void {
		self::$officer = self::person();
		self::joins(self::$officer, self::HR);
	}

	public function testANewAccountAppearsInPersonalAsFaltaCompletar(): void {
		$newcomer = self::person();

		$entry = self::entry(self::$officer, $newcomer);

		self::assertSame(['uid' => $newcomer, 'name' => $newcomer, 'cargos' => [], 'complete' => false, 'rut' => null,
			'givenNames' => null, 'surnames' => null, 'regime' => null, 'jefatura' => null], $entry);
		self::assertSame([self::HR], self::entry(self::$officer, self::$officer)['cargos'], 'a cargo is what «Personal» searches by');
	}

	public function testAValidRutIsSavedWithNombreYApellidos(): void {
		$staff = self::person();
		$jefatura = self::person();
		$rut = self::rut();

		$answer = self::ocs(self::$officer, 'POST', self::PEOPLE, self::record($staff, [
			'rut' => self::dotted($rut), 'givenNames' => ' María  José ', 'jefatura' => $jefatura, 'shift' => true,
		]));

		self::assertSame(201, $answer['status'], json_encode($answer['data']));
		$person = self::ocs(self::$officer, 'GET', self::PEOPLE . '/' . $rut)['data']['person'];
		$expected = ['complete' => true, 'rut' => $rut, 'givenNames' => 'María José', 'surnames' => 'Soto Ibarra', 'regime' => 'estatutoAps',
			'jefatura' => ['uid' => $jefatura, 'name' => $jefatura], 'startDate' => '2019-03-04', 'priorMonths' => 26, 'shift' => true];
		self::assertSame($expected, array_intersect_key($person, $expected));
		self::assertSame([['regime' => 'estatutoAps', 'from' => '2019-03-04']], $person['regimes']);
		self::assertSame([['kind' => 'completed', 'actor' => ['uid' => self::$officer, 'name' => self::$officer], 'regime' => 'estatutoAps',
			'from' => '2019-03-04', 'reason' => null]], array_map(static fn (array $step): array => array_diff_key($step, ['at' => 0]), $person['trail']));
		self::assertTrue(self::entry(self::$officer, $staff)['complete'], 'a complete record is no longer «Falta completar»');
	}

	public function testPeopleAreAddressableByRut(): void {
		$staff = self::person();
		$rut = self::rut();
		self::ocs(self::$officer, 'POST', self::PEOPLE, self::record($staff, ['rut' => $rut]));

		foreach ([$rut, self::dotted($rut), strtolower(str_replace('-', '', $rut))] as $typed) {
			$answer = self::ocs(self::$officer, 'GET', self::PEOPLE . '/' . rawurlencode($typed));
			self::assertSame(200, $answer['status'], $typed);
			self::assertSame($staff, $answer['data']['person']['uid'], $typed);
		}
		self::assertSame(404, self::ocs(self::$officer, 'GET', self::PEOPLE . '/' . self::rut())['status'], 'a RUT nobody has');
	}

	public function testAWrongVerifierDigitIsRefusedWithTheReason(): void {
		$staff = self::person();
		[$body, $verifier] = explode('-', self::rut());
		$wrong = $body . '-' . ($verifier === '0' ? '1' : '0');

		$answer = self::ocs(self::$officer, 'POST', self::PEOPLE, self::record($staff, ['rut' => $wrong]));

		self::assertSame(422, $answer['status']);
		self::assertSame('El dígito verificador no corresponde a ese RUT. Revísalo en el documento.', $answer['data']['message']);
		self::assertFalse(self::entry(self::$officer, $staff)['complete'], 'nothing is saved');
	}

	public function testARutAlreadyUsedIsRefusedWithTheReason(): void {
		$first = self::person();
		$second = self::person();
		$rut = self::rut();
		self::ocs(self::$officer, 'POST', self::PEOPLE, self::record($first, ['rut' => $rut]));

		$answer = self::ocs(self::$officer, 'POST', self::PEOPLE, self::record($second, ['rut' => self::dotted($rut)]));

		self::assertSame(422, $answer['status']);
		self::assertSame('Ese RUT ya es de otra persona del establecimiento.', $answer['data']['message']);
		self::assertFalse(self::entry(self::$officer, $second)['complete']);
	}

	/** @return array<string, array{array<string, mixed>, string}> */
	public static function refusedRecords(): array {
		return [
			'no RUT' => [['rut' => ''], 'Escribe el RUT con su dígito verificador, por ejemplo 12.345.678-5.'],
			'no apellidos' => [['surnames' => '  '], 'Escribe los nombres y los apellidos.'],
			'no start date' => [['startDate' => '2019-02-30'], 'Elige la fecha de ingreso.'],
			'negative prior service' => [['priorMonths' => -1], 'El servicio público anterior va en meses: un número entero desde 0.'],
			'no Regime' => [['regime' => 'contrata'], 'Elige el régimen: Estatuto APS u Honorarios / sin derecho.'],
			'no Regime date' => [['regimeFrom' => ''], 'Elige la fecha desde la que rige el régimen.'],
			'a Regime from after the start date' => [['regimeFrom' => '2019-03-05'], 'El régimen debe regir desde la fecha de ingreso o antes.'],
			'an unknown Jefatura' => [['jefatura' => 'nobody-' . bin2hex(random_bytes(4))], 'Esa jefatura no es una cuenta del establecimiento.'],
		];
	}

	/**
	 * @dataProvider refusedRecords
	 * @param array<string, mixed> $change
	 */
	public function testAnIncompleteOrWrongRecordIsRefusedWithTheReason(array $change, string $reason): void {
		$staff = self::person();

		$answer = self::ocs(self::$officer, 'POST', self::PEOPLE, self::record($staff, $change));

		self::assertSame([422, $reason], [$answer['status'], $answer['data']['message'] ?? null]);
	}

	public function testAPersonIsNotTheirOwnJefatura(): void {
		$staff = self::person();

		$answer = self::ocs(self::$officer, 'POST', self::PEOPLE, self::record($staff, ['jefatura' => $staff]));

		self::assertSame([422, 'Una persona no puede ser su propia jefatura.'], [$answer['status'], $answer['data']['message']]);
	}

	public function testARecordIsCompletedOnce(): void {
		$staff = self::person();
		self::ocs(self::$officer, 'POST', self::PEOPLE, self::record($staff));

		$answer = self::ocs(self::$officer, 'POST', self::PEOPLE, self::record($staff));

		self::assertSame([422, 'Esta persona ya tiene su ficha completa.'], [$answer['status'], $answer['data']['message']]);
	}

	public function testARegimeChangeIsANewDatedLineAndTheEarlierLinesStay(): void {
		$staff = self::person();
		$rut = self::rut();
		// The mockup's «Javiera Morales»: on honorarios before her start date under the Estatuto APS.
		self::ocs(self::$officer, 'POST', self::PEOPLE, self::record($staff,
			['rut' => $rut, 'startDate' => '2025-07-01', 'regime' => 'honorarios', 'regimeFrom' => '2024-01-02']));

		$answer = self::ocs(self::$officer, 'POST', self::PEOPLE . "/$rut/regimes",
			['regime' => 'estatutoAps', 'from' => '2025-07-01', 'reason' => 'Decreto de nombramiento N° 845.']);

		self::assertSame(201, $answer['status'], json_encode($answer['data']));
		$person = self::ocs(self::$officer, 'GET', self::PEOPLE . '/' . $rut)['data']['person'];
		self::assertSame([['regime' => 'honorarios', 'from' => '2024-01-02'], ['regime' => 'estatutoAps', 'from' => '2025-07-01']], $person['regimes']);
		self::assertSame('estatutoAps', $person['regime'], 'the line in force today');
		self::assertSame(['kind' => 'regime', 'actor' => ['uid' => self::$officer, 'name' => self::$officer], 'regime' => 'estatutoAps',
			'from' => '2025-07-01', 'reason' => 'Decreto de nombramiento N° 845.'], array_diff_key($person['trail'][0], ['at' => 0]), 'newest first');
		self::assertSame('completed', $person['trail'][1]['kind']);
	}

	public function testARegimeLineInTheFutureIsNotYetInForce(): void {
		$staff = self::person();
		$rut = self::rut();
		self::ocs(self::$officer, 'POST', self::PEOPLE, self::record($staff, ['rut' => $rut, 'regime' => 'honorarios']));

		self::ocs(self::$officer, 'POST', self::PEOPLE . "/$rut/regimes", ['regime' => 'estatutoAps', 'from' => '2099-01-01', 'reason' => 'Nombramiento futuro.']);

		self::assertSame('honorarios', self::entry(self::$officer, $staff)['regime']);
	}

	public function testARegimeChangeNeedsAReasonADifferentRegimeAndAnAvailableDate(): void {
		$staff = self::person();
		$rut = self::rut();
		self::ocs(self::$officer, 'POST', self::PEOPLE, self::record($staff, ['rut' => $rut]));
		$path = self::PEOPLE . "/$rut/regimes";

		$noReason = self::ocs(self::$officer, 'POST', $path, ['regime' => 'honorarios', 'from' => '2025-01-01', 'reason' => ' ']);
		$sameDate = self::ocs(self::$officer, 'POST', $path, ['regime' => 'honorarios', 'from' => '2019-03-04', 'reason' => 'Cambio de contrato.']);
		$sameRegime = self::ocs(self::$officer, 'POST', $path, ['regime' => 'estatutoAps', 'from' => '2025-01-01', 'reason' => 'Cambio de contrato.']);
		$beforeFirst = self::ocs(self::$officer, 'POST', $path, ['regime' => 'honorarios', 'from' => '2019-03-03', 'reason' => 'Cambio de contrato.']);

		self::assertSame([422, 'Escribe el motivo: queda en el recorrido de la ficha.'], [$noReason['status'], $noReason['data']['message']]);
		self::assertSame([422, 'Ya hay un régimen desde esa fecha.'], [$sameDate['status'], $sameDate['data']['message']]);
		self::assertSame([422, 'Ese ya es su régimen en esa fecha.'], [$sameRegime['status'], $sameRegime['data']['message']]);
		self::assertSame([422, 'Esa fecha es anterior al primer régimen de la ficha.'], [$beforeFirst['status'], $beforeFirst['data']['message']], 'correcting it is the Recount\'s');
		self::assertCount(1, self::ocs(self::$officer, 'GET', self::PEOPLE . '/' . $rut)['data']['person']['regimes']);
	}

	public function testAStaffMemberReadsOnlyTheirOwnRecord(): void {
		$staff = self::person();
		$staffRut = self::rut();
		$colleague = self::person();
		$colleagueRut = self::rut();
		self::ocs(self::$officer, 'POST', self::PEOPLE, self::record($staff, ['rut' => $staffRut]));
		self::ocs(self::$officer, 'POST', self::PEOPLE, self::record($colleague, ['rut' => $colleagueRut]));

		self::assertSame([$staff], array_column(self::ocs($staff, 'GET', self::PEOPLE)['data']['people'], 'uid'));
		self::assertSame(200, self::ocs($staff, 'GET', self::PEOPLE . '/' . $staffRut)['status']);
		self::assertSame(404, self::ocs($staff, 'GET', self::PEOPLE . '/' . $colleagueRut)['status'], 'the same answer as a RUT nobody has');
	}

	public function testOnlyHrWrites(): void {
		$staff = self::person();
		$rut = self::rut();
		self::ocs(self::$officer, 'POST', self::PEOPLE, self::record($staff, ['rut' => $rut]));
		$director = self::person();
		self::joins($director, 'role-director-cesfam');
		$newcomer = self::person();

		foreach ([$staff, $director] as $caller) {
			$complete = self::ocs($caller, 'POST', self::PEOPLE, self::record($newcomer));
			$regime = self::ocs($caller, 'POST', self::PEOPLE . "/$rut/regimes", ['regime' => 'honorarios', 'from' => '2025-01-01', 'reason' => 'Cambio.']);

			self::assertSame([403, 'Solo Recursos Humanos completa y cambia las fichas del personal.'], [$complete['status'], $complete['data']['message']], $caller);
			self::assertSame(403, $regime['status'], $caller);
		}
		self::assertFalse(self::entry(self::$officer, $newcomer)['complete']);
		self::assertCount(1, self::ocs(self::$officer, 'GET', self::PEOPLE . '/' . $rut)['data']['person']['regimes']);
	}

	public function testNobodyReadsPeopleWithoutSigningIn(): void {
		self::assertSame(401, self::ocs(null, 'GET', self::PEOPLE)['status']);
	}

	/** @return array<string, mixed> the person's entry in «Personal», as $viewer sees it */
	private static function entry(string $viewer, string $person): array {
		foreach (self::ocs($viewer, 'GET', self::PEOPLE)['data']['people'] as $entry) {
			if ($entry['uid'] === $person) {
				return $entry;
			}
		}
		self::fail("$person is not in «Personal»");
	}

	/** 12345678-5 as people write it: 12.345.678-5. */
	private static function dotted(string $rut): string {
		[$body, $verifier] = explode('-', $rut);
		return number_format((int)$body, 0, ',', '.') . '-' . $verifier;
	}
}
