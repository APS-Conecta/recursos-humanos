<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\WorkingDays;

/** How a Leave type counts its days (recursos-humanos#1, Schema): días hábiles or días corridos. */
enum Unit: string {
	/** Not Saturdays, Sundays or Public holidays (Ley 19.378 art. 18; CGR 011162N15). */
	case Habiles = 'habiles';
	/** Every day of the range. */
	case Corridos = 'corridos';
}
