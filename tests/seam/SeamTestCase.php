<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Tests\Seam;

use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * The seam (recursos-humanos#1, Testing Decisions): a test sees «Recursos Humanos» as a person or
 * an operator does, through its OCS API and occ, inside the throwaway instance's Nextcloud container.
 * Nothing here loads an app class. Every person a test needs is a fresh account, removed when
 * its class ends.
 */
abstract class SeamTestCase extends TestCase {
	private const BASE = 'http://localhost/ocs/v2.php';
	private const OCC = '/var/www/html/occ';

	/** @var array<string, string> each account this class made, with its password */
	private static array $people = [];

	public static function tearDownAfterClass(): void {
		foreach (array_keys(self::$people) as $name) {
			self::occ('user:delete', $name);
		}
		self::$people = [];
	}

	/** A fresh account, unique to this run, and its login name. */
	protected static function person(): string {
		$name = 'person-' . bin2hex(random_bytes(4));
		$password = bin2hex(random_bytes(16));
		self::occWithEnv(['OC_PASS' => $password], 'user:add', '--password-from-env', $name);
		self::$people[$name] = $password;
		return $name;
	}

	/** The person joins a Nextcloud group, such as a cargo group the Provisionador creates. */
	protected static function joins(string $person, string $group): void {
		self::occ('group:adduser', $group, $person);
	}

	/**
	 * A complete record for the account, with these fields changed.
	 *
	 * @param array<string, mixed> $change
	 * @return array<string, mixed>
	 */
	protected static function record(string $uid, array $change = []): array {
		return array_replace(['uid' => $uid, 'rut' => self::rut(), 'givenNames' => 'Valentina Paz', 'surnames' => 'Soto Ibarra',
			'startDate' => '2019-03-04', 'priorMonths' => 26, 'shift' => false, 'jefatura' => null,
			'regime' => 'estatutoAps', 'regimeFrom' => '2019-03-04'], $change);
	}

	/** A RUT no other run has used, in its canonical form 12345678-5. */
	protected static function rut(): string {
		$body = (string)random_int(10_000_000, 29_999_999);
		$sum = 0;
		foreach (array_reverse(str_split($body)) as $i => $digit) {
			$sum += (int)$digit * (2 + $i % 6);
		}
		$verifier = 11 - $sum % 11;
		return $body . '-' . ($verifier === 11 ? '0' : ($verifier === 10 ? 'K' : (string)$verifier));
	}

	/** occ as the web server's user; a failing command fails the test with its output. */
	protected static function occ(string ...$args): string {
		return self::occWithEnv([], ...$args);
	}

	/** @param array<string, string> $env extra environment for occ */
	private static function occWithEnv(array $env, string ...$args): string {
		$process = proc_open(['php', self::OCC, '--no-interaction', ...$args], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $env + getenv());
		if ($process === false) {
			throw new RuntimeException('occ did not start');
		}
		$out = (string)stream_get_contents($pipes[1]) . (string)stream_get_contents($pipes[2]);
		$code = proc_close($process);
		if ($code !== 0) {
			throw new RuntimeException('occ ' . implode(' ', $args) . " exited $code: $out");
		}
		return $out;
	}

	/**
	 * An OCS call as the person (null: no credentials), with a JSON body when one is given,
	 * answered as {status, data}.
	 *
	 * @param array<string, mixed>|null $body
	 * @return array{status: int, data: mixed}
	 */
	protected static function ocs(?string $person, string $method, string $path, ?array $body = null): array {
		$curl = curl_init(self::BASE . $path);
		$headers = ['OCS-APIRequest: true', 'Accept: application/json'];
		if ($body !== null) {
			$headers[] = 'Content-Type: application/json';
			curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
		}
		curl_setopt_array($curl, [
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 60,
			CURLOPT_HTTPHEADER => $headers,
		]);
		if ($person !== null) {
			curl_setopt($curl, CURLOPT_USERPWD, $person . ':' . self::$people[$person]);
		}
		$text = curl_exec($curl);
		if ($text === false) {
			throw new RuntimeException("$method $path: " . curl_error($curl));
		}
		$json = json_decode((string)$text, true);
		return [
			'status' => (int)curl_getinfo($curl, CURLINFO_RESPONSE_CODE),
			'data' => is_array($json) ? ($json['ocs']['data'] ?? null) : null,
		];
	}
}
