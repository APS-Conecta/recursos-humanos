<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Tests\Seam;

use DateTimeImmutable;
use DateTimeZone;

/**
 * The first end-to-end Request (recursos-humanos#6): a person asks for Annual leave or an
 * Administrative day; Available is the Balance minus the Requests in flight, and nothing goes
 * above it; the assigned Jefatura decides «por delegación del Director/a», the Director decides
 * for a person without one; a rejection carries its reason; each step is in the trail, and the
 * decider and the person are notified. The days asked are in the first two weeks of March of the
 * current year, which hold no Public holiday in any year (Good Friday falls on 20 March at the
 * earliest), so the suite reads the same any day it runs.
 */
class RequestsTest extends SeamTestCase {
	private const API = '/apps/recursos_humanos/api/v1';
	private const NOTIFICATIONS = '/apps/notifications/api/v2/notifications';

	private static string $officer;
	private static string $director;
	private static string $jefatura;
	private static int $year;

	public static function setUpBeforeClass(): void {
		self::$officer = self::person();
		self::joins(self::$officer, 'role-recursos-humanos');
		self::$director = self::person();
		self::joins(self::$director, 'role-director-cesfam');
		self::$jefatura = self::person();
		self::$year = (int)(new DateTimeImmutable('now', new DateTimeZone('America/Santiago')))->format('Y');
		$recorded = self::ocs(self::$officer, 'POST', self::API . '/settings/delegation', ['number' => '1234', 'date' => self::$year . '-01-05']);
		self::assertSame(201, $recorded['status'], json_encode($recorded['data']));
	}

	public function testOnlyHrRecordsTheDelegationAndEveryoneReadsTheOneInForce(): void {
		$staff = self::person();

		self::assertSame(403, self::ocs($staff, 'POST', self::API . '/settings/delegation', ['number' => '99', 'date' => self::$year . '-01-02'])['status']);
		self::assertSame([422, ['message' => 'Escribe el número de la resolución.']],
			self::answer(self::ocs(self::$officer, 'POST', self::API . '/settings/delegation', ['number' => ' ', 'date' => self::$year . '-01-02'])));
		self::assertSame([422, ['message' => 'Elige la fecha de la resolución.']],
			self::answer(self::ocs(self::$officer, 'POST', self::API . '/settings/delegation', ['number' => '99', 'date' => 'ayer'])));

		$read = self::ocs($staff, 'GET', self::API . '/settings/delegation');
		self::assertSame(200, $read['status']);
		self::assertSame(['number' => '1234', 'date' => self::$year . '-01-05'], array_intersect_key($read['data']['delegation'], ['number' => 0, 'date' => 0]));
		self::assertSame(self::$officer, $read['data']['delegation']['recordedBy']['uid']);
	}

	public function testAPendingRequestLowersAvailableButNotTheBalance(): void {
		$person = self::completed(self::$jefatura);

		$asked = self::asks($person, 'annualLeave', self::march(1, 0), self::march(1, 4));

		self::assertSame(201, $asked['status'], json_encode($asked['data']));
		$request = $asked['data']['request'];
		self::assertSame(['pending', 5, 'habiles', [['year' => self::$year, 'days' => 5]]],
			[$request['state'], $request['days'], $request['unit'], $request['years']]);
		self::assertSame(['leaveType' => 'annualLeave', 'unit' => 'habiles', 'balance' => 15, 'granted' => 15, 'available' => 10],
			self::balance($person, 'annualLeave'), 'Available drops by the pending days; the Balance waits for registration');
	}

	public function testASecondRequestAboveAvailableIsRefusedWithThePerYearReasonWhichThePreviewAlreadyShows(): void {
		$person = self::completed(self::$jefatura);
		self::assertSame(201, self::asks($person, 'administrativeDay', self::march(1, 0), self::march(1, 4))['status']);
		$reason = sprintf('%d: pides 2 días hábiles y tu disponible es 1: saldo 6 menos 5 en solicitudes en curso.', self::$year);

		$preview = self::ocs($person, 'GET', self::API . '/requests/preview?' . http_build_query(['from' => self::march(2, 0), 'to' => self::march(2, 1), 'leaveType' => 'administrativeDay']));

		self::assertSame(200, $preview['status'], 'a blocking error still shows the count');
		self::assertSame([2, [$reason], [['year' => self::$year, 'balance' => 6, 'inFlight' => 5, 'available' => 1]]],
			[$preview['data']['days'], $preview['data']['errors'], $preview['data']['available']]);
		self::assertSame([422, ['message' => $reason]], self::answer(self::asks($person, 'administrativeDay', self::march(2, 0), self::march(2, 1))));
	}

	public function testTheUnitComesFromTheLeaveTypeAndAContradictingOneIsRefused(): void {
		$person = self::completed(self::$jefatura);
		$range = ['from' => self::march(1, 0), 'to' => self::march(1, 6)];

		$preview = self::ocs($person, 'GET', self::API . '/requests/preview?' . http_build_query($range + ['leaveType' => 'annualLeave']));

		self::assertSame(200, $preview['status'], json_encode($preview['data']));
		self::assertSame([5, [], [['year' => self::$year, 'balance' => 15, 'inFlight' => 0, 'available' => 15]]],
			[$preview['data']['days'], $preview['data']['errors'], $preview['data']['available']], 'Annual leave counts in días hábiles');
		self::assertSame([422, ['message' => 'Feriado legal: se cuenta en días hábiles.']],
			self::answer(self::ocs($person, 'GET', self::API . '/requests/preview?' . http_build_query($range + ['leaveType' => 'annualLeave', 'unit' => 'corridos']))));
	}

	public function testARequestOverlappingAnotherInFlightIsRefused(): void {
		$person = self::completed(self::$jefatura);
		self::assertSame(201, self::asks($person, 'annualLeave', self::march(1, 0), self::march(1, 4))['status']);

		$overlap = self::asks($person, 'administrativeDay', self::march(1, 2), self::march(1, 2));

		$first = (int)substr(self::march(1, 0), 8);
		self::assertSame([422, ['message' => sprintf('Ya tienes una solicitud en esas fechas: feriado legal, del %d al %d de marzo de %d.', $first, $first + 4, self::$year)]],
			self::answer($overlap));
	}

	public function testNobodyAsksBeforeHrCompletesTheirRecordNorAsksAnnualLeaveBeforeTheirFirstYear(): void {
		self::assertSame([422, ['message' => 'Recursos Humanos todavía no completa tu ficha: cuando lo haga, podrás pedir días.']],
			self::answer(self::asks(self::person(), 'administrativeDay', self::march(1, 0), self::march(1, 0))));

		$year = self::$year;
		$newcomer = self::completed(self::$jefatura, ['startDate' => "$year-01-01", 'regimeFrom' => "$year-01-01", 'priorMonths' => 0]);

		self::assertSame([422, ['message' => sprintf('Tendrás feriado legal desde el 2 de enero de %d, al cumplir más de un año de servicio.', $year + 1)]],
			self::answer(self::asks($newcomer, 'annualLeave', self::march(2, 0), self::march(2, 0))));
	}

	public function testTheJefaturaApprovesByDelegationAndNobodyElseDecides(): void {
		$person = self::completed(self::$jefatura);
		$id = self::asks($person, 'annualLeave', self::march(2, 0), self::march(2, 4))['data']['request']['id'];

		self::assertContains($id, array_column(self::ocs(self::$jefatura, 'GET', self::API . '/decisions')['data']['requests'], 'id'));
		self::assertSame(404, self::ocs(self::person(), 'POST', self::API . "/requests/$id/approval")['status'], 'a stranger does not even see it');
		self::assertSame(403, self::ocs(self::$officer, 'POST', self::API . "/requests/$id/approval")['status'], 'HR registers, it does not decide');
		self::assertSame(403, self::ocs($person, 'POST', self::API . "/requests/$id/approval")['status'], 'nobody decides their own Request');

		$approved = self::ocs(self::$jefatura, 'POST', self::API . "/requests/$id/approval");

		self::assertSame(201, $approved['status'], json_encode($approved['data']));
		self::assertSame('approved', $approved['data']['request']['state']);
		self::assertSame([
			['kind' => 'asked', 'actor' => $person, 'capacity' => null, 'delegation' => null, 'note' => null],
			['kind' => 'approved', 'actor' => self::$jefatura, 'capacity' => 'delegation', 'delegation' => ['number' => '1234', 'date' => self::$year . '-01-05'], 'note' => null],
		], self::trail($approved['data']['request']));
		self::assertSame([422, ['message' => 'Esta solicitud ya está decidida.']], self::answer(self::ocs(self::$jefatura, 'POST', self::API . "/requests/$id/approval")));
		self::assertSame([15, 10], array_values(array_intersect_key(self::balance($person, 'annualLeave'), ['balance' => 0, 'available' => 0])),
			'approved but not registered: still in flight');
	}

	public function testARejectionNeedsAWrittenReasonWhichThePersonReadsWithTheEstablishmentRule(): void {
		$jefatura = self::person();
		$person = self::completed($jefatura);
		$id = self::asks($person, 'annualLeave', self::march(2, 0), self::march(2, 1))['data']['request']['id'];
		$reason = 'Ese lunes el vacunatorio queda sin enfermera. ¿Puedes pedir la semana siguiente?';

		self::assertSame([422, ['message' => 'Escribe el motivo del rechazo: la persona lo ve en su solicitud.']],
			self::answer(self::ocs($jefatura, 'POST', self::API . "/requests/$id/rejection", ['reason' => '  '])));
		self::assertSame(201, self::ocs($jefatura, 'POST', self::API . "/requests/$id/rejection", ['reason' => $reason])['status']);

		$request = self::ocs($person, 'GET', self::API . "/requests/$id")['data']['request'];
		self::assertSame('rejected', $request['state']);
		self::assertSame(['kind' => 'rejected', 'actor' => $jefatura, 'capacity' => 'delegation', 'delegation' => ['number' => '1234', 'date' => self::$year . '-01-05'], 'note' => $reason],
			self::trail($request)[1]);
		$rule = array_column($request['ruleVersions'], null, 'rule')['annualLeaveRejection'] ?? null;
		self::assertNotNull($rule, 'a rejected Annual leave cites the establishment rule that allows it');
		self::assertTrue($rule['establishmentRule']);
		self::assertSame(15, self::balance($person, 'annualLeave')['available'], 'a rejected Request is no longer in flight');
		$notice = self::notification($person, $id);
		self::assertSame("$jefatura rechazó tu solicitud", $notice['subject']);
		self::assertStringContainsString("«{$reason}»", $notice['message']);
	}

	public function testWithoutAJefaturaTheDirectorDecidesAsDirector(): void {
		$person = self::completed(null);
		$decider = self::ocs($person, 'GET', self::API . '/me')['data']['person']['decider'];
		self::assertSame('director', $decider['capacity']);
		self::assertContains(self::$director, array_column($decider['accounts'], 'uid'));
		$id = self::asks($person, 'administrativeDay', self::march(1, 3), self::march(1, 3))['data']['request']['id'];

		self::assertContains($id, array_column(self::ocs(self::$director, 'GET', self::API . '/decisions')['data']['requests'], 'id'));
		$approved = self::ocs(self::$director, 'POST', self::API . "/requests/$id/approval");

		self::assertSame(201, $approved['status'], json_encode($approved['data']));
		self::assertSame(['kind' => 'approved', 'actor' => self::$director, 'capacity' => 'director', 'delegation' => null, 'note' => null],
			self::trail($approved['data']['request'])[1]);
	}

	public function testPorDecidirListsTheRequestsOldestFirstWithAvailableBeforeTheirDays(): void {
		$jefatura = self::person();
		$first = self::completed($jefatura);
		$second = self::completed($jefatura);
		self::assertTrue(self::ocs($jefatura, 'GET', self::API . '/me')['data']['roles']['jefatura'], 'the menu shows «Por decidir»');
		$week = self::asks($first, 'annualLeave', self::march(1, 0), self::march(1, 4))['data']['request']['id'];
		$other = self::asks($second, 'administrativeDay', self::march(1, 0), self::march(1, 0))['data']['request']['id'];
		$twoDays = self::asks($first, 'annualLeave', self::march(2, 0), self::march(2, 1))['data']['request']['id'];

		$queue = self::ocs($jefatura, 'GET', self::API . '/decisions')['data'];

		self::assertSame([$week, $other, $twoDays], array_column($queue['requests'], 'id'));
		$byId = array_column($queue['requests'], null, 'id');
		$year = self::$year;
		self::assertSame([['year' => $year, 'balance' => 15, 'inFlight' => 2, 'available' => 13]], $byId[$week]['available'],
			'Available before this Request counts the others in flight, and its own days come off it');
		self::assertSame([['year' => $year, 'balance' => 15, 'inFlight' => 5, 'available' => 10]], $byId[$twoDays]['available']);
		self::assertSame([['year' => $year, 'days' => 2]], $byId[$twoDays]['years']);
		self::assertSame(['number' => '1234', 'date' => "$year-01-05"], array_intersect_key($queue['delegation'], ['number' => 0, 'date' => 0]));
	}

	public function testTheDeciderIsNotifiedOfTheAskAndThePersonOfTheApproval(): void {
		$jefatura = self::person();
		$person = self::completed($jefatura);
		$id = self::asks($person, 'annualLeave', self::march(2, 2), self::march(2, 4))['data']['request']['id'];

		$asked = self::notification($jefatura, $id);
		self::assertSame("$person pide feriado legal", $asked['subject']);
		self::assertStringContainsString('3 días hábiles', $asked['message']);

		self::ocs($jefatura, 'POST', self::API . "/requests/$id/approval");

		$approved = self::notification($person, $id);
		self::assertSame("$jefatura aprobó tu solicitud", $approved['subject']);
		self::assertStringContainsString('por delegación del Director/a', $approved['message']);
		self::assertNull(self::notification($jefatura, $id), 'the decided ask leaves the decider\'s notifications');
	}

	public function testOnlyThePersonTheirDeciderAndHrReadARequestWithTheRuleVersionsItWasCountedWith(): void {
		$person = self::completed(self::$jefatura);
		$leave = self::asks($person, 'annualLeave', self::march(1, 0), self::march(1, 0))['data']['request']['id'];
		$day = self::asks($person, 'administrativeDay', self::march(2, 0), self::march(2, 0))['data']['request']['id'];

		foreach ([$person, self::$jefatura, self::$officer] as $reader) {
			self::assertSame(200, self::ocs($reader, 'GET', self::API . "/requests/$leave")['status']);
		}
		self::assertSame(404, self::ocs(self::person(), 'GET', self::API . "/requests/$leave")['status']);

		$mine = self::ocs($person, 'GET', self::API . '/requests')['data']['requests'];
		self::assertSame([$day, $leave], array_column($mine, 'id'), 'the person\'s own Requests, newest first');
		$cited = static fn (array $request): array => array_map(static fn (array $rule): string => "{$rule['rule']} v{$rule['version']}", $request['ruleVersions']);
		self::assertSame(['annualLeaveDays v1', 'annualLeaveEligibility v1'], $cited($mine[1]));
		self::assertSame(['administrativeDays v1'], $cited($mine[0]));
		self::assertSame('Ley 19.378 art. 17', $mine[0]['ruleVersions'][0]['basis']);
	}

	/**
	 * A complete record under the Estatuto APS with more than a year of service, so this year's
	 * Annual leave (15) and Administrative days (6) are granted; $jefatura decides its Requests.
	 *
	 * @param array<string, mixed> $change
	 */
	private static function completed(?string $jefatura, array $change = []): string {
		$uid = self::person();
		$answer = self::ocs(self::$officer, 'POST', self::API . '/people', self::record($uid, ['jefatura' => $jefatura] + $change));
		self::assertSame(201, $answer['status'], json_encode($answer['data']));
		return $uid;
	}

	/** @return array{status: int, data: mixed} */
	private static function asks(string $person, string $leaveType, string $from, string $to): array {
		return self::ocs($person, 'POST', self::API . '/requests', ['leaveType' => $leaveType, 'from' => $from, 'to' => $to]);
	}

	/** A weekday of March this year: $week 1 or 2 from its first Monday, $offset days after that Monday. */
	private static function march(int $week, int $offset): string {
		return (new DateTimeImmutable(sprintf('first monday of march %d', self::$year), new DateTimeZone('UTC')))
			->modify(sprintf('+%d day', ($week - 1) * 7 + $offset))->format('Y-m-d');
	}

	/** @return array<string, mixed> this year's Balance of a Leave type, as the person's «Mis días» reads it */
	private static function balance(string $person, string $type): array {
		return array_column(self::ocs($person, 'GET', self::API . '/me')['data']['person']['days']['balances'], null, 'leaveType')[$type];
	}

	/**
	 * @param array<string, mixed> $request
	 * @return list<array<string, mixed>> its trail, each step without its time and the actor as a uid
	 */
	private static function trail(array $request): array {
		return array_map(static fn (array $step): array => [
			'kind' => $step['kind'],
			'actor' => $step['actor']['uid'],
			'capacity' => $step['capacity'],
			'delegation' => $step['delegation'] === null ? null : array_intersect_key($step['delegation'], ['number' => 0, 'date' => 0]),
			'note' => $step['note'],
		], $request['trail']);
	}

	/** @return array<string, mixed>|null the person's notification from this app about the Request, newest */
	private static function notification(string $person, int $request): ?array {
		foreach (self::ocs($person, 'GET', self::NOTIFICATIONS)['data'] ?? [] as $notification) {
			if ($notification['app'] === 'recursos_humanos' && $notification['object_type'] === 'request' && $notification['object_id'] === (string)$request) {
				return $notification;
			}
		}
		return null;
	}

	/**
	 * @param array{status: int, data: mixed} $answer
	 * @return array{int, mixed}
	 */
	private static function answer(array $answer): array {
		return [$answer['status'], $answer['data']];
	}
}
