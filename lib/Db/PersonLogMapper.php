<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/** @template-extends QBMapper<PersonLogEntry> */
class PersonLogMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'rh_person_log', PersonLogEntry::class);
	}

	/** @return list<PersonLogEntry> the record's trail, newest first */
	public function of(string $uid): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')->from($this->getTableName())
			->where($query->expr()->eq('uid', $query->createNamedParameter($uid)))
			->orderBy('at', 'DESC')->addOrderBy('id', 'DESC');
		return $this->findEntities($query);
	}
}
