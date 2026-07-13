<?php
declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch_Tesseract\Settings;


use OCA\Files_FullTextSearch_Tesseract\AppInfo\Application;
use OCA\Files_FullTextSearch_Tesseract\Service\ConfigService;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;


/**
 * Class Admin
 *
 * @package OCA\Files_FullTextSearch_Tesseract\Settings
 */
class Admin implements ISettings {

	public function __construct(private ConfigService $configService) {
	}

	/**
	 * @return TemplateResponse<200, array<string, mixed>>
	 */
	public function getForm(): TemplateResponse {
		return new TemplateResponse(Application::APP_ID, 'settings.admin', [
			'settings' => $this->configService->getConfig(),
		]);
	}


	/**
	 * @return string the section ID, e.g. 'sharing'
	 */
	public function getSection(): string {
		return 'fulltextsearch';
	}


	/**
	 * @return int whether the form should be rather on the top or bottom of
	 * the admin section. The forms are arranged in ascending order of the
	 * priority values. It is required to return a value between 0 and 100.
	 *
	 * keep the server setting at the top, right after "server settings"
	 */
	public function getPriority(): int {
		return 51;
	}

}
