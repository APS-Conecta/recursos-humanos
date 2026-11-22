<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Db;

use OCP\AppFramework\Db\Entity;

/**
 * One dated line of the Ledger: it changes one Balance, a person's Leave type in one period
 * (ADR 0001).
 *
 * @method string getUid()
 * @method void setUid(string $uid)
 * @method string getLeaveType()
 * @method void setLeaveType(string $leaveType)
 * @method int getPeriodYear()
 * @method void setPeriodYear(int $periodYear)
 * @method string getEffectiveOn()
 * @method void setEffectiveOn(string $effectiveOn)
 * @method string getKind()
 * @method void setKind(string $kind)
 * @method float getQuantity()
 * @method void setQuantity(float $quantity)
 * @method string|null getActor()
 * @method void setActor(?string $actor)
 * @method string getReason()
 * @method void setReason(string $reason)
 * @method string|null getRuleId()
 * @method void setRuleId(?string $ruleId)
 * @method int|null getRuleVersion()
 * @method void setRuleVersion(?int $ruleVersion)
 * @method string|null getOnceKey()
 * @method void setOnceKey(?string $onceKey)
 */
class Movement extends Entity {
	protected ?string $uid = null;
	protected ?string $leaveType = null;
	protected ?int $periodYear = null;
	protected ?string $effectiveOn = null;
	protected ?string $kind = null;
	protected ?float $quantity = null;
	/** The account that wrote it; null when the app wrote it on its own. */
	protected ?string $actor = null;
	protected ?string $reason = null;
	protected ?string $ruleId = null;
	protected ?int $ruleVersion = null;
	/** Set on a Movement a person may have only once, such as a year's grant. */
	protected ?string $onceKey = null;

	public function __construct() {
		$this->addType('periodYear', 'integer');
		$this->addType('quantity', 'float');
		$this->addType('ruleVersion', 'integer');
	}
}
