<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Db;

use OCP\AppFramework\Db\Entity;

/**
 * The Director's Delegation (CONTEXT.md): the resolution that lets the Jefaturas decide on the
 * Director's behalf, as HR recorded it. A new one is a new row; the latest is in force.
 *
 * @method string getNumber()
 * @method void setNumber(string $number)
 * @method string getGrantedOn()
 * @method void setGrantedOn(string $grantedOn)
 * @method string getRecordedBy()
 * @method void setRecordedBy(string $recordedBy)
 * @method int getRecordedAt()
 * @method void setRecordedAt(int $recordedAt)
 */
class Delegation extends Entity {
	protected ?string $number = null;
	/** Y-m-d, the resolution's date. */
	protected ?string $grantedOn = null;
	protected ?string $recordedBy = null;
	/** Unix time. */
	protected ?int $recordedAt = null;

	public function __construct() {
		$this->addType('recordedAt', 'integer');
	}
}
