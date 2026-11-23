<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Requests;

use DateTime;
use OCA\RecursosHumanos\AppInfo\Application;
use OCA\RecursosHumanos\Db\Request;
use OCA\RecursosHumanos\WorkingDays\Unit;
use OCP\AppFramework\Utility\ITimeFactory;
use OCP\Notification\IManager;
use OCP\Notification\INotification;

/**
 * The Nextcloud notifications a Request sends (recursos-humanos#1, Nextcloud integration; stories
 * 18, 39): its deciders hear of the ask, the person of the decision. Each carries what the
 * Notifier writes in Spanish, and a decision clears the ask from every decider's list.
 */
class Notices {
	public const OBJECT = 'request';
	public const ASKED = 'asked';
	public const APPROVED = 'approved';
	public const REJECTED = 'rejected';

	public function __construct(
		private IManager $notifications,
		private ITimeFactory $time,
	) {
	}

	/** Each of the deciders hears that $name asks, and for how many days. */
	public function asked(Request $request, Unit $unit, string $name, Decider $decider): void {
		foreach ($decider->uids as $uid) {
			$this->notifications->notify($this->notification($request, $uid, self::ASKED, ['name' => $name, 'unit' => $unit->value]));
		}
	}

	/** The ask leaves every decider's list, and the person hears the decision. */
	public function decided(Request $request, string $name, StepKind $kind, Capacity $capacity, ?string $reason): void {
		$ask = $this->notifications->createNotification();
		$ask->setApp(Application::APP_ID)->setObject(self::OBJECT, (string)$request->getId())->setSubject(self::ASKED);
		$this->notifications->markProcessed($ask);

		$this->notifications->notify($this->notification($request, $request->getUid(), $kind === StepKind::Approved ? self::APPROVED : self::REJECTED,
			['name' => $name, 'capacity' => $capacity->value, 'reason' => $reason]));
	}

	/** @param array<string, string|null> $about */
	private function notification(Request $request, string $to, string $subject, array $about): INotification {
		return $this->notifications->createNotification()
			->setApp(Application::APP_ID)
			->setUser($to)
			->setDateTime(new DateTime('@' . $this->time->getTime()))
			->setObject(self::OBJECT, (string)$request->getId())
			->setSubject($subject, $about + [
				'leaveType' => $request->getLeaveType(),
				'from' => $request->getStartsOn(),
				'to' => $request->getEndsOn(),
				'days' => $request->getDays(),
			]);
	}
}
