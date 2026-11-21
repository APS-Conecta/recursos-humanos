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
 * @psalm-type RecursosHumanosPerson = RecursosHumanosPersonItem&array{
 *     startDate: string,
 *     priorMonths: int,
 *     shift: bool,
 *     regimes: list<RecursosHumanosRegimeLine>,
 *     trail: list<RecursosHumanosTrailStep>,
 * }
 */
class ResponseDefinitions {
}
