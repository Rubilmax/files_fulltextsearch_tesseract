<?php
declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch_Tesseract\Tests\Service;


use OCA\Files_FullTextSearch_Tesseract\Service\ConfigService;
use OCA\Files_FullTextSearch_Tesseract\Service\OcrJobLimiter;
use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;


class OcrJobLimiterTest extends TestCase {

	public function testReleasesSlotAfterSuccessfulJob(): void {
		$config = $this->createMock(ConfigService::class);
		$config->method('getEffectiveParallelJobs')->willReturn(1);
		$lockedSlot = null;
		$lockingProvider = $this->createMock(ILockingProvider::class);
		$lockingProvider->expects(self::once())->method('acquireLock')
			->willReturnCallback(function (string $slot) use (&$lockedSlot): void {
				$lockedSlot = $slot;
			});
		$lockingProvider->expects(self::once())->method('releaseLock')
			->willReturnCallback(function (string $slot) use (&$lockedSlot): void {
				self::assertSame($lockedSlot, $slot);
			});
		$logger = $this->createMock(LoggerInterface::class);

		$limiter = new OcrJobLimiter($config, $lockingProvider, $logger);
		self::assertSame('done', $limiter->run(static fn (): string => 'done'));
	}


	public function testReleasesSlotAfterFailedJob(): void {
		$config = $this->createMock(ConfigService::class);
		$config->method('getEffectiveParallelJobs')->willReturn(1);
		$lockingProvider = $this->createMock(ILockingProvider::class);
		$lockingProvider->expects(self::once())->method('acquireLock');
		$lockingProvider->expects(self::once())->method('releaseLock');
		$logger = $this->createMock(LoggerInterface::class);
		$limiter = new OcrJobLimiter($config, $lockingProvider, $logger);

		$this->expectException(\RuntimeException::class);
		$limiter->run(static function (): void {
			throw new \RuntimeException('failed job');
		});
	}


	public function testBacksOffExponentiallyWhenAllSlotsAreOccupied(): void {
		$config = $this->createMock(ConfigService::class);
		$config->method('getEffectiveParallelJobs')->willReturn(2);
		$attempt = 0;
		$lockingProvider = $this->createMock(ILockingProvider::class);
		$lockingProvider->expects(self::exactly(5))->method('acquireLock')
			->willReturnCallback(function (string $slot) use (&$attempt): void {
				$attempt++;
				if ($attempt <= 4) {
					throw new LockedException($slot);
				}
			});
		$lockingProvider->expects(self::once())->method('releaseLock');
		$limiter = new class(
			$config,
			$lockingProvider,
			$this->createMock(LoggerInterface::class)
		) extends OcrJobLimiter {
			/** @var list<int> */
			public array $retryDelays = [];

			protected function waitBeforeRetry(int $delayMicroseconds): void {
				$this->retryDelays[] = $delayMicroseconds;
			}
		};

		self::assertSame('done', $limiter->run(static fn (): string => 'done'));
		self::assertSame([250000, 500000], $limiter->retryDelays);
	}
}
