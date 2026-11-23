<?php

declare(strict_types=1);

// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\RecursosHumanos\Notification;

use OCA\RecursosHumanos\AppInfo\Application;
use OCA\RecursosHumanos\Ledger\LeaveType;
use OCA\RecursosHumanos\Requests\Capacity;
use OCA\RecursosHumanos\Requests\Notices;
use OCA\RecursosHumanos\Spanish;
use OCA\RecursosHumanos\WorkingDays\Unit;
use OCP\IURLGenerator;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;
use OCP\Notification\UnknownNotificationException;

/**
 * Writes the app's notifications in Spanish, in tú, as the screens do (the app has no translation
 * layer): «Ana pide feriado legal», «Pedro aprobó tu solicitud». Each opens the page it is about.
 */
class Notifier implements INotifier {
	public function __construct(
		private IURLGenerator $urls,
	) {
	}

	public function getID(): string {
		return Application::APP_ID;
	}

	public function getName(): string {
		return 'Recursos Humanos';
	}

	public function prepare(INotification $notification, string $languageCode): INotification {
		$about = $notification->getSubjectParameters();
		$type = $notification->getApp() === Application::APP_ID ? LeaveType::tryFrom((string)($about['leaveType'] ?? '')) : null;
		if ($type === null) {
			throw new UnknownNotificationException();
		}
		$range = Spanish::range((string)$about['from'], (string)$about['to']);
		$what = sprintf('%s, %s', $type->title(), $range);
		[$subject, $message, $page] = match ($notification->getSubject()) {
			Notices::ASKED => [
				sprintf('%s pide %s', $about['name'], mb_strtolower($type->title())),
				sprintf('%s · %s', ucfirst($range), Spanish::days((float)$about['days'], Unit::from((string)$about['unit']))),
				'por-decidir',
			],
			Notices::APPROVED => [
				sprintf('%s aprobó tu solicitud', $about['name']),
				sprintf('%s, %s. Falta que Recursos Humanos la registre.', $what, Capacity::from((string)$about['capacity'])->phrase()),
				'mis-solicitudes',
			],
			Notices::REJECTED => [
				sprintf('%s rechazó tu solicitud', $about['name']),
				sprintf('%s: «%s»', $what, $about['reason']),
				'mis-solicitudes',
			],
			default => throw new UnknownNotificationException(),
		};
		return $notification
			->setParsedSubject($subject)
			->setParsedMessage($message)
			->setLink($this->urls->linkToRouteAbsolute(Application::APP_ID . '.page.index') . '#' . $page)
			->setIcon($this->urls->getAbsoluteURL($this->urls->imagePath(Application::APP_ID, 'app-dark.svg')));
	}
}
