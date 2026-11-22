<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Tests\Seam;

/**
 * Working days and the request preview (recursos-humanos#4): the national Public holiday list
 * ships with the app, and a range counts in días hábiles (no Saturdays, Sundays or Public
 * holidays) or días corridos, each day in its own year, before anything is sent.
 */
class WorkingDaysTest extends SeamTestCase {
	private const PREVIEW = '/apps/recursos_humanos/api/v1/requests/preview';
	private const HOLIDAYS = '/apps/recursos_humanos/api/v1/holidays';

	private static string $staff;

	public static function setUpBeforeClass(): void {
		self::$staff = self::person();
	}

	public function testAWeekendAndANationalHolidayAreSkippedByNameAndTheRestCount(): void {
		$count = self::preview('2026-05-18', '2026-05-26', 'habiles');

		self::assertSame(200, $count['status'], json_encode($count['data']));
		self::assertSame([
			'days' => 6,
			'counted' => ['2026-05-18', '2026-05-19', '2026-05-20', '2026-05-22', '2026-05-25', '2026-05-26'],
			'skipped' => [
				['date' => '2026-05-21', 'reason' => 'holiday', 'name' => 'Día de las Glorias Navales'],
				['date' => '2026-05-23', 'reason' => 'weekend', 'name' => null],
				['date' => '2026-05-24', 'reason' => 'weekend', 'name' => null],
			],
			'years' => [['year' => 2026, 'days' => 6]],
		], $count['data']);
	}

	public function testAHolidayOnASaturdayIsSkippedAsTheWeekendWithItsName(): void {
		$skipped = self::preview('2026-09-17', '2026-09-21', 'habiles')['data']['skipped'];

		self::assertSame([
			['date' => '2026-09-18', 'reason' => 'holiday', 'name' => 'Independencia Nacional'],
			['date' => '2026-09-19', 'reason' => 'weekend', 'name' => 'Día de las Glorias del Ejército'],
			['date' => '2026-09-20', 'reason' => 'weekend', 'name' => null],
		], $skipped);
	}

	public function testDiasCorridosCountEveryDay(): void {
		$count = self::preview('2026-05-18', '2026-05-26', 'corridos')['data'];

		self::assertSame(9, $count['days']);
		self::assertCount(9, $count['counted']);
		self::assertSame([], $count['skipped'], 'a weekend or a holiday counts like any other day');
	}

	public function testARangeCrossing31DecemberReportsTheDaysOfEachYear(): void {
		$habiles = self::preview('2026-12-28', '2027-01-08', 'habiles')['data'];
		$corridos = self::preview('2026-12-28', '2027-01-08', 'corridos')['data'];

		self::assertSame(9, $habiles['days']);
		self::assertSame([['year' => 2026, 'days' => 4], ['year' => 2027, 'days' => 5]], $habiles['years'], 'Año Nuevo and the first weekend are skipped');
		self::assertSame(['date' => '2027-01-01', 'reason' => 'holiday', 'name' => 'Año Nuevo'], $habiles['skipped'][0]);
		self::assertSame([['year' => 2026, 'days' => 4], ['year' => 2027, 'days' => 8]], $corridos['years']);
	}

	public function testTheNationalListShipsForEachYear(): void {
		$holidays2026 = self::ocs(self::$staff, 'GET', self::HOLIDAYS . '?year=2026');
		$holidays2027 = self::ocs(self::$staff, 'GET', self::HOLIDAYS . '?year=2027')['data']['holidays'];

		self::assertSame(200, $holidays2026['status']);
		self::assertCount(16, $holidays2026['data']['holidays']);
		self::assertSame(['date' => '2026-01-01', 'name' => 'Año Nuevo'], $holidays2026['data']['holidays'][0]);
		self::assertContains(['date' => '2026-09-18', 'name' => 'Independencia Nacional'], $holidays2026['data']['holidays']);
		self::assertCount(17, $holidays2027);
		self::assertContains(['date' => '2027-09-17', 'name' => 'Feriado adicional de Fiestas Patrias'], $holidays2027);
	}

	public function testAYearWithoutAListIsNeitherListedNorCounted(): void {
		$holidays = self::ocs(self::$staff, 'GET', self::HOLIDAYS . '?year=2028');
		$count = self::preview('2027-12-27', '2028-01-05', 'corridos');

		self::assertSame([404, ['message' => 'Todavía no está la lista de días feriados de 2028.']], [$holidays['status'], $holidays['data']]);
		self::assertSame([422, ['message' => 'Todavía no está la lista de días feriados de 2028.']], [$count['status'], $count['data']]);
	}

	public function testARangeThatCannotBeCountedIsRefusedWithTheReason(): void {
		$refusals = [
			['2026-03-10', '2026-03-09', 'habiles', 'La fecha de término es anterior a la de inicio.'],
			['2026-02-30', '2026-03-02', 'habiles', 'Elige la fecha de inicio.'],
			['2026-03-02', '3/3/2026', 'habiles', 'Elige la fecha de término.'],
			['2026-03-02', '2026-03-03', 'horas', 'Elige cómo se cuentan los días: hábiles o corridos.'],
		];

		foreach ($refusals as [$from, $to, $unit, $message]) {
			$answer = self::preview($from, $to, $unit);
			self::assertSame([422, ['message' => $message]], [$answer['status'], $answer['data']], "$from → $to, $unit");
		}
	}

	public function testAnyoneSignedInMayCountAndNobodyElse(): void {
		self::assertSame(200, self::preview('2026-03-02', '2026-03-06', 'habiles')['status'], 'a person with no record yet may count');
		self::assertSame(401, self::ocs(null, 'GET', self::PREVIEW . '?from=2026-03-02&to=2026-03-06&unit=habiles')['status']);
		self::assertSame(401, self::ocs(null, 'GET', self::HOLIDAYS . '?year=2026')['status']);
	}

	/** @return array{status: int, data: mixed} the staff member's preview of the range */
	private static function preview(string $from, string $to, string $unit): array {
		return self::ocs(self::$staff, 'GET', self::PREVIEW . '?' . http_build_query(['from' => $from, 'to' => $to, 'unit' => $unit]));
	}
}
