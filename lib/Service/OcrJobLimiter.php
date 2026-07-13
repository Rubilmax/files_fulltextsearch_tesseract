<?php
declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch_Tesseract\Service;


use OCP\Lock\ILockingProvider;
use OCP\Lock\LockedException;
use Psr\Log\LoggerInterface;
use Throwable;


/**
 * Limits OCR work across all indexing processes using Nextcloud's locking provider.
 */
class OcrJobLimiter {

	private const LOCK_PREFIX = 'files_fulltextsearch_tesseract::ocr-slot::';
	private const INITIAL_RETRY_DELAY_MICROSECONDS = 250000;
	private const MAX_RETRY_DELAY_MICROSECONDS = 2000000;


	public function __construct(
		private ConfigService $configService,
		private ILockingProvider $lockingProvider,
		private LoggerInterface $logger
	) {
	}


	/**
	 * @param callable $job
	 *
	 * @return mixed
	 */
	public function run(callable $job): mixed {
		$slot = $this->acquireSlot();

		try {
			return $job();
		} finally {
			try {
				$this->lockingProvider->releaseLock($slot, ILockingProvider::LOCK_EXCLUSIVE);
			} catch (Throwable $e) {
				$this->logger->notice('Failed to release an OCR job slot', ['exception' => $e]);
			}
		}
	}


	/**
	 * @return string
	 */
	private function acquireSlot(): string {
		$parallelJobs = $this->configService->getEffectiveParallelJobs();
		$offset = max(0, getmypid()) % $parallelJobs;
		$waitingSince = microtime(true);
		$waitingLogged = false;
		$retryDelay = self::INITIAL_RETRY_DELAY_MICROSECONDS;

		while (true) {
			for ($i = 0; $i < $parallelJobs; $i++) {
				$slotNumber = (($offset + $i) % $parallelJobs) + 1;
				$slot = self::LOCK_PREFIX . $slotNumber;

				try {
					$this->lockingProvider->acquireLock($slot, ILockingProvider::LOCK_EXCLUSIVE);
					$this->logWaitTime($waitingSince, $slotNumber);

					return $slot;
				} catch (LockedException) {
					// Try another slot. If all are occupied, wait before retrying.
				} catch (Throwable $e) {
					$this->logger->notice('Failed to acquire an OCR job slot', ['exception' => $e]);
					throw $e;
				}
			}

			if (!$waitingLogged) {
				$this->logger->debug(
					'All OCR job slots are occupied; waiting',
					['parallelJobs' => $parallelJobs]
				);
				$waitingLogged = true;
			}

			$this->waitBeforeRetry($retryDelay);
			$retryDelay = min(self::MAX_RETRY_DELAY_MICROSECONDS, $retryDelay * 2);
			$offset = ($offset + 1) % $parallelJobs;
		}
	}


	/**
	 * Exponential backoff with jitter substantially reduces pressure on DB/Redis lock providers.
	 */
	protected function waitBeforeRetry(int $delayMicroseconds): void {
		$jitterRange = intdiv($delayMicroseconds, 5);
		$jitter = mt_rand(-$jitterRange, $jitterRange);
		usleep(max(1000, $delayMicroseconds + $jitter));
	}


	/**
	 * @param float $waitingSince
	 * @param int $slotNumber
	 */
	private function logWaitTime(float $waitingSince, int $slotNumber): void {
		$waitMilliseconds = (int)round((microtime(true) - $waitingSince) * 1000);
		$this->logger->debug(
			'Acquired OCR job slot',
			['slot' => $slotNumber, 'waitMilliseconds' => $waitMilliseconds]
		);
	}
}
