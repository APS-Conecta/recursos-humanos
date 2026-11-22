<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Command;

use OCA\RecursosHumanos\Day;
use OCA\RecursosHumanos\Ledger\Grants;
use OCA\RecursosHumanos\Refused;
use OCP\AppFramework\Utility\ITimeFactory;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * `occ recursos_humanos:grants [--date=YYYY-MM-DD]`: writes the grants due by a day, as the hourly
 * job does for today, to recover a missed run (recursos-humanos#1, YearClose). A grant already
 * written is never written twice.
 */
class GrantsDue extends Command {
	public function __construct(
		private Grants $grants,
		private ITimeFactory $time,
	) {
		parent::__construct();
	}

	protected function configure(): void {
		$this->setName('recursos_humanos:grants')
			->setDescription('Write the yearly grants due by a day: Annual leave, its service rises, Administrative days and Training')
			->addOption('date', null, InputOption::VALUE_REQUIRED, 'The day, YYYY-MM-DD; today in Chile by default');
	}

	protected function execute(InputInterface $input, OutputInterface $output): int {
		$date = $input->getOption('date');
		try {
			$day = is_string($date) ? Day::of($date, 'The date is a YYYY-MM-DD day.')->format('Y-m-d') : Day::today($this->time);
		} catch (Refused $e) {
			$output->writeln('<error>' . $e->getMessage() . '</error>');
			return self::INVALID;
		}
		$output->writeln(sprintf('%d grants written, due by %s.', $this->grants->dueForEveryone($day), $day));
		return self::SUCCESS;
	}
}
