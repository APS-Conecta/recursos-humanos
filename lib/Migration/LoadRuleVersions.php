<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Migration;

use OCA\RecursosHumanos\RuleBook\RuleBook;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\IRepairStep;

/**
 * Loads the Rule versions the app ships (seeds/rule-versions.json) on install and on upgrade
 * (ADR 0002). A repair step, not a migration's postSchemaChange, because a first `occ app:enable`
 * applies the schema only (Estadística's EnsureSeedData); registered for both in info.xml.
 */
class LoadRuleVersions implements IRepairStep {
	public function __construct(
		private IDBConnection $db,
		private RuleBook $rules,
	) {
	}

	public function getName(): string {
		return 'Recursos Humanos: load the Rule versions the app ships';
	}

	public function run(IOutput $output): void {
		if (!$this->db->tableExists('rh_rule_versions')) {
			return;
		}
		$output->info(sprintf('Recursos Humanos: %d new Rule versions', $this->rules->load()));
	}
}
