<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Db;

use OCP\AppFramework\Db\Entity;

/**
 * One step of a person record's trail: what changed, who changed it and when.
 *
 * @method string getUid()
 * @method void setUid(string $uid)
 * @method string getKind()
 * @method void setKind(string $kind)
 * @method string getActor()
 * @method void setActor(string $actor)
 * @method int getAt()
 * @method void setAt(int $at)
 * @method string|null getDetail()
 * @method void setDetail(?string $detail)
 * @method string|null getNote()
 * @method void setNote(?string $note)
 */
class PersonLogEntry extends Entity {
	protected ?string $uid = null;
	/** 'completed' or 'regime' (People\People); later tickets add their own. */
	protected ?string $kind = null;
	protected ?string $actor = null;
	protected ?int $at = null;
	/** What the step set, as JSON. */
	protected ?string $detail = null;
	/** The reason the HR officer wrote. */
	protected ?string $note = null;

	public function __construct() {
		$this->addType('at', 'integer');
	}
}
