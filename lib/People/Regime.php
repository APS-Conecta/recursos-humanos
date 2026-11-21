<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\People;

/** A person's Regime (CONTEXT.md): under the Estatuto APS, or on honorarios without Annual leave or Administrative days. */
enum Regime: string {
	case EstatutoAps = 'estatutoAps';
	case Honorarios = 'honorarios';
}
