<?php
declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch_Tesseract\Tests\Settings;


use OCA\Files_FullTextSearch_Tesseract\Service\ConfigService;
use OCA\Files_FullTextSearch_Tesseract\Settings\Admin;
use OCP\IL10N;
use OCP\IUser;
use OCP\Settings\DeclarativeSettingsTypes;
use PHPUnit\Framework\TestCase;


class AdminTest extends TestCase {

	public function testSchemaUsesNativeAdminSettingsFields(): void {
		$configService = $this->createMock(ConfigService::class);
		$configService->method('getDefaultValue')
			->willReturnMap([
				[ConfigService::TESSERACT_ENABLED, '1'],
				[ConfigService::TESSERACT_PSM, '4'],
				[ConfigService::TESSERACT_LANG, 'eng'],
				[ConfigService::TESSERACT_CPU_BUDGET, '4'],
				[ConfigService::TESSERACT_PARALLEL_JOBS, '4'],
				[ConfigService::TESSERACT_THREADS, '1'],
				[ConfigService::TESSERACT_PDF, '0'],
				[ConfigService::TESSERACT_PDF_LIMIT, '0'],
			]);
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(static fn (string $text): string => $text);

		$schema = (new Admin($l, $configService))->getSchema();

		self::assertSame('tesseract', $schema['id']);
		self::assertSame(51, $schema['priority']);
		self::assertSame(DeclarativeSettingsTypes::SECTION_TYPE_ADMIN, $schema['section_type']);
		self::assertSame('fulltextsearch', $schema['section_id']);
		self::assertSame(DeclarativeSettingsTypes::STORAGE_TYPE_EXTERNAL, $schema['storage_type']);
		self::assertSame('Files - Tesseract OCR', $schema['title']);
		self::assertSame([
			ConfigService::TESSERACT_ENABLED,
			ConfigService::TESSERACT_PSM,
			ConfigService::TESSERACT_LANG,
			ConfigService::TESSERACT_CPU_BUDGET,
			ConfigService::TESSERACT_PARALLEL_JOBS,
			ConfigService::TESSERACT_THREADS,
			ConfigService::TESSERACT_PDF,
			ConfigService::TESSERACT_PDF_LIMIT,
		], array_column($schema['fields'], 'id'));
		self::assertSame(DeclarativeSettingsTypes::CHECKBOX, $schema['fields'][0]['type']);
		self::assertSame(1, $schema['fields'][0]['default']);
		self::assertSame(DeclarativeSettingsTypes::NUMBER, $schema['fields'][1]['type']);
		self::assertSame(4, $schema['fields'][1]['default']);
		self::assertSame(DeclarativeSettingsTypes::TEXT, $schema['fields'][2]['type']);
		self::assertSame('eng', $schema['fields'][2]['default']);
	}


	public function testGetValueUsesNormalizedConfig(): void {
		$configService = $this->createMock(ConfigService::class);
		$configService->expects(self::once())
			->method('getAppValue')
			->with(ConfigService::TESSERACT_PSM)
			->willReturn('4');
		$admin = new Admin($this->createMock(IL10N::class), $configService);

		$value = $admin->getValue(ConfigService::TESSERACT_PSM, $this->createMock(IUser::class));

		self::assertSame('4', $value);
	}


	public function testSetValueUsesConfigValidation(): void {
		$configService = $this->createMock(ConfigService::class);
		$configService->expects(self::once())
			->method('setConfig')
			->with([ConfigService::TESSERACT_PSM => 99]);
		$admin = new Admin($this->createMock(IL10N::class), $configService);

		$admin->setValue(ConfigService::TESSERACT_PSM, 99, $this->createMock(IUser::class));
	}
}
