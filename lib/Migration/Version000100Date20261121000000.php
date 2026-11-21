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
 * The person record (recursos-humanos#1, Schema): the Person, their dated Regime lines and the
 * record's trail. Later tickets add the other tables and the columns their work needs; nothing
 * here is ever deleted.
 */
class Version000100Date20261121000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('rh_people')) {
			$people = $schema->createTable('rh_people');
			$people->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$people->addColumn('uid', Types::STRING, ['notnull' => true, 'length' => 64]);
			// The canonical RUT, body and verifier digit: 12345678-5 (ADR 0006).
			$people->addColumn('rut', Types::STRING, ['notnull' => true, 'length' => 10]);
			$people->addColumn('given_names', Types::STRING, ['notnull' => true, 'length' => 200]);
			$people->addColumn('surnames', Types::STRING, ['notnull' => true, 'length' => 200]);
			$people->addColumn('start_date', Types::STRING, ['notnull' => true, 'length' => 10]);
			$people->addColumn('prior_months', Types::INTEGER, ['notnull' => true, 'default' => 0, 'unsigned' => true]);
			$people->addColumn('shift', Types::BOOLEAN, ['notnull' => false, 'default' => false]);
			$people->addColumn('jefatura', Types::STRING, ['notnull' => false, 'length' => 64]);
			$people->setPrimaryKey(['id']);
			$people->addUniqueIndex(['uid'], 'rh_people_uid');
			$people->addUniqueIndex(['rut'], 'rh_people_rut');
		}

		if (!$schema->hasTable('rh_regimes')) {
			$regimes = $schema->createTable('rh_regimes');
			$regimes->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$regimes->addColumn('uid', Types::STRING, ['notnull' => true, 'length' => 64]);
			$regimes->addColumn('regime', Types::STRING, ['notnull' => true, 'length' => 16]);
			$regimes->addColumn('starts_on', Types::STRING, ['notnull' => true, 'length' => 10]);
			$regimes->setPrimaryKey(['id']);
			$regimes->addUniqueIndex(['uid', 'starts_on'], 'rh_regimes_uid_starts');
		}

		if (!$schema->hasTable('rh_person_log')) {
			$log = $schema->createTable('rh_person_log');
			$log->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$log->addColumn('uid', Types::STRING, ['notnull' => true, 'length' => 64]);
			$log->addColumn('kind', Types::STRING, ['notnull' => true, 'length' => 32]);
			$log->addColumn('actor', Types::STRING, ['notnull' => true, 'length' => 64]);
			$log->addColumn('at', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			// What the step changed, as JSON, and the reason the HR officer wrote, when there is one.
			$log->addColumn('detail', Types::TEXT, ['notnull' => false]);
			$log->addColumn('note', Types::TEXT, ['notnull' => false]);
			$log->setPrimaryKey(['id']);
			$log->addIndex(['uid'], 'rh_person_log_uid');
		}

		return $schema;
	}
}
