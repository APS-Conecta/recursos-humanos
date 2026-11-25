<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Db;

use OCP\AppFramework\Db\Entity;

/**
 * One step of a Request's trail (recursos-humanos#1, Schema): who did what, when, in what
 * capacity and, for a Jefatura, by which Delegation.
 *
 * @method int getRequestId()
 * @method void setRequestId(int $requestId)
 * @method string getKind()
 * @method void setKind(string $kind)
 * @method string getActor()
 * @method void setActor(string $actor)
 * @method string|null getCapacity()
 * @method void setCapacity(?string $capacity)
 * @method int|null getDelegationId()
 * @method void setDelegationId(?int $delegationId)
 * @method int getAt()
 * @method void setAt(int $at)
 * @method string|null getNote()
 * @method void setNote(?string $note)
 */
class RequestStep extends Entity {
	protected ?int $requestId = null;
	protected ?string $kind = null;
	protected ?string $actor = null;
	protected ?string $capacity = null;
	protected ?int $delegationId = null;
	/** Unix time. */
	protected ?int $at = null;
	protected ?string $note = null;

	public function __construct() {
		$this->addType('requestId', 'integer');
		$this->addType('delegationId', 'integer');
		$this->addType('at', 'integer');
	}
}
