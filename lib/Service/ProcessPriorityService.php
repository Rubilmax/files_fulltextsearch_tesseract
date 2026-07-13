<?php
declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch_Tesseract\Service;


/**
 * Launches OCR helper commands at a lower scheduler priority when POSIX nice is available.
 */
class ProcessPriorityService {

	private const NICE_LEVEL = 10;

	private ?string $niceExecutable = null;
	private bool $niceExecutableResolved = false;


	/**
	 * @param list<string> $command
	 *
	 * @return list<string>
	 */
	public function prioritize(array $command): array {
		$niceExecutable = $this->getNiceExecutable();
		if ($niceExecutable === null || $command === []) {
			return $command;
		}

		return [
			$niceExecutable,
			'-n',
			(string)self::NICE_LEVEL,
			...$command,
		];
	}


	/**
	 * Preserve the OpenMP environment assignment emitted by the Tesseract wrapper before nice.
	 */
	public function prioritizeShellCommand(string $command): string {
		$niceExecutable = $this->getNiceExecutable();
		if ($niceExecutable === null || $command === '') {
			return $command;
		}

		$environment = '';
		if (preg_match('/^(OMP_THREAD_LIMIT=\d+\s+)(.*)$/sD', $command, $matches) === 1) {
			$environment = $matches[1];
			$command = $matches[2];
		}

		return $environment
			. escapeshellarg($niceExecutable)
			. ' -n ' . self::NICE_LEVEL
			. ' ' . $command;
	}


	protected function getNiceExecutable(): ?string {
		if ($this->niceExecutableResolved) {
			return $this->niceExecutable;
		}
		$this->niceExecutableResolved = true;

		if (PHP_OS_FAMILY === 'Windows') {
			return null;
		}

		foreach (['/usr/bin/nice', '/bin/nice'] as $path) {
			if (@is_executable($path)) {
				$this->niceExecutable = $path;

				break;
			}
		}

		return $this->niceExecutable;
	}
}
