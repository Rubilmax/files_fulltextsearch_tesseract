<?php
declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch_Tesseract\Service;


/**
 * Uses Poppler tools to identify PDF pages containing meaningful raster images.
 */
class PdfContentInspector {

	private const MIN_IMAGE_PIXELS = 250000;
	private const MIN_DISPLAYED_IMAGE_AREA_SQUARE_INCHES = 6.0;
	private const MIN_FALLBACK_IMAGE_PIXELS = 1000000;
	private const MIN_FALLBACK_SHORT_EDGE = 600;


	public function __construct(private ExternalCommandRunner $commandRunner) {
	}


	/**
	 * @return int|null Page count, or null when Poppler inspection is unavailable.
	 */
	public function getPageCount(string $path): ?int {
		$output = $this->commandRunner->run(['pdfinfo', $path]);
		if ($output === null
			|| preg_match('/^Pages:\s+(\d+)\s*$/mi', $output, $matches) !== 1) {
			return null;
		}

		$pageCount = (int)$matches[1];

		return $pageCount > 0 ? $pageCount : null;
	}


	/**
	 * @param string $path
	 * @param int $pages
	 *
	 * Ignore masks and small decorative images. Prefer physical image coverage calculated from
	 * Poppler's effective DPI, with a conservative pixel-size fallback for malformed metadata.
	 *
	 * @return array<int, bool>|null One-indexed pages containing meaningful raster images, or null when unavailable.
	 */
	public function findOcrCandidatePages(string $path, int $pages): ?array {
		if ($pages < 1) {
			return [];
		}

		$output = $this->commandRunner->run(
			['pdfimages', '-f', '1', '-l', (string)$pages, '-list', $path]
		);
		if ($output === null) {
			return null;
		}

		/**
		 * @var array<int, array{
		 *     displayedArea: float,
		 *     fallbackPixels: float,
		 *     fallbackShortEdge: int
		 * }> $pageStatistics
		 */
		$pageStatistics = [];
		$candidatePages = [];
		foreach (preg_split('/\R/', $output) ?: [] as $line) {
			$columns = preg_split('/\s+/', trim($line)) ?: [];
			if (count($columns) < 14 || $columns[2] !== 'image') {
				continue;
			}

			$page = $this->parsePositiveInteger($columns[0]);
			if ($page === null || $page > $pages) {
				continue;
			}

			$width = $this->parsePositiveInteger($columns[3]);
			$height = $this->parsePositiveInteger($columns[4]);
			if ($width === null || $height === null) {
				// Unknown dimensions cannot safely be classified as decorative.
				$candidatePages[$page] = true;
				continue;
			}

			$pixelArea = (float)$width * $height;
			if ($pixelArea < self::MIN_IMAGE_PIXELS) {
				continue;
			}

			$pageStatistics[$page] ??= [
				'displayedArea' => 0.0,
				'fallbackPixels' => 0.0,
				'fallbackShortEdge' => 0,
			];
			$xPpi = $this->parsePositiveFloat($columns[12]);
			$yPpi = $this->parsePositiveFloat($columns[13]);
			if ($xPpi !== null && $yPpi !== null) {
				$pageStatistics[$page]['displayedArea'] += ($width / $xPpi) * ($height / $yPpi);
			} else {
				$pageStatistics[$page]['fallbackPixels'] += $pixelArea;
				$pageStatistics[$page]['fallbackShortEdge'] = max(
					$pageStatistics[$page]['fallbackShortEdge'],
					min($width, $height)
				);
			}
		}

		foreach ($pageStatistics as $page => $statistics) {
			if ($statistics['displayedArea'] >= self::MIN_DISPLAYED_IMAGE_AREA_SQUARE_INCHES
				|| ($statistics['fallbackPixels'] >= self::MIN_FALLBACK_IMAGE_PIXELS
					&& $statistics['fallbackShortEdge'] >= self::MIN_FALLBACK_SHORT_EDGE)) {
				$candidatePages[$page] = true;
			}
		}
		ksort($candidatePages, SORT_NUMERIC);

		return $candidatePages;
	}


	private function parsePositiveInteger(string $value): ?int {
		if (preg_match('/^\d+$/D', $value) !== 1) {
			return null;
		}

		$integer = (int)$value;

		return $integer > 0 ? $integer : null;
	}


	private function parsePositiveFloat(string $value): ?float {
		if (!is_numeric($value)) {
			return null;
		}

		$float = (float)$value;

		return is_finite($float) && $float > 0 ? $float : null;
	}


}
