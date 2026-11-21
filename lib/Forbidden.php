<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos;

use RuntimeException;

/** The caller may not do this (only HR writes, recursos-humanos#1 Access), with the reason in Spanish. */
class Forbidden extends RuntimeException {
}
