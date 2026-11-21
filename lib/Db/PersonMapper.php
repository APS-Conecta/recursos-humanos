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

	private function by(string $column, string $value): ?Person {
		$query = $this->db->getQueryBuilder();
		$query->select('*')->from($this->getTableName())
			->where($query->expr()->eq($column, $query->createNamedParameter($value)));
		try {
			return $this->findEntity($query);
		} catch (DoesNotExistException) {
			return null;
		}
	}
}
