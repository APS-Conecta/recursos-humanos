<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos;

/**
 * The shapes the OCS API answers with, for openapi.json.
 *
 * @psalm-type RecursosHumanosAccount = array{
 *     uid: string,
 *     name: string,
 * }
 *
 * @psalm-type RecursosHumanosPersonItem = array{
 *     uid: string,
 *     name: string,
 *     cargos: list<string>,
 *     complete: bool,
 *     rut: ?string,
 *     givenNames: ?string,
 *     surnames: ?string,
 *     regime: 'estatutoAps'|'honorarios'|null,
 *     jefatura: ?RecursosHumanosAccount,
 * }
 *
 * @psalm-type RecursosHumanosRegimeLine = array{
 *     regime: 'estatutoAps'|'honorarios',
 *     from: string,
 * }
 *
 * @psalm-type RecursosHumanosTrailStep = array{
 *     kind: 'completed'|'regime',
 *     actor: RecursosHumanosAccount,
 *     at: int,
 *     regime: 'estatutoAps'|'honorarios'|null,
 *     from: ?string,
 *     reason: ?string,
 * }
 *
 * @psalm-type RecursosHumanosRuleVersion = array{
 *     rule: string,
 *     version: int,
 *     title: string,
 *     summary: string,
 *     basis: string,
 *     from: string,
 *     establishmentRule: bool,
 * }
 *
 * @psalm-type RecursosHumanosMovement = array{
 *     id: int,
 *     leaveType: 'annualLeave'|'administrativeDay'|'training',
 *     period: int,
 *     date: string,
 *     kind: 'grant'|'serviceRise',
 *     quantity: float,
 *     actor: ?RecursosHumanosAccount,
 *     reason: string,
 *     ruleVersion: ?array{rule: string, version: int, title: string},
 * }
 *
 * @psalm-type RecursosHumanosBalance = array{
 *     leaveType: 'annualLeave'|'administrativeDay'|'training',
 *     unit: 'habiles'|'corridos',
 *     balance: float,
 *     granted: float,
 * }
 *
 * @psalm-type RecursosHumanosDays = array{
 *     year: int,
 *     serviceMonths: int,
 *     annualLeaveFrom: ?string,
 *     nextRise: ?array{on: string, years: int, days: float},
 *     balances: list<RecursosHumanosBalance>,
 *     missing: list<'administrativeDays'>,
 * }
 *
 * @psalm-type RecursosHumanosPerson = RecursosHumanosPersonItem&array{
 *     startDate: string,
 *     priorMonths: int,
 *     shift: bool,
 *     regimes: list<RecursosHumanosRegimeLine>,
 *     trail: list<RecursosHumanosTrailStep>,
 *     days: RecursosHumanosDays,
 *     movements: list<RecursosHumanosMovement>,
 * }
 *
 * @psalm-type RecursosHumanosHoliday = array{
 *     date: string,
 *     name: string,
 * }
 *
 * @psalm-type RecursosHumanosSkippedDay = array{
 *     date: string,
 *     reason: 'weekend'|'holiday',
 *     name: ?string,
 * }
 *
 * @psalm-type RecursosHumanosYearCount = array{
 *     year: int,
 *     days: float,
 * }
 *
 * @psalm-type RecursosHumanosCount = array{
 *     days: float,
 *     counted: list<string>,
 *     skipped: list<RecursosHumanosSkippedDay>,
 *     years: list<RecursosHumanosYearCount>,
 * }
 */
class ResponseDefinitions {
}
