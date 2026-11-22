<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

/**
 * The Rule versions and the Ledger (recursos-humanos#5): the law's numbers as dated data, and the
 * Movements whose sum is every Balance (ADR 0001, ADR 0002). Nothing here is ever deleted.
 */
class Version000100Date20261122000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('rh_rule_versions')) {
			$rules = $schema->createTable('rh_rule_versions');
			$rules->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$rules->addColumn('rule_id', Types::STRING, ['notnull' => true, 'length' => 64]);
			$rules->addColumn('version', Types::INTEGER, ['notnull' => true, 'unsigned' => true]);
			$rules->addColumn('title', Types::STRING, ['notnull' => true, 'length' => 200]);
			$rules->addColumn('summary', Types::TEXT, ['notnull' => true]);
			// What the app reads, as JSON (seeds/rule-versions.json).
			$rules->addColumn('value', Types::TEXT, ['notnull' => true]);
			$rules->addColumn('basis', Types::STRING, ['notnull' => true, 'length' => 200]);
			$rules->addColumn('starts_on', Types::STRING, ['notnull' => true, 'length' => 10]);
			$rules->addColumn('establishment', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
			$rules->setPrimaryKey(['id']);
			$rules->addUniqueIndex(['rule_id', 'version'], 'rh_rule_versions_rule');
		}

		if (!$schema->hasTable('rh_movements')) {
			$movements = $schema->createTable('rh_movements');
			$movements->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$movements->addColumn('uid', Types::STRING, ['notnull' => true, 'length' => 64]);
			$movements->addColumn('leave_type', Types::STRING, ['notnull' => true, 'length' => 64]);
			$movements->addColumn('period_year', Types::INTEGER, ['notnull' => true, 'unsigned' => true]);
			$movements->addColumn('effective_on', Types::STRING, ['notnull' => true, 'length' => 10]);
			$movements->addColumn('kind', Types::STRING, ['notnull' => true, 'length' => 32]);
			// Days in half-day steps, or hours later: decimal, in the Leave type's unit (recursos-humanos#1, Ledger).
			$movements->addColumn('quantity', Types::DECIMAL, ['notnull' => true, 'precision' => 8, 'scale' => 2]);
			// The account that wrote it; null when the app wrote it on its own.
			$movements->addColumn('actor', Types::STRING, ['notnull' => false, 'length' => 64]);
			$movements->addColumn('reason', Types::TEXT, ['notnull' => true]);
			$movements->addColumn('rule_id', Types::STRING, ['notnull' => false, 'length' => 64]);
			$movements->addColumn('rule_version', Types::INTEGER, ['notnull' => false, 'unsigned' => true]);
			// A Movement that may exist only once per person (a year's grant, a service rise) names
			// itself here, so two runs at the same moment cannot both write it.
			$movements->addColumn('once_key', Types::STRING, ['notnull' => false, 'length' => 128]);
			$movements->setPrimaryKey(['id']);
			$movements->addIndex(['uid', 'period_year'], 'rh_movements_uid_period');
			$movements->addUniqueIndex(['uid', 'once_key'], 'rh_movements_once');
		}

		return $schema;
	}
}
