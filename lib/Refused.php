<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos;

use APS\Common\Refusal;
use RuntimeException;

/** A request understood and turned down, with the reason in Spanish as the person reads it (aps-common's Refusal). */
class Refused extends RuntimeException implements Refusal {
}
