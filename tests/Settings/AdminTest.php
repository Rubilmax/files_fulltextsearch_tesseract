<?php
declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch_Tesseract\Tests\Settings;


use OCA\Files_FullTextSearch_Tesseract\AppInfo\Application;
use OCA\Files_FullTextSearch_Tesseract\Service\ConfigService;
use OCA\Files_FullTextSearch_Tesseract\Settings\Admin;
use PHPUnit\Framework\TestCase;


class AdminTest extends TestCase {

	public function testFormReceivesEffectiveSettings(): void {
		$settings = [
			ConfigService::TESSERACT_ENABLED => '1',
			ConfigService::TESSERACT_PSM => '4',
			ConfigService::TESSERACT_LANG => 'eng',
		];
		$configService = $this->createMock(ConfigService::class);
		$configService->expects(self::once())
			->method('getConfig')
			->willReturn($settings);

		$response = (new Admin($configService))->getForm();

		self::assertSame(Application::APP_ID, $response->getApp());
		self::assertSame('settings.admin', $response->getTemplateName());
		self::assertSame(['settings' => $settings], $response->getParams());
	}
}
