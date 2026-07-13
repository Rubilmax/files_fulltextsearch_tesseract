<?php
declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch_Tesseract\Tests\AppInfo;


use OCA\Files_FullTextSearch_Tesseract\AppInfo\Application;
use OCA\Files_FullTextSearch_Tesseract\Listeners\GenericListener;
use OCA\Files_FullTextSearch_Tesseract\Settings\Admin;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\GenericEvent;
use PHPUnit\Framework\TestCase;
use ReflectionClass;


class ApplicationTest extends TestCase {

	public function testRegistersDeclarativeAdminSettings(): void {
		$context = $this->createMock(IRegistrationContext::class);
		$context->expects(self::once())
			->method('registerEventListener')
			->with(GenericEvent::class, GenericListener::class);
		$context->expects(self::once())
			->method('registerDeclarativeSettings')
			->with(Admin::class);
		$application = (new ReflectionClass(Application::class))->newInstanceWithoutConstructor();

		$application->register($context);
	}
}
