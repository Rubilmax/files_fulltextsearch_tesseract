<?php
declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch_Tesseract\Tests\Service;


use OCA\Files_FullTextSearch_Tesseract\Service\ConfigService;
use OCP\AppFramework\Services\IAppConfig;
use PHPUnit\Framework\TestCase;


class ConfigServiceTest extends TestCase {

	/** @var array<string, string> */
	private array $values;
	private ConfigService $service;


	protected function setUp(): void {
		$this->values = [];
		$appConfig = $this->createMock(IAppConfig::class);
		$appConfig->method('getAppValueString')->willReturnCallback(
			fn (string $key, string $default): string => $this->values[$key] ?? $default
		);
		$appConfig->method('setAppValueString')->willReturnCallback(
			function (string $key, string $value): bool {
				$changed = ($this->values[$key] ?? null) !== $value;
				$this->values[$key] = $value;

				return $changed;
			}
		);

		$this->service = new ConfigService($appConfig);
	}


	public function testDefaultsAreSafe(): void {
		self::assertFalse($this->service->isEnabled());
		self::assertFalse($this->service->isPdfEnabled());
		self::assertTrue($this->service->shouldSkipPdfText());
		self::assertSame(4, $this->service->getPageSegmentationMode());
		self::assertSame(['eng'], $this->service->getLanguages());
		self::assertSame(0, $this->service->getPdfPageLimit());
		self::assertGreaterThanOrEqual(1, $this->service->getCpuBudget());
		self::assertGreaterThanOrEqual(1, $this->service->getParallelJobs());
		self::assertSame(1, $this->service->getThreadLimit());
		self::assertLessThanOrEqual(
			$this->service->getCpuBudget(),
			$this->service->getEffectiveParallelJobs() * $this->service->getEffectiveThreadLimit()
		);
	}


	public function testSettingsAreNormalizedAndBounded(): void {
		$this->service->setConfig([
			ConfigService::TESSERACT_ENABLED => 'true',
			ConfigService::TESSERACT_PDF => 1,
			ConfigService::TESSERACT_PDF_SKIP_TEXT => 'no',
			ConfigService::TESSERACT_PSM => '13; touch /tmp/injected',
			ConfigService::TESSERACT_LANG => 'eng, fra;rm -rf /,deu,script/Latin,eng',
			ConfigService::TESSERACT_PDF_LIMIT => '999999999',
			ConfigService::TESSERACT_CPU_BUDGET => '999999999',
			ConfigService::TESSERACT_PARALLEL_JOBS => '999999999',
			ConfigService::TESSERACT_THREADS => '-20',
		]);

		self::assertTrue($this->service->isEnabled());
		self::assertTrue($this->service->isPdfEnabled());
		self::assertFalse($this->service->shouldSkipPdfText());
		self::assertSame(4, $this->service->getPageSegmentationMode());
		self::assertSame(['eng', 'deu', 'script/Latin'], $this->service->getLanguages());
		self::assertSame(100000, $this->service->getPdfPageLimit());
		self::assertSame(256, $this->service->getCpuBudget());
		self::assertSame(256, $this->service->getParallelJobs());
		self::assertSame(1, $this->service->getThreadLimit());
	}


	public function testEffectiveLimitsCannotExceedCpuBudget(): void {
		$this->service->setConfig([
			ConfigService::TESSERACT_CPU_BUDGET => '5',
			ConfigService::TESSERACT_PARALLEL_JOBS => '12',
			ConfigService::TESSERACT_THREADS => '3',
		]);

		self::assertSame(3, $this->service->getEffectiveThreadLimit());
		self::assertSame(1, $this->service->getEffectiveParallelJobs());

		$this->service->setConfig([ConfigService::TESSERACT_THREADS => '12']);
		self::assertSame(5, $this->service->getEffectiveThreadLimit());
		self::assertSame(1, $this->service->getEffectiveParallelJobs());
	}


	public function testUnsafeLegacyValuesAreNormalizedWhenRead(): void {
		$this->values[ConfigService::TESSERACT_LANG] = 'eng; touch /tmp/injected';
		$this->values[ConfigService::TESSERACT_PSM] = '4; whoami';

		self::assertSame(['eng'], $this->service->getLanguages());
		self::assertSame(4, $this->service->getPageSegmentationMode());
		self::assertSame('eng', $this->service->getConfig()[ConfigService::TESSERACT_LANG]);
	}
}
