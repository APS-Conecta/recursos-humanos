<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * What a person reads is Spanish, written as literals, in tú as the mockup writes it (AGENTS.md,
 * Strings; recursos-humanos#1, Testing Decisions). Two mechanical halves of that rule: no
 * translation layer, and no «usted» imperative opening a sentence.
 */
class StringsTest extends TestCase {
	private const APP_DIR = __DIR__ . '/../..';
	/** A translation call or import, PHP or JS. */
	private const TRANSLATION = '/\bIL10N\b|->t\(|->n\(|@nextcloud\/l10n/';
	/** The formal imperatives a sibling's copy would bring in, opening a sentence. */
	private const USTED = '/(?:^|[.!?¿¡«"\'>])\s*(Escriba|Elija|Revise|Ingrese|Seleccione|Intente|Recargue|Avise|Vuelva|Agregue|Cambie|Descargue|Suba|Guarde|Confirme|Pida)\b/mu';

	/** @return array<string, string> each file a person's text lives in, by its path in the repo */
	private function sources(): array {
		$files = ['appinfo/info.xml' => self::APP_DIR . '/appinfo/info.xml'];
		foreach (['lib' => '/\.php$/', 'src' => '/\.(js|vue)$/', 'templates' => '/\.php$/'] as $dir => $pattern) {
			$found = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(self::APP_DIR . "/$dir", RecursiveDirectoryIterator::SKIP_DOTS));
			foreach ($found as $file) {
				$path = substr($file->getPathname(), strlen(self::APP_DIR) + 1);
				// src/aps/ is aps-common's copy (make aps-sync): its text is the package's.
				if (preg_match($pattern, $path) === 1 && !str_starts_with($path, 'src/aps/')) {
					$files[$path] = $file->getPathname();
				}
			}
		}
		self::assertArrayHasKey('lib/Controller/ApiController.php', $files);
		self::assertArrayHasKey('src/App.vue', $files);
		return $files;
	}

	public function testTheAppHasNoTranslationLayer(): void {
		self::assertDirectoryDoesNotExist(self::APP_DIR . '/l10n', 'the screens are Spanish literals; there is nothing to translate');
		foreach ($this->sources() as $path => $file) {
			self::assertDoesNotMatchRegularExpression(self::TRANSLATION, (string)file_get_contents($file),
				"$path calls a translation API: write the Spanish text as a literal");
		}
	}

	public function testThePersonIsAddressedAsTu(): void {
		foreach ($this->sources() as $path => $file) {
			preg_match_all(self::USTED, (string)file_get_contents($file), $found);
			self::assertSame([], $found[1], "$path speaks as «usted»: write it in tú (Escribe, Elige, Revisa…)");
		}
	}
}
