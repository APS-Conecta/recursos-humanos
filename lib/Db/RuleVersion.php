<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Db;

use OCP\AppFramework\Db\Entity;

/**
 * One dated version of a legal or establishment rule, as the app ships it (ADR 0002).
 *
 * @method string getRuleId()
 * @method void setRuleId(string $ruleId)
 * @method int getVersion()
 * @method void setVersion(int $version)
 * @method string getTitle()
 * @method void setTitle(string $title)
 * @method string getSummary()
 * @method void setSummary(string $summary)
 * @method string getValue()
 * @method void setValue(string $value)
 * @method string getBasis()
 * @method void setBasis(string $basis)
 * @method string getStartsOn()
 * @method void setStartsOn(string $startsOn)
 * @method bool|null getEstablishment()
 * @method void setEstablishment(bool $establishment)
 */
class RuleVersion extends Entity {
	protected ?string $ruleId = null;
	protected ?int $version = null;
	protected ?string $title = null;
	/** What a person reads: the value in words. */
	protected ?string $summary = null;
	/** What the app reads, as JSON. */
	protected ?string $value = null;
	protected ?string $basis = null;
	protected ?string $startsOn = null;
	protected ?bool $establishment = null;

	public function __construct() {
		$this->addType('version', 'integer');
		$this->addType('establishment', 'boolean');
	}
}
