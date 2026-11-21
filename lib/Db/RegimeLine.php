<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Db;

use OCP\AppFramework\Db\Entity;

/**
 * One dated Regime line (CONTEXT.md): the person's Regime from that date on. A change is a new
 * line; the earlier ones stay.
 *
 * @method string getUid()
 * @method void setUid(string $uid)
 * @method string getRegime()
 * @method void setRegime(string $regime)
 * @method string getStartsOn()
 * @method void setStartsOn(string $startsOn)
 */
class RegimeLine extends Entity {
	protected ?string $uid = null;
	/** A People\Regime value. */
	protected ?string $regime = null;
	/** Y-m-d */
	protected ?string $startsOn = null;
}
