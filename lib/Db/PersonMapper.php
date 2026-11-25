<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Db;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/** @template-extends QBMapper<Person> */
class PersonMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'rh_people', Person::class);
	}

	public function byUid(string $uid): ?Person {
		return $this->by('uid', $uid);
	}

	public function byRut(string $rut): ?Person {
		return $this->by('rut', $rut);
	}

	/** @return list<Person> every record: one per staff member */
	public function all(): array {
		$query = $this->db->getQueryBuilder();
		$query->select('*')->from($this->getTableName());
		return $this->findEntities($query);
	}

	/** Whether some record names $uid as its Jefatura. */
	public function isJefatura(string $uid): bool {
		$query = $this->db->getQueryBuilder();
		$query->select('id')->from($this->getTableName())
			->where($query->expr()->eq('jefatura', $query->createNamedParameter($uid)))
			->setMaxResults(1);
		$result = $query->executeQuery();
		$found = $result->fetch() !== false;
		$result->closeCursor();
		return $found;
	}

	/**
	 * The person's record, its row locked until the transaction ends, so that two Requests sent at
	 * the same moment are checked one after the other.
	 */
	public function lock(string $uid): ?Person {
		return $this->by('uid', $uid, true);
	}

	private function by(string $column, string $value, bool $lock = false): ?Person {
		$query = $this->db->getQueryBuilder();
		$query->select('*')->from($this->getTableName())
			->where($query->expr()->eq($column, $query->createNamedParameter($value)));
		if ($lock) {
			$query->forUpdate();
		}
		try {
			return $this->findEntity($query);
		} catch (DoesNotExistException) {
			return null;
		}
	}
}
