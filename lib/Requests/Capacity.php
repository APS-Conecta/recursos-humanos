<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Requests;

/** In what capacity a person's Requests are decided (recursos-humanos#1, Deciders); the Stand-in and «(S)» arrive with recursos-humanos#13. */
enum Capacity: string {
	/** A Jefatura, on the Director's Delegation. */
	case Delegation = 'delegation';
	/** The Director, in their own power: for a person without a Jefatura, or one whose Jefatura is the Director. */
	case Director = 'director';
	/** The Director's own Requests, decided outside the app; HR registers them with the outside document. */
	case External = 'external';

	/** How a decision reads: «Aprobó por delegación del Director/a». */
	public function phrase(): string {
		return match ($this) {
			self::Delegation => 'por delegación del Director/a',
			self::Director => 'como Director/a',
			self::External => 'fuera de la app',
		};
	}
}
