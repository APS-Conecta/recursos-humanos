<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Ledger;

/** What a Movement is (recursos-humanos#1, Schema); later tickets add uses, cancellations and the rest. */
enum MovementKind: string {
	/** The year's days of a Leave type. */
	case Grant = 'grant';
	/** Annual leave rising on the day Years of service reach a step. */
	case ServiceRise = 'serviceRise';
}
