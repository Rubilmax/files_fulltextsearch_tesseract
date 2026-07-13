<?php
declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch_Tesseract\Service;


use Exception;
use OCP\Files\File;
use OCP\ITempManager;
use Throwable;


/**
 * Provides command-line tools with a local file for local and remote Nextcloud storage.
 */
class LocalFileService {

	public function __construct(private ITempManager $tempManager) {
	}


	/**
	 * @template T
	 * @param callable(string): T $callback
	 * @return T
	 */
	public function runWithLocalFile(File $file, callable $callback): mixed {
		$localPath = $file->getStorage()->getLocalFile($file->getInternalPath());
		if (is_string($localPath) && is_file($localPath)) {
			return $callback($localPath);
		}

		return $this->runWithTemporaryCopy($file, $callback);
	}


	/**
	 * @template T
	 * @param callable(string): T $callback
	 * @return T
	 */
	public function runWithTemporaryFile(string $suffix, callable $callback): mixed {
		$temporaryPath = $this->tempManager->getTemporaryFile($suffix);
		if ($temporaryPath === false) {
			throw new Exception('Could not create a temporary file for OCR');
		}

		try {
			return $callback($temporaryPath);
		} finally {
			@unlink($temporaryPath);
		}
	}


	/**
	 * @template T
	 * @param callable(string): T $callback
	 * @return T
	 */
	public function runWithTemporaryFolder(callable $callback): mixed {
		$temporaryPath = $this->tempManager->getTemporaryFolder();
		if ($temporaryPath === false) {
			throw new Exception('Could not create a temporary folder for OCR');
		}

		try {
			return $callback($temporaryPath);
		} finally {
			$this->removeFolder($temporaryPath);
		}
	}


	/**
	 * @template T
	 * @param callable(string): T $callback
	 * @return T
	 */
	private function runWithTemporaryCopy(File $file, callable $callback): mixed {
		$temporaryPath = $this->tempManager->getTemporaryFile($this->getSafeSuffix($file));
		if ($temporaryPath === false) {
			throw new Exception('Could not create a temporary file for OCR');
		}

		$source = null;
		$destination = null;
		try {
			$source = $file->fopen('rb');
			if (!is_resource($source)) {
				throw new Exception('Could not open the Nextcloud file for OCR');
			}

			$destination = @fopen($temporaryPath, 'wb');
			if (!is_resource($destination)) {
				throw new Exception('Could not open the temporary OCR file for writing');
			}

			if (stream_copy_to_stream($source, $destination) === false) {
				throw new Exception('Could not copy the Nextcloud file for OCR');
			}
			fclose($destination);
			$destination = null;
			fclose($source);
			$source = null;

			return $callback($temporaryPath);
		} finally {
			if (is_resource($destination)) {
				fclose($destination);
			}
			if (is_resource($source)) {
				fclose($source);
			}
			@unlink($temporaryPath);
		}
	}


	private function getSafeSuffix(File $file): string {
		try {
			$extension = strtolower($file->getExtension());
		} catch (Throwable) {
			return '';
		}

		return preg_match('/^[a-z0-9]{1,10}$/D', $extension) === 1 ? '.' . $extension : '';
	}


	private function removeFolder(string $path): void {
		$entries = scandir($path);
		if ($entries === false) {
			return;
		}

		foreach ($entries as $entry) {
			if ($entry === '.' || $entry === '..') {
				continue;
			}

			$entryPath = $path . DIRECTORY_SEPARATOR . $entry;
			if (is_dir($entryPath) && !is_link($entryPath)) {
				$this->removeFolder($entryPath);
			} else {
				@unlink($entryPath);
			}
		}

		@rmdir($path);
	}
}
