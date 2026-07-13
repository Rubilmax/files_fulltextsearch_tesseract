<?php
declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch_Tesseract\Service;


use OCP\EventDispatcher\GenericEvent;
use OCP\Files\File;
use OCP\Files_FullTextSearch\Model\AFilesDocument;
use OCP\FullTextSearch\Model\IIndexDocument;
use OCP\FullTextSearch\Model\ISearchRequest;
use Psr\Log\LoggerInterface;
use Spatie\PdfToImage\Exceptions\PageDoesNotExist;
use Spatie\PdfToImage\Pdf;
use thiagoalessio\TesseractOCR\Option;
use thiagoalessio\TesseractOCR\TesseractOCR;
use Throwable;


/**
 * Class TesseractService
 *
 * @package OCA\Files_FullTextSearch_Tesseract\Service
 */
class TesseractService {

	public function __construct(
		private ConfigService $configService,
		private LocalFileService $localFileService,
		private OcrJobLimiter $ocrJobLimiter,
		private PdfContentInspector $pdfContentInspector,
		private PdfPageRenderer $pdfPageRenderer,
		private ProcessPriorityService $processPriorityService,
		private LoggerInterface $logger
	) {
	}


	/**
	 * @param string $mimeType
	 * @param string $extension
	 *
	 * @return bool
	 */
	public function parsedMimeType(string $mimeType, string $extension): bool {
		$mimeType = strtolower(trim(explode(';', $mimeType, 2)[0]));
		$extension = strtolower($extension);
		$ocrMimes = [
			'image/png',
			'image/jpeg',
			'image/tiff',
			'image/vnd.djvu',
			'application/pdf'
		];

		if (in_array($mimeType, $ocrMimes, true)) {
			return true;
		}

		if ($mimeType === 'application/octet-stream') {
			return $this->parsedExtension($extension);
		}

		return false;
	}


	/**
	 * @param GenericEvent $e
	 */
	public function onFileIndexing(GenericEvent $e): void {
		$file = $e->getArgument('file');
		if (!$file instanceof File) {
			return;
		}

		$document = $e->getArgument('document');
		if (!$document instanceof AFilesDocument || !$document instanceof IIndexDocument) {
			return;
		}

		$this->extractContentUsingTesseractOCR($document, $file);
	}


	/**
	 * @param GenericEvent $e
	 */
	public function onSearchRequest(GenericEvent $e): void {
		$request = $e->getArgument('request');
		if (!$request instanceof ISearchRequest) {
			return;
		}

		$request->addPart('ocr');
	}


	/**
	 * @param AFilesDocument&IIndexDocument $document
	 * @param File $file
	 */
	private function extractContentUsingTesseractOCR(
		AFilesDocument&IIndexDocument $document,
		File $file
	): void {
		try {
			if (!$this->configService->isEnabled()) {
				return;
			}

			$extension = pathinfo($document->getPath(), PATHINFO_EXTENSION);

			if (!$this->parsedMimeType($document->getMimetype(), $extension)) {
				return;
			}

			$this->logger->debug(
				'extracting content using TesseractOCR',
				[
					'documentId' => $document->getId(),
					'path' => $document->getPath(),
					'mime' => $document->getMimetype(),
					'extension' => $extension
				]
			);

			if ($this->ocrPdf($document, $file)) {
				return;
			}

			$content = $this->ocrFile($file);
		} catch (Throwable $e) {
			$this->logger->notice(
				'Failed to extract content using Tesseract OCR',
				[
					'exception' => $e,
					'documentId' => $document->getId(),
					'path' => $document->getPath()
				]
			);

			return;
		}

		$document->setContent(base64_encode($content), IIndexDocument::ENCODED_BASE64);
	}


	/**
	 * @param File $file
	 *
	 * @return string
	 */
	private function ocrFile(File $file): string {
		return $this->ocrJobLimiter->run(function () use ($file): string {
			return $this->localFileService->runWithLocalFile(
				$file,
				fn (string $path): string => $this->ocrFileFromPath($path)
			);
		});
	}


	/**
	 * @param string $path
	 *
	 * @return string
	 */
	private function ocrFileFromPath(string $path): string {
		$this->logger->debug('generating the TesseractOCR wrapper', ['path' => $path]);

		$ocr = new TesseractOCR(
			$path,
			new PrioritizedTesseractCommand($this->processPriorityService)
		);
		$ocr->threadLimit($this->configService->getEffectiveThreadLimit());
		$languages = $this->configService->getLanguages();
		$ocr->command->options[] = Option::psm($this->configService->getPageSegmentationMode());
		$ocr->command->options[] = static fn (string $_version): string => '-l ' . implode('+', $languages);
		$this->logger->debug('running the OCR command', ['command' => $ocr->command]);

		try {
			$result = $ocr->run();
			$this->logger->debug('OCR command ran smoothly');
		} catch (Throwable $e) {
			$this->logger->notice('failed to OCR', [
				'exception' => $e,
				'path' => $path,
				'cmd' => $ocr->command,
				'lang' => $languages
			]);
			$result = '';
		}

		return $result;
	}


	/**
	 * @param AFilesDocument&IIndexDocument $document
	 * @param File $file
	 *
	 * @return bool
	 */
	private function ocrPdf(AFilesDocument&IIndexDocument $document, File $file): bool {
		if (!$this->isPdf($document)) {
			return false;
		}

		if (!$this->configService->isPdfEnabled()) {
			return true;
		}

		$this->logger->debug('looks like we\'re working on a PDF file');

		try {
			$content = $this->ocrJobLimiter->run(function () use ($file): string {
				return $this->localFileService->runWithLocalFile(
					$file,
					fn (string $path): string => $this->ocrPdfFromPath($path)
				);
			});
		} catch (Throwable $e) {
			$this->logger->notice('failed to ocrPdf', ['exception' => $e, 'document' => $document]);
			throw $e;
		}

		$this->logger->debug('Saving the data into the IndexDocument');
		$document->addPart('ocr', $content);

		return true;
	}


	/**
	 * @param string $path
	 *
	 * @return string
	 */
	private function ocrPdfFromPath(string $path): string {
		$pages = $this->pdfContentInspector->getPageCount($path);
		if ($pages === null) {
			$pages = (new Pdf($path))->pageCount();
		}
		$this->logger->debug('PDF contains ' . $pages . ' page(s)');

		$limit = $this->configService->getPdfPageLimit();
		$pages = ($limit > 0 && $pages > $limit) ? $limit : $pages;
		$this->logger->debug('App will now ocr ' . $pages . ' page(s)');
		if ($pages < 1) {
			return '';
		}

		$textByPage = $this->pdfContentInspector->extractTextByPage($path, $pages);
		$skipPagesWithText = $this->configService->shouldSkipPdfText();
		if ($skipPagesWithText && $this->allPagesHaveUsefulText($textByPage, $pages)) {
			$this->logger->debug('Skipping PDF image inspection and OCR; every page has useful text');

			return $this->combinePdfContent($textByPage, [], $pages);
		}

		$ocrCandidatePages = $this->pdfContentInspector->findOcrCandidatePages($path, $pages);
		$pagesToOcr = [];

		for ($i = 1; $i <= $pages; $i++) {
			$pageText = $textByPage[$i] ?? '';

			if ($ocrCandidatePages !== null && !isset($ocrCandidatePages[$i])) {
				$this->logger->debug(
					'Skipping OCR for PDF page without a meaningful raster image',
					['page' => $i]
				);
				continue;
			}

			if ($skipPagesWithText && $this->pdfContentInspector->hasUsefulText($pageText)) {
				$this->logger->debug(
					'Skipping OCR for PDF page with an existing text layer',
					['page' => $i]
				);
				continue;
			}

			$pagesToOcr[] = $i;
		}

		if ($pagesToOcr === []) {
			return $this->combinePdfContent($textByPage, [], $pages);
		}

		return $this->localFileService->runWithTemporaryFolder(
			function (string $temporaryFolder) use ($path, $pages, $pagesToOcr, $textByPage): string {
				$renderedPages = $this->pdfPageRenderer->render(
					$path,
					$pagesToOcr,
					$temporaryFolder
				);
				if ($renderedPages === null) {
					$this->logger->debug('Falling back to Imagick PDF page rendering');
					$renderedPages = $this->renderPdfPagesWithImagick(
						$path,
						$pagesToOcr,
						$temporaryFolder
					);
				}

				$ocrContent = $this->ocrFilesFromPaths(
					array_values($renderedPages),
					$temporaryFolder
				);
				$ocrByPage = $this->splitOcrContentByPage(
					$ocrContent,
					array_keys($renderedPages)
				);

				return $this->combinePdfContent($textByPage, $ocrByPage, $pages);
			}
		);
	}


	/**
	 * @param list<int> $pages
	 *
	 * @return array<int, string>
	 */
	private function renderPdfPagesWithImagick(
		string $path,
		array $pages,
		string $outputDirectory
	): array {
		$previousThreadLimit = $this->setImagickThreadLimit();
		try {
			$pdf = new Pdf($path);
			$renderedPages = [];
			foreach ($pages as $page) {
				$tmpPath = $outputDirectory . DIRECTORY_SEPARATOR . 'page-' . $page . '.jpg';
				try {
					$pdf->selectPage($page);
					$pdf->save($tmpPath);
					if (is_file($tmpPath)) {
						$renderedPages[$page] = $tmpPath;
					}
				} catch (PageDoesNotExist $e) {
					$this->logger->notice('PDF page does not exist', ['exception' => $e, 'page' => $page]);
				} catch (Throwable $e) {
					$this->logger->notice('Failed to render PDF page', ['exception' => $e, 'page' => $page]);
				}
			}

			return $renderedPages;
		} finally {
			$this->restoreImagickThreadLimit($previousThreadLimit);
		}
	}


	private function setImagickThreadLimit(): ?int {
		try {
			$previousLimit = \Imagick::getResourceLimit(\Imagick::RESOURCETYPE_THREAD);
			\Imagick::setResourceLimit(
				\Imagick::RESOURCETYPE_THREAD,
				$this->configService->getEffectiveThreadLimit()
			);

			return $previousLimit;
		} catch (Throwable $e) {
			$this->logger->debug('Could not apply the OCR thread limit to Imagick', ['exception' => $e]);

			return null;
		}
	}


	private function restoreImagickThreadLimit(?int $previousLimit): void {
		if ($previousLimit === null) {
			return;
		}

		try {
			\Imagick::setResourceLimit(\Imagick::RESOURCETYPE_THREAD, $previousLimit);
		} catch (Throwable $e) {
			$this->logger->debug('Could not restore the Imagick thread limit', ['exception' => $e]);
		}
	}


	/**
	 * @param list<string> $paths
	 */
	private function ocrFilesFromPaths(array $paths, string $temporaryFolder): string {
		if ($paths === []) {
			return '';
		}
		if (count($paths) === 1) {
			return $this->ocrFileFromPath($paths[0]);
		}

		$listPath = $temporaryFolder . DIRECTORY_SEPARATOR . 'pages.txt';
		if (file_put_contents($listPath, implode(PHP_EOL, $paths) . PHP_EOL) === false) {
			$this->logger->notice('Failed to create the Tesseract PDF page list');

			return '';
		}

		return $this->ocrFileFromPath($listPath);
	}


	/**
	 * @param array<int, string>|null $textByPage
	 */
	private function allPagesHaveUsefulText(?array $textByPage, int $pages): bool {
		if ($textByPage === null) {
			return false;
		}

		for ($page = 1; $page <= $pages; $page++) {
			if (!$this->pdfContentInspector->hasUsefulText($textByPage[$page] ?? '')) {
				return false;
			}
		}

		return true;
	}


	/**
	 * @param list<int> $pages
	 *
	 * @return array<int, string>
	 */
	private function splitOcrContentByPage(string $content, array $pages): array {
		if ($pages === []) {
			return [];
		}

		$pageContent = explode("\f", $content);
		if (count($pageContent) !== count($pages)) {
			$this->logger->debug(
				'Tesseract returned an unexpected number of PDF page results',
				['expected' => count($pages), 'actual' => count($pageContent)]
			);

			return [$pages[0] => trim($content)];
		}

		$result = [];
		foreach ($pages as $index => $page) {
			$result[$page] = trim($pageContent[$index]);
		}

		return $result;
	}


	/**
	 * @param array<int, string>|null $textByPage
	 * @param array<int, string> $ocrByPage
	 */
	private function combinePdfContent(?array $textByPage, array $ocrByPage, int $pages): string {
		$content = [];
		for ($page = 1; $page <= $pages; $page++) {
			$pageText = trim($textByPage[$page] ?? '');
			if ($pageText !== '') {
				$content[] = $pageText;
			}

			$ocrText = trim($ocrByPage[$page] ?? '');
			if ($ocrText !== '') {
				$content[] = $ocrText;
			}
		}

		return implode("\n", $content);
	}


	/**
	 * @param string $extension
	 *
	 * @return bool
	 */
	private function parsedExtension(string $extension): bool {
		$ocrExtensions = [
			'png',
			'jpg',
			'jpeg',
			'tif',
			'tiff',
			'djv',
			'djvu',
			'pdf',
		];

		return in_array(strtolower($extension), $ocrExtensions, true);
	}


	private function isPdf(AFilesDocument $document): bool {
		$mimeType = strtolower(trim(explode(';', $document->getMimetype(), 2)[0]));

		return $mimeType === 'application/pdf'
			|| strtolower(pathinfo($document->getPath(), PATHINFO_EXTENSION)) === 'pdf';
	}
}
