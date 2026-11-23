<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Db;

use OCP\AppFramework\Db\Entity;

/**
 * A person's ask for days of one Leave type (CONTEXT.md, Request): the range, the days it counts
 * and its state. Its steps live in the trail table.
 *
 * @method string getUid()
 * @method void setUid(string $uid)
 * @method string getLeaveType()
 * @method void setLeaveType(string $leaveType)
 * @method string getStartsOn()
 * @method void setStartsOn(string $startsOn)
 * @method string getEndsOn()
 * @method void setEndsOn(string $endsOn)
 * @method float getDays()
 * @method void setDays(float $days)
 * @method string getState()
 * @method void setState(string $state)
 * @method string|null getComment()
 * @method void setComment(?string $comment)
 * @method string getRuleVersions()
 * @method void setRuleVersions(string $ruleVersions)
 */
class Request extends Entity {
	protected ?string $uid = null;
	protected ?string $leaveType = null;
	/** Y-m-d, both counted. */
	protected ?string $startsOn = null;
	protected ?string $endsOn = null;
	protected ?float $days = null;
	protected ?string $state = null;
	protected ?string $comment = null;
	/** JSON [{rule, version}]. */
	protected ?string $ruleVersions = null;

	public function __construct() {
		$this->addType('days', 'float');
	}

	/** @return list<array{rule: string, version: int}> */
	public function cited(): array {
		return json_decode((string)$this->ruleVersions, true, flags: JSON_THROW_ON_ERROR);
	}
}
