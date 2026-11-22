<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Tests\Unit;

use DOMDocument;
use LibXMLError;
use OCA\RecursosHumanos\Controller\ApiController;
use OCA\RecursosHumanos\Controller\PageController;
use OCP\AppFramework\Http\Attribute\ApiRoute;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\UserRateLimit;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * The repo says true things about itself, and this file fails when they stop being true
 * (Estadística's MetadataTest, which is farmacia's, cut to what «Recursos Humanos» promises so far).
 */
class MetadataTest extends TestCase {
	private const APP_DIR = __DIR__ . '/../..';

	private function infoXml(): DOMDocument {
		$doc = new DOMDocument();
		self::assertTrue($doc->load(self::APP_DIR . '/appinfo/info.xml'), 'appinfo/info.xml must parse');
		return $doc;
	}

	private function text(string $tag): string {
		$nodes = $this->infoXml()->getElementsByTagName($tag);
		self::assertGreaterThan(0, $nodes->length, "info.xml must carry <$tag>");
		return trim((string)$nodes->item(0)->textContent);
	}

	private function json(string $file): array {
		return json_decode((string)file_get_contents(self::APP_DIR . '/' . $file), true, 512, JSON_THROW_ON_ERROR);
	}

	/** @return list<ReflectionMethod> the public actions a class declares itself */
	private function actions(string $class): array {
		$actions = [];
		foreach ((new ReflectionClass($class))->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
			if ($method->getDeclaringClass()->getName() === $class && !str_starts_with($method->getName(), '__')) {
				$actions[] = $method;
			}
		}
		return $actions;
	}

	/**
	 * The app store validates info.xml against its app schema at upload, which fixes each element's
	 * order: a wrong order passes every other gate (recursos-humanos#30). The schema is committed
	 * beside this test, from https://apps.nextcloud.com/schema/apps/info.xsd.
	 */
	public function testInfoXmlFollowsTheAppSchema(): void {
		$previous = libxml_use_internal_errors(true);
		$valid = $this->infoXml()->schemaValidate(__DIR__ . '/info.xsd');
		$errors = array_map(static fn (LibXMLError $error): string => trim($error->message) . " (line $error->line)", libxml_get_errors());
		libxml_clear_errors();
		libxml_use_internal_errors($previous);

		self::assertTrue($valid, "appinfo/info.xml breaks the app schema:\n" . implode("\n", $errors));
	}

	/** The release tag must be v + info.xml's version (pack-app), so every other copy agrees. */
	public function testEveryFileThatDeclaresAVersionDeclaresTheSameOne(): void {
		$version = $this->text('version');

		self::assertSame($version, $this->json('package.json')['version'], 'package.json declares a version info.xml does not');
		self::assertSame($version, $this->json('openapi.json')['info']['version'], 'openapi.json is generated: run make openapi');
	}

	public function testEveryDescriptionCarriesTheSummary(): void {
		$summary = $this->text('summary');

		self::assertSame($summary, $this->json('openapi.json')['info']['description'], 'openapi.json is generated: run make openapi');
		self::assertSame($summary, $this->json('package.json')['description']);
		self::assertSame($summary, $this->json('composer.json')['description']);
	}

	public function testTheDeclaredLicenceIsTheOneShipped(): void {
		self::assertSame('AGPL-3.0-or-later', $this->text('licence'));
		$text = (string)file_get_contents(self::APP_DIR . '/LICENSE');
		self::assertStringContainsString('GNU AFFERO GENERAL PUBLIC LICENSE', substr($text, 0, 200));
		self::assertGreaterThan(30_000, strlen($text), 'the AGPL text is long; a short file means the licence was not copied');
	}

	public function testEveryPhpFileUnderLibCarriesTheSpdxHeader(): void {
		$files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::APP_DIR . '/lib', \FilesystemIterator::SKIP_DOTS));
		$checked = 0;
		foreach ($files as $file) {
			if ($file->getExtension() === 'php') {
				$checked++;
				self::assertStringContainsString('SPDX-License-Identifier: AGPL-3.0-or-later',
					(string)file_get_contents($file->getPathname(), length: 4096), "{$file->getPathname()} must carry the SPDX header");
			}
		}
		self::assertGreaterThan(0, $checked);
	}

	/** Every person asks for days and sees their own: the page is open to every signed-in person. */
	public function testThePageIsOpenToEveryone(): void {
		foreach ($this->actions(PageController::class) as $method) {
			self::assertCount(1, $method->getAttributes(NoAdminRequired::class),
				PageController::class . '::' . $method->getName() . ' must be #[NoAdminRequired]');
		}
	}

	/**
	 * Every OCS route is routed by its attribute, open to every signed-in person and
	 * rate-limited: who may see what is checked inside the action. An attribute deleted from one
	 * method is a route that is gone, admin-only or unbounded, silently (farmacia's gate).
	 */
	public function testEveryOcsRouteIsRoutedOpenAndRateLimited(): void {
		$actions = $this->actions(ApiController::class);
		self::assertNotEmpty($actions);

		foreach ($actions as $method) {
			$name = ApiController::class . '::' . $method->getName();
			self::assertCount(1, $method->getAttributes(ApiRoute::class), "$name must carry #[ApiRoute]");
			self::assertCount(1, $method->getAttributes(NoAdminRequired::class), "$name must be #[NoAdminRequired]");
			self::assertCount(1, $method->getAttributes(UserRateLimit::class), "$name must carry #[UserRateLimit]");
		}
	}

	/**
	 * The contract outside systems read (recursos-humanos#1, API contract) says what the routes do:
	 * an enum's default is one of its values, and a route with a body names the fields it needs. A
	 * parameter given a PHP default to dodge a missing field otherwise reaches openapi.json as
	 * optional, with a default no caller may send (rh#3's first cut).
	 */
	public function testTheOpenApiContractNamesRequiredFieldsAndValidDefaults(): void {
		$spec = $this->json('openapi.json');

		$schemas = [];
		$collect = static function (array $node, string $at) use (&$collect, &$schemas): void {
			if (isset($node['enum'])) {
				$schemas[$at] = $node;
			}
			foreach ($node as $key => $child) {
				if (is_array($child)) {
					$collect($child, "$at/$key");
				}
			}
		};
		$collect($spec, '');
		self::assertNotEmpty($schemas, 'openapi.json has enums to check');
		foreach ($schemas as $at => $schema) {
			if (!array_key_exists('default', $schema)) {
				continue;
			}
			$allowed = ($schema['nullable'] ?? false) ? [...$schema['enum'], null] : $schema['enum'];
			self::assertContains($schema['default'], $allowed, "$at: the default is not one of its enum values");
		}

		$bodies = 0;
		foreach ($spec['paths'] as $path => $operations) {
			foreach ($operations as $verb => $operation) {
				$body = $operation['requestBody']['content']['application/json']['schema'] ?? null;
				if ($body !== null) {
					$bodies++;
					self::assertNotEmpty($body['required'] ?? [], strtoupper($verb) . " $path takes a body but requires none of its fields");
				}
			}
		}
		self::assertGreaterThan(0, $bodies, 'openapi.json has routes with a body to check');
	}
}
