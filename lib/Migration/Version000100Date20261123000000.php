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
 * Requests, their trail and the Delegation (recursos-humanos#6). Nothing here is ever deleted: a
 * Request changes state, each step is a new trail row, and a new Delegation is a new row that
 * keeps the earlier ones.
 */
class Version000100Date20261123000000 extends SimpleMigrationStep {
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper {
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('rh_requests')) {
			$requests = $schema->createTable('rh_requests');
			$requests->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$requests->addColumn('uid', Types::STRING, ['notnull' => true, 'length' => 64]);
			$requests->addColumn('leave_type', Types::STRING, ['notnull' => true, 'length' => 64]);
			$requests->addColumn('starts_on', Types::STRING, ['notnull' => true, 'length' => 10]);
			$requests->addColumn('ends_on', Types::STRING, ['notnull' => true, 'length' => 10]);
			// In the Leave type's unit, as the Ledger counts it (recursos-humanos#1, Ledger).
			$requests->addColumn('days', Types::DECIMAL, ['notnull' => true, 'precision' => 8, 'scale' => 2]);
			$requests->addColumn('state', Types::STRING, ['notnull' => true, 'length' => 32]);
			$requests->addColumn('comment', Types::TEXT, ['notnull' => false]);
			// The Rule versions it was counted with, as JSON [{rule, version}] (ADR 0002).
			$requests->addColumn('rule_versions', Types::TEXT, ['notnull' => true]);
			$requests->setPrimaryKey(['id']);
			$requests->addIndex(['uid'], 'rh_requests_uid');
			$requests->addIndex(['state'], 'rh_requests_state');
		}

		if (!$schema->hasTable('rh_request_steps')) {
			$steps = $schema->createTable('rh_request_steps');
			$steps->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$steps->addColumn('request_id', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$steps->addColumn('kind', Types::STRING, ['notnull' => true, 'length' => 32]);
			$steps->addColumn('actor', Types::STRING, ['notnull' => true, 'length' => 64]);
			// In what capacity a decision was taken; null for a step that decides nothing.
			$steps->addColumn('capacity', Types::STRING, ['notnull' => false, 'length' => 32]);
			// The Delegation a Jefatura decided by.
			$steps->addColumn('delegation_id', Types::BIGINT, ['notnull' => false, 'unsigned' => true]);
			$steps->addColumn('at', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$steps->addColumn('note', Types::TEXT, ['notnull' => false]);
			$steps->setPrimaryKey(['id']);
			$steps->addIndex(['request_id'], 'rh_request_steps_request');
		}

		if (!$schema->hasTable('rh_delegations')) {
			$delegations = $schema->createTable('rh_delegations');
			$delegations->addColumn('id', Types::BIGINT, ['autoincrement' => true, 'notnull' => true, 'unsigned' => true]);
			$delegations->addColumn('number', Types::STRING, ['notnull' => true, 'length' => 64]);
			$delegations->addColumn('granted_on', Types::STRING, ['notnull' => true, 'length' => 10]);
			$delegations->addColumn('recorded_by', Types::STRING, ['notnull' => true, 'length' => 64]);
			$delegations->addColumn('recorded_at', Types::BIGINT, ['notnull' => true, 'unsigned' => true]);
			$delegations->setPrimaryKey(['id']);
		}

		return $schema;
	}
}
