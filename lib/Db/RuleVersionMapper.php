<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/** @template-extends QBMapper<RuleVersion> */
class RuleVersionMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'rh_rule_versions', RuleVersion::class);
	}

	/** @return list<RuleVersion> every version, each rule's oldest first */
	public function all(): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')->from($this->getTableName())->orderBy('rule_id')->addOrderBy('version');
		return $this->findEntities($query);
	}
}
