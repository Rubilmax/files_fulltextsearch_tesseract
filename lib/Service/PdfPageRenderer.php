<?php
declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch_Tesseract\Service;


use Psr\Log\LoggerInterface;


/**
 * Renders selected PDF pages as grayscale PGM images using Poppler.
 */
class PdfPageRenderer {

	private const RENDER_DPI = 144;
	private const RENDER_TIMEOUT_SECONDS = 300;


	public function __construct(
		private ExternalCommandRunner $commandRunner,
		private LoggerInterface $logger
	) {
	}


	/**
	 * Consecutive page ranges are rendered in one Poppler process.
	 *
	 * @param list<int> $pages
	 *
	 * @return array<int, string>|null One-indexed page paths, or null when Poppler is unavailable.
	 */
	public function render(string $pdfPath, array $pages, string $outputDirectory): ?array {
		$pages = array_values(array_unique(array_filter(
			array_map('intval', $pages),
			static fn (int $page): bool => $page > 0
		)));
		sort($pages, SORT_NUMERIC);
		if ($pages === []) {
			return [];
		}

		$renderedPages = [];
		foreach ($this->groupConsecutivePages($pages) as $range) {
			$firstPage = $range[0];
			$lastPage = $range[count($range) - 1];
			$prefix = $outputDirectory . DIRECTORY_SEPARATOR . 'page-' . $firstPage . '-' . $lastPage;
			$output = $this->commandRunner->run([
				'pdftoppm',
				'-f', (string)$firstPage,
				'-l', (string)$lastPage,
				'-r', (string)self::RENDER_DPI,
				'-gray',
				'-forcenum',
				'-q',
				$pdfPath,
				$prefix
			], self::RENDER_TIMEOUT_SECONDS);
			if ($output === null) {
				return null;
			}

			$paths = glob($prefix . '-*.pgm');
			if ($paths === false) {
				return null;
			}
			natsort($paths);
			$paths = array_values($paths);
			if (count($paths) !== count($range)) {
				$this->logger->debug(
					'Poppler returned an unexpected number of rendered PDF pages',
					[
						'firstPage' => $firstPage,
						'lastPage' => $lastPage,
						'expected' => count($range),
						'actual' => count($paths)
					]
				);

				return null;
			}

			foreach ($range as $index => $page) {
				$renderedPages[$page] = $paths[$index];
			}
		}

		return $renderedPages;
	}


	/**
	 * @param list<int> $pages
	 *
	 * @return list<list<int>>
	 */
	private function groupConsecutivePages(array $pages): array {
		$ranges = [];
		$currentRange = [];
		$previousPage = null;

		foreach ($pages as $page) {
			if ($previousPage !== null && $page !== $previousPage + 1) {
				$ranges[] = $currentRange;
				$currentRange = [];
			}

			$currentRange[] = $page;
			$previousPage = $page;
		}

		if ($currentRange !== []) {
			$ranges[] = $currentRange;
		}

		return $ranges;
	}
}
