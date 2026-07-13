<?php
declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch_Tesseract\Controller;


use OCA\Files_FullTextSearch_Tesseract\AppInfo\Application;
use OCA\Files_FullTextSearch_Tesseract\Service\ConfigService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;


/**
 * Class SettingsController
 *
 * @package OCA\Files_FullTextSearch_Tesseract\Controller
 */
class SettingsController extends Controller {
	/**
	 * SettingsController constructor.
	 *
	 * @param IRequest $request
	 * @param ConfigService $configService
	 */
	public function __construct(IRequest $request, private ConfigService $configService) {
		parent::__construct(Application::APP_ID, $request);
	}


	/**
	 * @return DataResponse<200, array<string, string>, array<string, mixed>>
	 */
	public function getSettingsAdmin(): DataResponse {
		$data = $this->configService->getConfig();

		return new DataResponse($data, Http::STATUS_OK);
	}


	/**
	 * @param array<string, mixed> $data
	 * @return DataResponse<200, array<string, string>, array<string, mixed>>
	 */
	public function setSettingsAdmin(array $data): DataResponse {
		$this->configService->setConfig($data);

		return $this->getSettingsAdmin();
	}

}
