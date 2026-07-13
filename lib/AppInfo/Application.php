<?php
declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch_Tesseract\AppInfo;


use OCA\Files_FullTextSearch_Tesseract\Listeners\GenericListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\GenericEvent;


require_once __DIR__ . '/../../vendor/autoload.php';


/**
 * Class Application
 *
 * @package OCA\Files_FullTextSearch_Tesseract\AppInfo
 */
class Application extends App implements IBootstrap {

	public const APP_ID = 'files_fulltextsearch_tesseract';

	/**
	 * @param array<string, mixed> $params
	 */
	public function __construct(array $params = []) {
		parent::__construct(self::APP_ID, $params);
	}


	/**
	 * @param IRegistrationContext $context
	 */
	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(GenericEvent::class, GenericListener::class);
	}


	/**
	 * @param IBootContext $context
	 */
	public function boot(IBootContext $context): void {
	}

}
