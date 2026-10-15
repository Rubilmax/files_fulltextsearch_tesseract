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
use OCP\EventDispatcher\GenericEvent;
use OCP\Files\File;
use OCP\Files\NotPermittedException;
use OCP\Files_FullTextSearch\Model\AFilesDocument;
use OCP\FullTextSearch\Model\IIndexDocument;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use thiagoalessio\TesseractOCR\TesseractNotFoundException;


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


	public function testFileReadFailureReachesTheIndexer(): void {
		$document = $this->createImageDocument();
		$failure = new NotPermittedException('The file cannot be read.');
		$localFiles = $this->createMock(LocalFileService::class);
		$localFiles->method('runWithLocalFile')->willThrowException($failure);
		$service = $this->createImageIndexingService($localFiles);

		$this->expectExceptionObject($failure);
		$service->onFileIndexing($this->createImageEvent($document));
	}


	public function testMissingTesseractReachesTheIndexer(): void {
		$document = $this->createImageDocument();
		$localFiles = $this->createMock(LocalFileService::class);
		$localFiles->method('runWithLocalFile')->willReturnCallback(
			static fn (File $_file, callable $callback): string => $callback('/missing-image.png')
		);
		$service = $this->createImageIndexingService($localFiles);
		$previousPath = getenv('PATH');
		putenv('PATH=');

		try {
			$this->expectException(TesseractNotFoundException::class);
			$service->onFileIndexing($this->createImageEvent($document));
		} finally {
			putenv($previousPath === false ? 'PATH' : 'PATH=' . $previousPath);
		}
	}


	/** @return iterable<string, array{string}> */
	public static function imageContent(): iterable {
		yield 'recognized text' => ['Searchable scan content'];
		yield 'no recognized text' => [''];
	}


	#[DataProvider('imageContent')]
	public function testSuccessfulImageOcrPreservesItsContent(string $content): void {
		$document = $this->createImageDocument();
		$localFiles = $this->createMock(LocalFileService::class);
		$localFiles->method('runWithLocalFile')->willReturn($content);
		$service = $this->createImageIndexingService($localFiles);

		$service->onFileIndexing($this->createImageEvent($document));

		self::assertSame(base64_encode($content), $document->getContent());
		self::assertSame(IIndexDocument::ENCODED_BASE64, $document->isContentEncoded());
	}


	public function testPdfOcrStopsWhenRasterInspectionIsUnavailable(): void {
		$config = $this->createMock(ConfigService::class);
		$config->method('getPdfPageLimit')->willReturn(0);
		$localFiles = $this->createMock(LocalFileService::class);
		$localFiles->expects(self::never())->method('runWithTemporaryFolder');
		$inspector = $this->createMock(PdfContentInspector::class);
		$inspector->method('getPageCount')->willReturn(2);
		$inspector->method('findOcrCandidatePages')->willReturn(null);

		$service = new TesseractService(
			$config,
			$localFiles,
			$this->createMock(OcrJobLimiter::class),
			$inspector,
			$this->createMock(PdfPageRenderer::class),
			$this->createMock(ProcessPriorityService::class),
			$this->createMock(LoggerInterface::class)
		);

		self::assertSame('', $this->invokePdfOcr($service, '/tmp/document.pdf'));
	}


	public function testPdfOcrRendersOnlyMeaningfulRasterCandidates(): void {
		$config = $this->createMock(ConfigService::class);
		$config->method('getPdfPageLimit')->willReturn(0);
		$localFiles = $this->createMock(LocalFileService::class);
		$localFiles->expects(self::once())
			->method('runWithTemporaryFolder')
			->willReturnCallback(
				static fn (callable $callback): mixed => $callback('/tmp/ocr-work')
			);
		$inspector = $this->createMock(PdfContentInspector::class);
		$inspector->method('getPageCount')->willReturn(3);
		$inspector->expects(self::once())
			->method('findOcrCandidatePages')
			->with('/tmp/document.pdf', 3)
			->willReturn([2 => true]);
		$renderer = $this->createMock(PdfPageRenderer::class);
		$renderer->expects(self::once())
			->method('render')
			->with('/tmp/document.pdf', [2], '/tmp/ocr-work')
			->willReturn([]);

		$service = new TesseractService(
			$config,
			$localFiles,
			$this->createMock(OcrJobLimiter::class),
			$inspector,
			$renderer,
			$this->createMock(ProcessPriorityService::class),
			$this->createMock(LoggerInterface::class)
		);

		self::assertSame('', $this->invokePdfOcr($service, '/tmp/document.pdf'));
	}


	private function createImageDocument(): AFilesDocument&\PHPUnit\Framework\MockObject\MockObject {
		if (!class_exists(\OC\FullTextSearch\Model\IndexDocument::class)) {
			self::markTestSkipped('Set NEXTCLOUD_SERVER_ROOT to test the actual Nextcloud document model.');
		}

		$document = $this->getMockBuilder(AFilesDocument::class)
			->setConstructorArgs(['files', '12'])
			->getMock();
		$document->method('getPath')->willReturn('/scan.png');
		$document->method('getMimetype')->willReturn('image/png');

		return $document;
	}


	private function createImageEvent(AFilesDocument $document): GenericEvent {
		return new GenericEvent('Files_FullTextSearch.onFileIndexing', [
			'document' => $document,
			'file' => $this->createMock(File::class),
		]);
	}


	private function createImageIndexingService(LocalFileService $localFiles): TesseractService {
		$config = $this->createMock(ConfigService::class);
		$config->method('isEnabled')->willReturn(true);
		$config->method('getEffectiveThreadLimit')->willReturn(1);
		$config->method('getLanguages')->willReturn(['eng']);
		$config->method('getPageSegmentationMode')->willReturn(4);
		$limiter = $this->createMock(OcrJobLimiter::class);
		$limiter->method('run')->willReturnCallback(static fn (callable $job): mixed => $job());

		return new TesseractService(
			$config,
			$localFiles,
			$limiter,
			$this->createMock(PdfContentInspector::class),
			$this->createMock(PdfPageRenderer::class),
			$this->createMock(ProcessPriorityService::class),
			$this->createMock(LoggerInterface::class)
		);
	}


	private function invokePdfOcr(TesseractService $service, string $path): mixed {
		$method = new \ReflectionMethod($service, 'ocrPdfFromPath');

		return $method->invoke($service, $path);
	}
}
