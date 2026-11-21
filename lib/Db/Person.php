<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Db;

use OCP\AppFramework\Db\Entity;

/**
 * A Person's record (CONTEXT.md, ADR 0006): the account it belongs to, the RUT that keys it and
 * the facts HR enters once. Its Regime lines and its trail have their own tables.
 *
 * @method string getUid()
 * @method void setUid(string $uid)
 * @method string getRut()
 * @method void setRut(string $rut)
 * @method string getGivenNames()
 * @method void setGivenNames(string $givenNames)
 * @method string getSurnames()
 * @method void setSurnames(string $surnames)
 * @method string getStartDate()
 * @method void setStartDate(string $startDate)
 * @method int getPriorMonths()
 * @method void setPriorMonths(int $priorMonths)
 * @method bool|null getShift()
 * @method void setShift(bool $shift)
 * @method string|null getJefatura()
 * @method void setJefatura(?string $jefatura)
 */
class Person extends Entity {
	// Null until set: Entity writes only the fields a setter changed.
	protected ?string $uid = null;
	protected ?string $rut = null;
	protected ?string $givenNames = null;
	protected ?string $surnames = null;
	/** Y-m-d */
	protected ?string $startDate = null;
	/** Prior public service, in months. */
	protected ?int $priorMonths = null;
	/** Shift staff (CONTEXT.md, ADR 0005): Annual leave is counted Monday to Friday and HR corrects it. */
	protected ?bool $shift = null;
	/** The Jefatura's uid; null sends the person's Requests to the Director. */
	protected ?string $jefatura = null;

	public function __construct() {
		$this->addType('priorMonths', 'integer');
		$this->addType('shift', 'boolean');
	}
}
