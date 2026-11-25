<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<Delegation> */
class DelegationMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'rh_delegations', Delegation::class);
	}

	/** The one in force: the latest recorded. */
	public function latest(): ?Delegation {
		$query = $this->db->getQueryBuilder();
		$query->select('*')->from($this->getTableName())->orderBy('id', 'DESC')->setMaxResults(1);
		try {
			return $this->findEntity($query);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/**
	 * @param list<int> $ids
	 * @return array<int, Delegation> by id
	 */
	public function byIds(array $ids): array {
		if ($ids === []) {
			return [];
		}
		$query = $this->db->getQueryBuilder();
		$query->select('*')->from($this->getTableName())
			->where($query->expr()->in('id', $query->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)));
		$found = [];
		foreach ($this->findEntities($query) as $delegation) {
			$found[$delegation->getId()] = $delegation;
		}
		return $found;
	}
}
