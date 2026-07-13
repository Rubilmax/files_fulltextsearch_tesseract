<?php
declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch_Tesseract\Tests\Service;


use OCA\Files_FullTextSearch_Tesseract\Service\LocalFileService;
use OCP\Files\File;
use OCP\Files\Storage\IStorage;
use OCP\ITempManager;
use PHPUnit\Framework\TestCase;


class LocalFileServiceTest extends TestCase {

	public function testUsesStorageLocalFileWithoutCopying(): void {
		$path = tempnam(sys_get_temp_dir(), 'ocr-local-test-');
		self::assertIsString($path);
		file_put_contents($path, 'local content');

		try {
			$storage = $this->createMock(IStorage::class);
			$storage->expects(self::once())->method('getLocalFile')->with('folder/file.png')
				->willReturn($path);
			$file = $this->createMock(File::class);
			$file->method('getStorage')->willReturn($storage);
			$file->method('getInternalPath')->willReturn('folder/file.png');
			$file->expects(self::never())->method('fopen');
			$tempManager = $this->createMock(ITempManager::class);
			$tempManager->expects(self::never())->method('getTemporaryFile');

			$service = new LocalFileService($tempManager);
			$result = $service->runWithLocalFile($file, fn (string $localPath): string => $localPath);

			self::assertSame($path, $result);
		} finally {
			@unlink($path);
		}
	}


	public function testCopiesRemoteFileAndAlwaysRemovesTemporaryFile(): void {
		$path = tempnam(sys_get_temp_dir(), 'ocr-copy-test-');
		self::assertIsString($path);
		$source = fopen('php://memory', 'w+b');
		self::assertIsResource($source);
		fwrite($source, 'remote content');
		rewind($source);

		$storage = $this->createMock(IStorage::class);
		$storage->method('getLocalFile')->willReturn(false);
		$file = $this->createMock(File::class);
		$file->method('getStorage')->willReturn($storage);
		$file->method('getInternalPath')->willReturn('remote/file.png');
		$file->method('getExtension')->willReturn('png');
		$file->method('fopen')->with('rb')->willReturn($source);
		$tempManager = $this->createMock(ITempManager::class);
		$tempManager->expects(self::once())->method('getTemporaryFile')->with('.png')
			->willReturn($path);

		$service = new LocalFileService($tempManager);
		$result = $service->runWithLocalFile(
			$file,
			fn (string $localPath): string => (string)file_get_contents($localPath)
		);

		self::assertSame('remote content', $result);
		self::assertFileDoesNotExist($path);
	}


	public function testTemporaryOutputIsRemovedWhenCallbackFails(): void {
		$path = tempnam(sys_get_temp_dir(), 'ocr-output-test-');
		self::assertIsString($path);
		$tempManager = $this->createMock(ITempManager::class);
		$tempManager->method('getTemporaryFile')->willReturn($path);
		$service = new LocalFileService($tempManager);

		try {
			$service->runWithTemporaryFile('.jpg', static function (): void {
				throw new \RuntimeException('test failure');
			});
			self::fail('The callback exception was not propagated');
		} catch (\RuntimeException $e) {
			self::assertSame('test failure', $e->getMessage());
		}

		self::assertFileDoesNotExist($path);
	}


	public function testTemporaryFolderAndContentsAreRemoved(): void {
		$path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ocr-folder-test-' . bin2hex(random_bytes(8));
		mkdir($path, 0700, true);
		$tempManager = $this->createMock(ITempManager::class);
		$tempManager->expects(self::once())->method('getTemporaryFolder')->willReturn($path);
		$service = new LocalFileService($tempManager);

		$result = $service->runWithTemporaryFolder(static function (string $folder): string {
			file_put_contents($folder . DIRECTORY_SEPARATOR . 'page.pgm', 'P5');

			return $folder;
		});

		self::assertSame($path, $result);
		self::assertDirectoryDoesNotExist($path);
	}
}
