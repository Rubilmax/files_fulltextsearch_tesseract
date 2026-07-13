<?php
declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch_Tesseract\Tests\Service;


use OCA\Files_FullTextSearch_Tesseract\Service\ExternalCommandRunner;
use OCA\Files_FullTextSearch_Tesseract\Service\PdfContentInspector;
use PHPUnit\Framework\TestCase;


class PdfContentInspectorTest extends TestCase {

	public function testGetsPageCountWithPdfInfo(): void {
		$commandRunner = $this->createMock(ExternalCommandRunner::class);
		$commandRunner->expects($this->once())
			->method('run')
			->with(['pdfinfo', '/tmp/document.pdf'])
			->willReturn("Title: example\nPages:          42\n");

		$inspector = new PdfContentInspector($commandRunner);

		self::assertSame(42, $inspector->getPageCount('/tmp/document.pdf'));
	}


	public function testReturnsNullForUnavailablePageCount(): void {
		$commandRunner = $this->createMock(ExternalCommandRunner::class);
		$commandRunner->method('run')->willReturn(null);

		$inspector = new PdfContentInspector($commandRunner);

		self::assertNull($inspector->getPageCount('/tmp/document.pdf'));
	}


	public function testExtractsTextByPage(): void {
		$commandRunner = $this->createMock(ExternalCommandRunner::class);
		$commandRunner->method('run')->willReturn("first page\fsecond page\f");

		$inspector = new PdfContentInspector($commandRunner);

		self::assertSame(
			[1 => 'first page', 2 => 'second page'],
			$inspector->extractTextByPage('/tmp/document.pdf', 2)
		);
	}


	public function testFindsPagesWithMeaningfulRasterImages(): void {
		$commandRunner = $this->createMock(ExternalCommandRunner::class);
		$commandRunner->method('run')->willReturn(
			"page num type width height color comp bpc enc interp object ID x-ppi y-ppi size ratio\n"
			. "   1   0 image 100 100 rgb 3 8 jpeg no 1 0 72 72 1K 1%\n"
			. "   2   1 image 2550 3300 rgb 3 8 jpeg no 2 0 300 300 1M 10%\n"
			. "   2   2 smask 2550 3300 gray 1 8 image no 3 0 300 300 1M 10%\n"
		);
		$inspector = new PdfContentInspector($commandRunner);

		self::assertSame([2 => true], $inspector->findOcrCandidatePages('/tmp/document.pdf', 2));
	}


	public function testUsesPixelFallbackWhenPdfImageResolutionIsMissing(): void {
		$commandRunner = $this->createMock(ExternalCommandRunner::class);
		$commandRunner->method('run')->willReturn(
			"page num type width height color comp bpc enc interp object ID x-ppi y-ppi size ratio\n"
			. "   1   0 image 1600 1200 rgb 3 8 jpeg no 1 0 - - 1M 10%\n"
		);
		$inspector = new PdfContentInspector($commandRunner);

		self::assertSame([1 => true], $inspector->findOcrCandidatePages('/tmp/document.pdf', 1));
	}


	public function testUsefulTextRequiresEnoughUnicodeWordsAndCharacters(): void {
		$inspector = new PdfContentInspector($this->createMock(ExternalCommandRunner::class));

		self::assertFalse($inspector->hasUsefulText('Page 1'));
		self::assertTrue($inspector->hasUsefulText(
			'Éléments utiles répétés plusieurs fois pour fournir une couche textuelle réellement complète.'
		));
	}
}
