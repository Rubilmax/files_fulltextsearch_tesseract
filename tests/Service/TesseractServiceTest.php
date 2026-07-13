<?php
declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch_Tesseract\Tests\Service;


use OCA\Files_FullTextSearch_Tesseract\Service\ConfigService;
use OCA\Files_FullTextSearch_Tesseract\Service\LocalFileService;
use OCA\Files_FullTextSearch_Tesseract\Service\OcrJobLimiter;
use OCA\Files_FullTextSearch_Tesseract\Service\PdfContentInspector;
use OCA\Files_FullTextSearch_Tesseract\Service\PdfPageRenderer;
use OCA\Files_FullTextSearch_Tesseract\Service\ProcessPriorityService;
use OCA\Files_FullTextSearch_Tesseract\Service\TesseractService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;


class TesseractServiceTest extends TestCase {

	/**
	 * @return iterable<string, array{string, string, bool}>
	 */
	public static function mimeTypes(): iterable {
		yield 'JPEG' => ['image/jpeg', 'jpg', true];
		yield 'JPEG parameters' => ['IMAGE/JPEG; charset=binary', 'jpg', true];
		yield 'PDF' => ['application/pdf', 'pdf', true];
		yield 'octet-stream image extension' => ['application/octet-stream', 'PNG', true];
		yield 'octet-stream PDF extension' => ['application/octet-stream', 'pdf', true];
		yield 'similar invalid MIME' => ['image/jpeg-malformed', 'jpg', false];
		yield 'unrelated MIME' => ['text/plain', 'png', false];
		yield 'unrelated extension' => ['application/octet-stream', 'exe', false];
	}


	#[DataProvider('mimeTypes')]
	public function testParsedMimeType(string $mimeType, string $extension, bool $expected): void {
		$service = new TesseractService(
			$this->createMock(ConfigService::class),
			$this->createMock(LocalFileService::class),
			$this->createMock(OcrJobLimiter::class),
			$this->createMock(PdfContentInspector::class),
			$this->createMock(PdfPageRenderer::class),
			$this->createMock(ProcessPriorityService::class),
			$this->createMock(LoggerInterface::class)
		);

		self::assertSame($expected, $service->parsedMimeType($mimeType, $extension));
	}
}
