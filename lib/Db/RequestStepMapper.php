<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/** @template-extends QBMapper<RequestStep> */
class RequestStepMapper extends QBMapper {
	public function __construct(IDBConnection $db) {
		parent::__construct($db, 'rh_request_steps', RequestStep::class);
	}

	/**
	 * @param list<int> $requests
	 * @return array<int, list<RequestStep>> each Request's trail, oldest step first
	 */
	public function of(array $requests): array {
		$trails = array_fill_keys($requests, []);
		if ($requests === []) {
			return $trails;
		}
		$query = $this->db->getQueryBuilder();
		$query->select('*')->from($this->getTableName())
			->where($query->expr()->in('request_id', $query->createNamedParameter($requests, IQueryBuilder::PARAM_INT_ARRAY)))
			->orderBy('id', 'ASC');
		foreach ($this->findEntities($query) as $step) {
			$trails[$step->getRequestId()][] = $step;
		}
		return $trails;
	}
}
