<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Requests;

/** What a step of a Request's trail records; later tickets add registration, withdrawal and cancellation. */
enum StepKind: string {
	case Asked = 'asked';
	case Approved = 'approved';
	case Rejected = 'rejected';
}
