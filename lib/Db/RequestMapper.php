<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<Request> */
class RequestMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'rh_requests', Request::class);
	}

	public function byId(int $id): ?Request {
		$query = $this->db->getQueryBuilder();
		$query->select('*')->from($this->getTableName())
			->where($query->expr()->eq('id', $query->createNamedParameter($id)));
		try {
			return $this->findEntity($query);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	/** @return list<Request> the person's Requests, newest first */
	public function of(string $uid): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')->from($this->getTableName())
			->where($query->expr()->eq('uid', $query->createNamedParameter($uid)))
			->orderBy('id', 'DESC');
		return $this->findEntities($query);
	}

	/**
	 * @param list<string> $states
	 * @return list<Request> everyone's Requests in these states, oldest first
	 */
	public function inStates(array $states): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')->from($this->getTableName())
			->where($query->expr()->in('state', $query->createNamedParameter($states, IQueryBuilder::PARAM_STR_ARRAY)))
			->orderBy('id', 'ASC');
		return $this->findEntities($query);
	}

	/** Moves the Request from $from to $to; false when it is no longer in $from, as another decider got there first. */
	public function move(int $id, string $from, string $to): bool {
		$query = $this->db->getQueryBuilder();
		$query->update($this->getTableName())
			->set('state', $query->createNamedParameter($to))
			->where($query->expr()->eq('id', $query->createNamedParameter($id)))
			->andWhere($query->expr()->eq('state', $query->createNamedParameter($from)));
		return $query->executeStatement() === 1;
	}
}
