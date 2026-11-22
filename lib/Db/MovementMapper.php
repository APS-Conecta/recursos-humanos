<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/** @template-extends QBMapper<Movement> */
class MovementMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'rh_movements', Movement::class);
	}

	/** @return list<Movement> the person's Movements, newest first */
	public function of(string $uid): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')->from($this->getTableName())
			->where($query->expr()->eq('uid', $query->createNamedParameter($uid)))
			->orderBy('effective_on', 'DESC')->addOrderBy('id', 'DESC');
		return $this->findEntities($query);
	}

	/** @return list<string> the people who have the Movement named $onceKey */
	public function holders(string $onceKey): array {
		$query = $this->db->getQueryBuilder();
		$query->select('uid')->from($this->getTableName())
			->where($query->expr()->eq('once_key', $query->createNamedParameter($onceKey)));
		$result = $query->executeQuery();
		$uids = [];
		while (($row = $result->fetch()) !== false) {
			$uids[] = (string)$row['uid'];
		}
		$result->closeCursor();
		return $uids;
	}
}
