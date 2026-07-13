<?php
declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch_Tesseract\Tests\Service;


use OCA\Files_FullTextSearch_Tesseract\Service\ProcessPriorityService;
use PHPUnit\Framework\TestCase;


class ProcessPriorityServiceTest extends TestCase {

	public function testPrioritizesArrayAndShellCommands(): void {
		$service = new class extends ProcessPriorityService {
			protected function getNiceExecutable(): ?string {
				return '/usr/bin/nice';
			}
		};

		self::assertSame(
			['/usr/bin/nice', '-n', '10', 'pdfinfo', '/tmp/file.pdf'],
			$service->prioritize(['pdfinfo', '/tmp/file.pdf'])
		);
		self::assertSame(
			"OMP_THREAD_LIMIT=2 '/usr/bin/nice' -n 10 \"tesseract\" input output",
			$service->prioritizeShellCommand(
				'OMP_THREAD_LIMIT=2 "tesseract" input output'
			)
		);
	}
}
