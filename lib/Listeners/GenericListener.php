<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch_Tesseract\Listeners;


use OCA\Files_FullTextSearch_Tesseract\Service\ConfigService;
use OCA\Files_FullTextSearch_Tesseract\Service\TesseractService;
use OCP\EventDispatcher\Event;
use OCP\EventDispatcher\GenericEvent;
use OCP\EventDispatcher\IEventListener;


/**
 * Class FileCreated
 *
 * @package OCA\Circles\Listeners
 * @implements IEventListener<GenericEvent>
 */
class GenericListener implements IEventListener {

	private const SUBJECT_PREFIX = 'Files_FullTextSearch.';

	public function __construct(
		private ConfigService $configService,
		private TesseractService $tesseractService
	) {
	}


	/**
	 * @param Event $event
	 */
	public function handle(Event $event): void {
		if (!($event instanceof GenericEvent)) {
			return;
		}

		$subject = $event->getSubject();
		if (!is_string($subject) || !str_starts_with($subject, self::SUBJECT_PREFIX)) {
			return;
		}

		$action = substr($subject, strlen(self::SUBJECT_PREFIX));

		switch ($action) {
			case 'onGetConfig':
				$this->configService->onGetConfig($event);
				break;

			case 'onFileIndexing':
				$this->tesseractService->onFileIndexing($event);
				break;

			case 'onSearchRequest':
				$this->tesseractService->onSearchRequest($event);
				break;
		}

	}

}
