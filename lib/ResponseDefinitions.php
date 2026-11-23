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
 *     available: float,
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
 * @psalm-type RecursosHumanosDecider = array{
 *     capacity: 'delegation'|'director'|'external',
 *     accounts: list<RecursosHumanosAccount>,
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
 *     decider: RecursosHumanosDecider,
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
 *
 * @psalm-type RecursosHumanosAvailable = array{
 *     year: int,
 *     balance: float,
 *     inFlight: float,
 *     available: float,
 * }
 *
 * @psalm-type RecursosHumanosPreview = RecursosHumanosCount&array{
 *     unit: 'habiles'|'corridos',
 *     errors: list<string>,
 *     available: list<RecursosHumanosAvailable>,
 * }
 *
 * @psalm-type RecursosHumanosDelegation = array{
 *     number: string,
 *     date: string,
 *     recordedBy: RecursosHumanosAccount,
 *     recordedAt: int,
 * }
 *
 * @psalm-type RecursosHumanosRequestStep = array{
 *     kind: 'asked'|'approved'|'rejected',
 *     actor: RecursosHumanosAccount,
 *     at: int,
 *     capacity: 'delegation'|'director'|null,
 *     delegation: ?RecursosHumanosDelegation,
 *     note: ?string,
 * }
 *
 * @psalm-type RecursosHumanosRequest = array{
 *     id: int,
 *     person: array{uid: string, name: string, rut: string, cargos: list<string>},
 *     leaveType: 'annualLeave'|'administrativeDay'|'training',
 *     from: string,
 *     to: string,
 *     unit: 'habiles'|'corridos',
 *     days: float,
 *     years: list<RecursosHumanosYearCount>,
 *     counted: list<string>,
 *     skipped: list<RecursosHumanosSkippedDay>,
 *     state: 'pending'|'approved'|'rejected',
 *     comment: ?string,
 *     decider: ?RecursosHumanosDecider,
 *     available: list<RecursosHumanosAvailable>,
 *     ruleVersions: list<RecursosHumanosRuleVersion>,
 *     trail: list<RecursosHumanosRequestStep>,
 * }
 */
class ResponseDefinitions {
}
