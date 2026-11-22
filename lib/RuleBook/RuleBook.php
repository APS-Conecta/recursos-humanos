<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\RuleBook;

use APS\Common\Text;
use LogicException;
use OCA\RecursosHumanos\Db\RuleVersion;
use OCA\RecursosHumanos\Db\RuleVersionMapper;
use OCA\RecursosHumanos\ResponseDefinitions;

/**
 * RuleBook (recursos-humanos#1, Modules): the law's numbers as dated Rule versions (ADR 0002).
 *
 * - **They arrive only with app updates**: `seeds/rule-versions.json` is loaded on install and
 *   upgrade (LoadRuleVersions). A new law is a new version with its own date. A shipped version's
 *   value, date and flag are fixed, since Movements cite it; an update may reword it only. It is
 *   never deleted.
 * - **The version in force on a day** is the latest that starts on or before it; a day before a
 *   rule's first version reads that first version, the oldest the app knows.
 * - **Establishment rules** (ADR 0005) are versions too, marked as the establishment's.
 *
 * @psalm-import-type RecursosHumanosRuleVersion from ResponseDefinitions
 */
class RuleBook {
	public const ANNUAL_LEAVE_DAYS = 'annualLeaveDays';
	public const ANNUAL_LEAVE_ELIGIBILITY = 'annualLeaveEligibility';
	public const ADMINISTRATIVE_DAYS = 'administrativeDays';
	public const ADMINISTRATIVE_DAYS_MID_YEAR = 'administrativeDaysMidYear';
	public const TRAINING_DAYS = 'trainingDays';

	private const SEED = __DIR__ . '/../../seeds/rule-versions.json';

	/** @var array<string, list<RuleVersion>>|null each rule's versions, oldest first, once read */
	private ?array $versions = null;

	public function __construct(
		private RuleVersionMapper $mapper,
	) {
	}

	/**
	 * Writes the shipped versions: a new one is added whole; a known one takes only the file's
	 * words (title, summary, basis), never a new value, date or flag.
	 *
	 * @return int how many versions were new
	 */
	public function load(): int {
		$known = [];
		foreach ($this->mapper->all() as $version) {
			$known[$version->getRuleId() . '/' . $version->getVersion()] = $version;
		}
		$added = 0;
		foreach (json_decode((string)file_get_contents(self::SEED), true, flags: JSON_THROW_ON_ERROR) as $shipped) {
			$version = $known[$shipped['rule'] . '/' . $shipped['version']] ?? null;
			if ($version === null) {
				$version = new RuleVersion();
				$version->setRuleId($shipped['rule']);
				$version->setVersion($shipped['version']);
				$version->setValue(json_encode($shipped['value'], JSON_THROW_ON_ERROR));
				$version->setStartsOn($shipped['from']);
				$version->setEstablishmentRule($shipped['establishmentRule']);
			}
			$version->setTitle($shipped['title']);
			$version->setSummary($shipped['summary']);
			$version->setBasis($shipped['basis']);
			if ($version->getId() === null) {
				$this->mapper->insert($version);
				$added++;
			} else {
				$this->mapper->update($version);
			}
		}
		$this->versions = null;
		return $added;
	}

	/** The version of $rule in force on $day. */
	public function at(string $rule, string $day): RuleVersion {
		$versions = $this->versions()[$rule] ?? throw new LogicException("The app ships no version of the rule $rule");
		$inForce = $versions[0];
		foreach ($versions as $version) {
			if ($version->getStartsOn() <= $day) {
				$inForce = $version;
			}
		}
		return $inForce;
	}

	/** The title of one version, for a Movement that names it. */
	public function title(string $rule, int $version): string {
		foreach ($this->versions()[$rule] ?? [] as $known) {
			if ($known->getVersion() === $version) {
				return $known->getTitle();
			}
		}
		return $rule;
	}

	/**
	 * Normativa: every version, by title, each rule's oldest first.
	 *
	 * @return list<RecursosHumanosRuleVersion>
	 */
	public function all(): array {
		$all = array_merge(...array_values($this->versions()));
		usort($all, static fn (RuleVersion $a, RuleVersion $b): int => strcmp(Text::fold($a->getTitle()), Text::fold($b->getTitle()))
			?: $a->getVersion() <=> $b->getVersion());
		return array_map(static fn (RuleVersion $version): array => [
			'rule' => $version->getRuleId(),
			'version' => $version->getVersion(),
			'title' => $version->getTitle(),
			'summary' => $version->getSummary(),
			'basis' => $version->getBasis(),
			'from' => $version->getStartsOn(),
			'establishmentRule' => (bool)$version->getEstablishmentRule(),
		], $all);
	}

	/** @return array<string, list<RuleVersion>> */
	private function versions(): array {
		if ($this->versions === null) {
			$this->versions = [];
			foreach ($this->mapper->all() as $version) {
				$this->versions[$version->getRuleId()][] = $version;
			}
		}
		return $this->versions;
	}
}
