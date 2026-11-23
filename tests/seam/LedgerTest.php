<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Tests\Seam;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Rule versions, the Ledger and «Mis días» balances (recursos-humanos#5): the law's numbers as
 * dated Rule versions; a complete record under the Estatuto APS receives the year's Annual leave,
 * Administrative days and Training as Movements, and a Balance is their sum (ADR 0001, 0002).
 * The dates are this year's, so the suite reads the same any year it runs.
 */
class LedgerTest extends SeamTestCase {
	private const PEOPLE = '/apps/recursos_humanos/api/v1/people';
	private const HR = 'role-recursos-humanos';

	private static string $officer;
	private static int $year;

	public static function setUpBeforeClass(): void {
		self::$officer = self::person();
		self::joins(self::$officer, self::HR);
		self::$year = (int)(new DateTimeImmutable('now', new DateTimeZone('America/Santiago')))->format('Y');
	}

	public function testNormativaListsEachRuleVersionWithValueBasisAndStartDay(): void {
		$answer = self::ocs(self::person(), 'GET', '/apps/recursos_humanos/api/v1/rules');

		self::assertSame(200, $answer['status']);
		$rules = array_column($answer['data']['rules'], null, 'rule');
		self::assertSame(['rule' => 'annualLeaveDays', 'version' => 1, 'title' => 'Feriado legal: días por año',
			'summary' => '15 días hábiles; 20 con 15 años de servicio; 25 con 20 años. Sube el día en que se cumplen los años.',
			'basis' => 'Ley 19.378 art. 18', 'from' => '1995-04-13', 'establishmentRule' => false], $rules['annualLeaveDays']);
		self::assertTrue($rules['administrativeDaysMidYear']['establishmentRule'], 'an establishment rule is marked as one');
		self::assertSame([], array_diff(['administrativeDays', 'administrativeDaysMidYear', 'annualLeaveDays', 'annualLeaveEligibility', 'trainingDays'], array_keys($rules)),
			'every rule the grants read is listed');
	}

	public function testFourteenYearsElevenMonthsGrant15DaysAndAServiceRiseOf5OnTheDay15AreReached(): void {
		$year = self::$year;
		$start = sprintf('%d-02-01', $year - 15);
		$rut = self::completes(['startDate' => $start, 'regimeFrom' => $start, 'priorMonths' => 0]);

		self::occ('recursos_humanos:grants', "--date=$year-12-31");

		$person = self::read($rut);
		self::assertSame([
			['date' => "$year-02-01", 'kind' => 'serviceRise', 'quantity' => 5, 'reason' => "Cumple 15 años de servicio: el feriado legal $year sube a 20 días hábiles."],
			['date' => "$year-01-01", 'kind' => 'grant', 'quantity' => 15, 'reason' => "Feriado legal $year: 15 días hábiles por 14 años y 11 meses de servicio."],
		], self::lines($person, 'annualLeave', ['date', 'kind', 'quantity', 'reason']));
		self::assertSame(['leaveType' => 'annualLeave', 'unit' => 'habiles', 'balance' => 20, 'granted' => 20, 'available' => 20], self::balance($person, 'annualLeave'));
	}

	public function testEveryBalanceIsTheSumOfMovementsThatSayWhenWhoWhyAndUnderWhichRuleVersion(): void {
		$year = self::$year;
		$rut = self::completes(['startDate' => '2010-06-15', 'regimeFrom' => '2010-06-15', 'priorMonths' => 0]);
		self::occ('recursos_humanos:grants', "--date=$year-12-31");

		$person = self::read($rut);

		foreach (['annualLeave' => 'annualLeaveDays', 'administrativeDay' => 'administrativeDays', 'training' => 'trainingDays'] as $type => $rule) {
			$lines = array_values(array_filter($person['movements'], static fn (array $line): bool => $line['leaveType'] === $type && $line['period'] === $year));
			self::assertNotEmpty($lines, "$type is granted");
			self::assertEquals(array_sum(array_column($lines, 'quantity')), self::balance($person, $type)['balance'], "the $type Balance is the sum of its Movements");
			foreach ($lines as $line) {
				self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $line['date']);
				self::assertNull($line['actor'], 'the app wrote it on its own');
				self::assertNotSame('', $line['reason']);
				self::assertSame($rule, $line['ruleVersion']['rule']);
				self::assertSame(1, $line['ruleVersion']['version']);
			}
		}
		self::assertSame(["Días administrativos $year.", "Capacitación $year."], [
			self::lines($person, 'administrativeDay', ['reason'])[0]['reason'], self::lines($person, 'training', ['reason'])[0]['reason'],
		]);
		self::assertSame([6, 5], [self::balance($person, 'administrativeDay')['balance'], self::balance($person, 'training')['balance']]);
	}

	public function testAGrantIsWrittenOnceHoweverOftenTheGrantsRun(): void {
		$year = self::$year;
		$rut = self::completes(['startDate' => '2015-01-10', 'regimeFrom' => '2015-01-10', 'priorMonths' => 0]);
		self::occ('recursos_humanos:grants', "--date=$year-12-31");
		$before = self::read($rut)['movements'];

		self::occ('recursos_humanos:grants', "--date=$year-12-31");
		self::occ('recursos_humanos:grants');

		self::assertSame($before, self::read($rut)['movements']);
	}

	public function testAnnualLeaveStartsTheDayServicePassesOneYear(): void {
		$next = self::$year + 1;
		$rut = self::completes(['startDate' => sprintf('%d-06-10', self::$year), 'regimeFrom' => sprintf('%d-06-10', self::$year), 'priorMonths' => 0]);
		self::assertSame("$next-06-11", self::read($rut)['days']['annualLeaveFrom']);

		self::occ('recursos_humanos:grants', "--date=$next-06-10");
		self::assertSame([], self::lines(self::read($rut), 'annualLeave', ['date']), 'one year of service is not more than one year');

		self::occ('recursos_humanos:grants', "--date=$next-06-11");
		self::assertSame([['date' => "$next-06-11", 'quantity' => 15, 'reason' => "Feriado legal $next: 15 días hábiles por 1 año de servicio."]],
			self::lines(self::read($rut), 'annualLeave', ['date', 'quantity', 'reason']));
	}

	public function testAMidYearJoinerIsFaltaCompletarUntilHrSetsTheirAdministrativeDays(): void {
		$year = self::$year;
		$staff = self::person();
		$rut = self::completes(['startDate' => "$year-03-02", 'regimeFrom' => "$year-03-02", 'priorMonths' => 0], $staff);

		$person = self::read($rut);
		self::assertFalse($person['complete']);
		self::assertSame(['administrativeDays'], $person['days']['missing']);
		self::assertFalse(self::entry($staff)['complete'], '«Personal» shows «Falta completar»');

		$answer = self::ocs(self::$officer, 'POST', self::PEOPLE . "/$rut/administrative-days", ['days' => 3.5]);

		self::assertSame(201, $answer['status'], json_encode($answer['data']));
		$person = $answer['data']['person'];
		self::assertTrue($person['complete']);
		self::assertSame([], $person['days']['missing']);
		self::assertTrue(self::entry($staff)['complete']);
		$line = self::lines($person, 'administrativeDay', ['quantity', 'actor', 'reason', 'ruleVersion'])[0];
		self::assertSame(['quantity' => 3.5, 'actor' => ['uid' => self::$officer, 'name' => self::$officer],
			'reason' => "Días administrativos $year fijados por Recursos Humanos: ingresó a mitad de año.",
			'ruleVersion' => ['rule' => 'administrativeDaysMidYear', 'version' => 1, 'title' => 'Días administrativos al ingresar a mitad de año']], $line);
		self::assertSame([422, ['message' => "Los días administrativos de $year ya están fijados."]],
			self::answer(self::ocs(self::$officer, 'POST', self::PEOPLE . "/$rut/administrative-days", ['days' => 1])));
	}

	public function testAdministrativeDaysAreSetByHandOnlyByHrForAMidYearJoinerWithinTheYearsDays(): void {
		$year = self::$year;
		$staff = self::person();
		$joiner = self::completes(['startDate' => "$year-04-01", 'regimeFrom' => "$year-04-01", 'priorMonths' => 0], $staff);
		$veteran = self::completes(['startDate' => '2012-08-01', 'regimeFrom' => '2012-08-01']);
		$range = [422, ['message' => 'Los días administrativos van de 0 a 6, en días enteros o medios días.']];

		self::assertSame(403, self::ocs($staff, 'POST', self::PEOPLE . "/$joiner/administrative-days", ['days' => 6])['status'], 'only HR sets them');
		self::assertSame($range, self::answer(self::ocs(self::$officer, 'POST', self::PEOPLE . "/$joiner/administrative-days", ['days' => 6.5])));
		self::assertSame($range, self::answer(self::ocs(self::$officer, 'POST', self::PEOPLE . "/$joiner/administrative-days", ['days' => 1.25])));
		self::assertSame([422, ['message' => "Los días administrativos de $year ya están fijados."]],
			self::answer(self::ocs(self::$officer, 'POST', self::PEOPLE . "/$veteran/administrative-days", ['days' => 2])), 'the law grants theirs');
	}

	public function testHonorariosPeopleGetNoAnnualLeaveOrAdministrativeDays(): void {
		$year = self::$year;
		$rut = self::completes(['startDate' => '2016-05-02', 'regime' => 'honorarios', 'regimeFrom' => '2016-05-02']);
		$joiner = self::completes(['startDate' => "$year-05-04", 'regime' => 'honorarios', 'regimeFrom' => "$year-05-04", 'priorMonths' => 0]);
		self::occ('recursos_humanos:grants', "--date=$year-12-31");

		foreach ([$rut, $joiner] as $honorarios) {
			$person = self::read($honorarios);
			self::assertSame([], $person['movements']);
			self::assertSame([0, 0, 0], array_column($person['days']['balances'], 'balance'));
			self::assertTrue($person['complete'], 'nothing for HR to set');
		}
		self::assertSame([422, ['message' => 'Solo se fijan a mano los días administrativos de quien ingresó a mitad de año.']],
			self::answer(self::ocs(self::$officer, 'POST', self::PEOPLE . "/$joiner/administrative-days", ['days' => 2])));
	}

	public function testARegimeChangeToTheEstatutoApsGrantsWhatIsDueAtOnce(): void {
		$year = self::$year;
		$rut = self::completes(['startDate' => '2021-09-01', 'regime' => 'honorarios', 'regimeFrom' => '2021-09-01']);

		$answer = self::ocs(self::$officer, 'POST', self::PEOPLE . "/$rut/regimes",
			['regime' => 'estatutoAps', 'from' => sprintf('%d-12-01', $year - 1), 'reason' => 'Nombramiento en planta.']);

		self::assertSame(201, $answer['status'], json_encode($answer['data']));
		self::assertSame([15, 6, 5], array_column($answer['data']['person']['days']['balances'], 'balance'), 'under the Estatuto APS on 1 January: the year is granted');
	}

	public function testMovingToTheEstatutoApsMidYearIsJoiningMidYear(): void {
		$year = self::$year;
		$staff = self::person();
		$rut = self::completes(['startDate' => sprintf('%d-03-01', $year - 3), 'regime' => 'honorarios', 'regimeFrom' => sprintf('%d-03-01', $year - 3)], $staff);
		self::ocs(self::$officer, 'POST', self::PEOPLE . "/$rut/regimes", ['regime' => 'estatutoAps', 'from' => "$year-06-01", 'reason' => 'Nombramiento en planta.']);
		self::occ('recursos_humanos:grants', "--date=$year-12-31");

		$person = self::read($rut);
		self::assertSame([15, 0, 5], array_column($person['days']['balances'], 'balance'), 'Annual leave and Training from the day the Estatuto APS starts');
		self::assertSame(["$year-06-01", "$year-06-01"], array_column(array_filter($person['movements'], static fn (array $line): bool => $line['period'] === $year), 'date'));
		self::assertSame(['administrativeDays'], $person['days']['missing']);
		self::assertFalse(self::entry($staff)['complete']);

		$answer = self::ocs(self::$officer, 'POST', self::PEOPLE . "/$rut/administrative-days", ['days' => 3]);
		self::assertSame(201, $answer['status'], json_encode($answer['data']));
		self::assertSame(3, self::balance($answer['data']['person'], 'administrativeDay')['balance']);
	}

	public function testAServiceRiseNeverLandsWithoutTheYearsGrant(): void {
		$year = self::$year;
		$start = sprintf('%d-09-01', $year - 15);
		$rut = self::completes(['startDate' => $start, 'regime' => 'honorarios', 'regimeFrom' => $start, 'priorMonths' => 0]);
		self::ocs(self::$officer, 'POST', self::PEOPLE . "/$rut/regimes", ['regime' => 'estatutoAps', 'from' => "$year-06-01", 'reason' => 'Nombramiento en planta.']);
		self::occ('recursos_humanos:grants', "--date=$year-12-31");

		$person = self::read($rut);
		self::assertSame([
			['date' => "$year-09-01", 'kind' => 'serviceRise', 'quantity' => 5],
			['date' => "$year-06-01", 'kind' => 'grant', 'quantity' => 15],
		], self::lines($person, 'annualLeave', ['date', 'kind', 'quantity']));
		self::assertSame(20, self::balance($person, 'annualLeave')['balance']);
	}

	public function testMisDiasShowsTheCallersOwnBalancesYearsOfServiceAndNextRise(): void {
		$year = self::$year;
		$staff = self::person();
		self::completes(['startDate' => sprintf('%d-01-01', $year - 10), 'regimeFrom' => sprintf('%d-01-01', $year - 10), 'priorMonths' => 24], $staff);

		$days = self::ocs($staff, 'GET', '/apps/recursos_humanos/api/v1/me')['data']['person']['days'];

		self::assertSame($year, $days['year']);
		self::assertGreaterThanOrEqual(144, $days['serviceMonths'], 'ten years here and two before');
		self::assertSame(['on' => sprintf('%d-01-01', $year + 3), 'years' => 15, 'days' => 20], $days['nextRise'], '15 years: 13 here, from the start date');
		self::assertNull($days['annualLeaveFrom']);
		self::assertSame(['leaveType' => 'annualLeave', 'unit' => 'habiles', 'balance' => 15, 'granted' => 15, 'available' => 15], $days['balances'][0]);
	}

	/**
	 * HR completes a record with these fields changed; its RUT.
	 *
	 * @param array<string, mixed> $change
	 */
	private static function completes(array $change, ?string $uid = null): string {
		$record = self::record($uid ?? self::person(), $change);
		$answer = self::ocs(self::$officer, 'POST', self::PEOPLE, $record);
		self::assertSame(201, $answer['status'], json_encode($answer['data']));
		return $record['rut'];
	}

	/** @return array<string, mixed> the record as HR reads it */
	private static function read(string $rut): array {
		return self::ocs(self::$officer, 'GET', self::PEOPLE . "/$rut")['data']['person'];
	}

	/** @return array<string, mixed> the person's entry in «Personal» */
	private static function entry(string $uid): array {
		return array_column(self::ocs(self::$officer, 'GET', self::PEOPLE)['data']['people'], null, 'uid')[$uid];
	}

	/**
	 * The record's Movements of a Leave type, newest first, with only these fields.
	 *
	 * @param array<string, mixed> $person
	 * @param list<string> $fields
	 * @return list<array<string, mixed>>
	 */
	private static function lines(array $person, string $type, array $fields): array {
		$lines = array_filter($person['movements'], static fn (array $line): bool => $line['leaveType'] === $type);
		return array_values(array_map(static fn (array $line): array => array_intersect_key($line, array_flip($fields)), $lines));
	}

	/**
	 * @param array<string, mixed> $person
	 * @return array<string, mixed> this year's Balance of a Leave type
	 */
	private static function balance(array $person, string $type): array {
		return array_column($person['days']['balances'], null, 'leaveType')[$type];
	}

	/**
	 * @param array{status: int, data: mixed} $answer
	 * @return array{int, mixed}
	 */
	private static function answer(array $answer): array {
		return [$answer['status'], $answer['data']];
	}
}
