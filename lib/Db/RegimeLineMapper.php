<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/** @template-extends QBMapper<RegimeLine> */
class RegimeLineMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'rh_regimes', RegimeLine::class);
	}

	/** @return list<RegimeLine> the person's lines, oldest first */
	public function of(string $uid): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')->from($this->getTableName())
			->where($query->expr()->eq('uid', $query->createNamedParameter($uid)))
			->orderBy('starts_on');
		return $this->findEntities($query);
	}

	/** @return list<RegimeLine> everyone's lines, oldest first */
	public function all(): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')->from($this->getTableName())->orderBy('starts_on');
		return $this->findEntities($query);
	}
}
