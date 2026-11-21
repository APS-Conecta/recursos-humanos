<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\People;

use OCA\RecursosHumanos\Refused;

/**
 * The RUT, the person key (ADR 0006): a body of 7 or 8 digits and its verifier digit, módulo 11.
 * Typed with or without dots, spaces and the dash; kept and answered as 12345678-5.
 */
final class Rut {
	private const MALFORMED = 'Escribe el RUT con su dígito verificador, por ejemplo 12.345.678-5.';
	private const WRONG_DIGIT = 'El dígito verificador no corresponde a ese RUT. Revísalo en el documento.';

	/**
	 * The canonical form of a typed RUT.
	 *
	 * @throws Refused when it is not a RUT, or its verifier digit is wrong
	 */
	public static function canonical(string $typed): string {
		$clean = strtoupper((string)preg_replace('/[\s.\-]/', '', $typed));
		if (preg_match('/^0*([1-9]\d{6,7})([\dK])$/', $clean, $parts) !== 1) {
			throw new Refused(self::MALFORMED);
		}
		[, $body, $verifier] = $parts;
		if (self::verifier($body) !== $verifier) {
			throw new Refused(self::WRONG_DIGIT);
		}
		return "$body-$verifier";
	}

	private static function verifier(string $body): string {
		$sum = 0;
		$factor = 2;
		foreach (array_reverse(str_split($body)) as $digit) {
			$sum += (int)$digit * $factor;
			$factor = $factor === 7 ? 2 : $factor + 1;
		}
		$rest = 11 - $sum % 11;
		return match ($rest) {
			11 => '0',
			10 => 'K',
			default => (string)$rest,
		};
	}
}
