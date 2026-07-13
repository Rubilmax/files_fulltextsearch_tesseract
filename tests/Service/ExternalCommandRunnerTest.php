<?php
declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch_Tesseract\Tests\Service;


use OCA\Files_FullTextSearch_Tesseract\Service\ExternalCommandRunner;
use OCA\Files_FullTextSearch_Tesseract\Service\ProcessPriorityService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;


class ExternalCommandRunnerTest extends TestCase {

	public function testReturnsStandardOutput(): void {
		$runner = new ExternalCommandRunner(
			$this->createMock(LoggerInterface::class),
			new ProcessPriorityService()
		);

		self::assertSame('safe output', $runner->run([
			PHP_BINARY,
			'-r',
			'fwrite(STDOUT, "safe output");',
		]));
	}


	public function testReturnsNullAndLogsFailedCommand(): void {
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::once())->method('debug');
		$runner = new ExternalCommandRunner($logger, new ProcessPriorityService());

		self::assertNull($runner->run([PHP_BINARY, '-r', 'exit(7);']));
	}
}
