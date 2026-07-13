<?php
declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch_Tesseract\Tests\Service;


use OCA\Files_FullTextSearch_Tesseract\Service\ExternalCommandRunner;
use OCA\Files_FullTextSearch_Tesseract\Service\PdfPageRenderer;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;


class PdfPageRendererTest extends TestCase {

	private string $temporaryDirectory;


	protected function setUp(): void {
		parent::setUp();
		$this->temporaryDirectory = sys_get_temp_dir()
			. DIRECTORY_SEPARATOR
			. 'fts-ocr-renderer-test-'
			. bin2hex(random_bytes(8));
		mkdir($this->temporaryDirectory, 0700, true);
	}


	protected function tearDown(): void {
		foreach (glob($this->temporaryDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $path) {
			@unlink($path);
		}
		@rmdir($this->temporaryDirectory);
		parent::tearDown();
	}


	public function testRendersConsecutivePagesInBatches(): void {
		$commands = [];
		$commandRunner = $this->createMock(ExternalCommandRunner::class);
		$commandRunner->expects($this->exactly(2))
			->method('run')
			->willReturnCallback(function (array $command) use (&$commands): string {
				$commands[] = $command;
				$firstPage = (int)$command[array_search('-f', $command, true) + 1];
				$lastPage = (int)$command[array_search('-l', $command, true) + 1];
				$prefix = $command[count($command) - 1];
				for ($page = $firstPage; $page <= $lastPage; $page++) {
					file_put_contents($prefix . '-' . $page . '.pgm', 'P5');
				}

				return '';
			});

		$renderer = new PdfPageRenderer(
			$commandRunner,
			$this->createMock(LoggerInterface::class)
		);
		$result = $renderer->render(
			'/tmp/document.pdf',
			[4, 2, 1, 2],
			$this->temporaryDirectory
		);

		self::assertSame([1, 2, 4], array_keys($result ?? []));
		self::assertSame('1', $commands[0][2]);
		self::assertSame('2', $commands[0][4]);
		self::assertSame('4', $commands[1][2]);
		self::assertSame('4', $commands[1][4]);
		self::assertContains('-gray', $commands[0]);
		self::assertContains('-forcenum', $commands[0]);
	}


	public function testReturnsNullWhenPopplerIsUnavailable(): void {
		$commandRunner = $this->createMock(ExternalCommandRunner::class);
		$commandRunner->method('run')->willReturn(null);
		$renderer = new PdfPageRenderer(
			$commandRunner,
			$this->createMock(LoggerInterface::class)
		);

		self::assertNull($renderer->render(
			'/tmp/document.pdf',
			[1],
			$this->temporaryDirectory
		));
	}
}
