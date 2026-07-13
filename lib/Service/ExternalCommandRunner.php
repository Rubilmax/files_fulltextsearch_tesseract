<?php
declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch_Tesseract\Service;


use Psr\Log\LoggerInterface;


/**
 * Runs optional command-line helpers with a bounded execution time.
 */
class ExternalCommandRunner {

	private const COMMAND_TIMEOUT_SECONDS = 60;


	public function __construct(
		private LoggerInterface $logger,
		private ProcessPriorityService $processPriorityService
	) {
	}


	/**
	 * @param list<string> $command
	 *
	 * @return string|null Standard output, or null when the command is unavailable or fails.
	 */
	public function run(array $command, int $timeoutSeconds = self::COMMAND_TIMEOUT_SECONDS): ?string {
		if ($command === [] || !function_exists('proc_open')) {
			return null;
		}
		$timeoutSeconds = max(1, $timeoutSeconds);

		$commandName = $command[0];
		$command = $this->processPriorityService->prioritize($command);
		$descriptors = [
			0 => ['pipe', 'r'],
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w']
		];
		$process = @proc_open($command, $descriptors, $pipes);
		if (!is_resource($process)) {
			return null;
		}

		fclose($pipes[0]);
		stream_set_blocking($pipes[1], false);
		stream_set_blocking($pipes[2], false);
		$output = '';
		$error = '';
		$startedAt = microtime(true);
		$timedOut = false;
		do {
			$status = proc_get_status($process);
			$output .= stream_get_contents($pipes[1]);
			$error .= stream_get_contents($pipes[2]);
			if ($status['running']) {
				if (microtime(true) - $startedAt >= $timeoutSeconds) {
					$timedOut = true;
					proc_terminate($process);
					break;
				}
				usleep(10000);
			}
		} while ($status['running']);

		$output .= stream_get_contents($pipes[1]);
		$error .= stream_get_contents($pipes[2]);
		fclose($pipes[1]);
		fclose($pipes[2]);
		$closeExitCode = proc_close($process);
		$exitCode = !$timedOut && $status['exitcode'] >= 0 ? $status['exitcode'] : $closeExitCode;

		if ($timedOut || $exitCode !== 0) {
			$this->logger->debug(
					'Optional OCR helper command failed; fallback will be used',
					[
						'command' => $commandName,
					'exitCode' => $exitCode,
					'timedOut' => $timedOut,
					'error' => trim($error)
				]
			);

			return null;
		}

		return $output;
	}
}
