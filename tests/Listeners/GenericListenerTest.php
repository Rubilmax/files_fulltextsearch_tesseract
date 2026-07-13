<?php
declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch_Tesseract\Tests\Listeners;


use OCA\Files_FullTextSearch_Tesseract\Listeners\GenericListener;
use OCA\Files_FullTextSearch_Tesseract\Service\ConfigService;
use OCA\Files_FullTextSearch_Tesseract\Service\TesseractService;
use OCP\EventDispatcher\GenericEvent;
use PHPUnit\Framework\TestCase;


class GenericListenerTest extends TestCase {

	public function testIgnoresGenericEventsWithNonStringSubjects(): void {
		$configService = $this->createMock(ConfigService::class);
		$tesseractService = $this->createMock(TesseractService::class);
		$configService->expects(self::never())->method('onGetConfig');
		$tesseractService->expects(self::never())->method('onFileIndexing');
		$tesseractService->expects(self::never())->method('onSearchRequest');

		$listener = new GenericListener($configService, $tesseractService);
		$listener->handle(new GenericEvent(new \stdClass()));
	}


	public function testRoutesFullTextSearchConfigEvent(): void {
		$configService = $this->createMock(ConfigService::class);
		$tesseractService = $this->createMock(TesseractService::class);
		$event = new GenericEvent('Files_FullTextSearch.onGetConfig', ['config' => []]);
		$configService->expects(self::once())->method('onGetConfig')->with($event);

		$listener = new GenericListener($configService, $tesseractService);
		$listener->handle($event);
	}
}
