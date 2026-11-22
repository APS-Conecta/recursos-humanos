<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\BackgroundJob;

use OCA\RecursosHumanos\Day;
use OCA\RecursosHumanos\Ledger\Grants;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\BackgroundJob\TimedJob;

/**
 * Writes the grants that fall due (recursos-humanos#5): Annual leave on the day service passes
 * its first year, the rise on the day it reaches a step, and each year's grants on 1 January.
 * Hourly, so a grant lands within the hour its day begins; a run with nothing due writes nothing.
 */
class GrantsDue extends TimedJob {
	public function __construct(
		ITimeFactory $time,
		private Grants $grants,
	) {
		parent::__construct($time);
		$this->setInterval(3600);
	}

	protected function run($argument): void {
		$this->grants->dueForEveryone(Day::today($this->time));
	}
}
