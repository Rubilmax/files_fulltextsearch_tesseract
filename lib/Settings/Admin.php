<?php
declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch_Tesseract\Settings;


use OCA\Files_FullTextSearch_Tesseract\Service\ConfigService;
use OCP\IL10N;
use OCP\IUser;
use OCP\Settings\DeclarativeSettingsTypes;
use OCP\Settings\IDeclarativeSettingsFormWithHandlers;


/**
 * Class Admin
 *
 * @package OCA\Files_FullTextSearch_Tesseract\Settings
 */
class Admin implements IDeclarativeSettingsFormWithHandlers {

	private const DOCUMENTATION_URL = 'https://tesseract-ocr.github.io/tessdoc/ImproveQuality.html#page-segmentation-method';


	public function __construct(
		private IL10N $l,
		private ConfigService $configService,
	) {
	}


	/**
	 * @return array<string, mixed>
	 */
	public function getSchema(): array {
		return [
			'id' => 'tesseract',
			'priority' => 51,
			'section_type' => DeclarativeSettingsTypes::SECTION_TYPE_ADMIN,
			'section_id' => 'fulltextsearch',
			'storage_type' => DeclarativeSettingsTypes::STORAGE_TYPE_EXTERNAL,
			'title' => $this->l->t('Files - Tesseract OCR'),
			'doc_url' => self::DOCUMENTATION_URL,
			'fields' => [
				$this->field(
					ConfigService::TESSERACT_ENABLED,
					$this->l->t('Enable OCR'),
					DeclarativeSettingsTypes::CHECKBOX,
					$this->l->t('Extract searchable text with Tesseract.'),
				),
				$this->field(
					ConfigService::TESSERACT_PSM,
					$this->l->t('Page segmentation method'),
					DeclarativeSettingsTypes::NUMBER,
				),
				$this->field(
					ConfigService::TESSERACT_LANG,
					$this->l->t('Languages'),
					DeclarativeSettingsTypes::TEXT,
					$this->l->t('Comma-separated list of installed Tesseract languages.'),
				),
				$this->field(
					ConfigService::TESSERACT_CPU_BUDGET,
					$this->l->t('OCR CPU budget'),
					DeclarativeSettingsTypes::NUMBER,
					$this->l->t('Maximum CPU threads shared by all OCR jobs; defaults to half of the available CPUs.'),
				),
				$this->field(
					ConfigService::TESSERACT_PARALLEL_JOBS,
					$this->l->t('Parallel OCR jobs'),
					DeclarativeSettingsTypes::NUMBER,
					$this->l->t('Maximum files processed at once, additionally constrained by the CPU budget.'),
				),
				$this->field(
					ConfigService::TESSERACT_THREADS,
					$this->l->t('Threads per OCR job'),
					DeclarativeSettingsTypes::NUMBER,
					$this->l->t('Maximum Tesseract threads per file; all files share the CPU budget.'),
				),
				$this->field(
					ConfigService::TESSERACT_PDF,
					$this->l->t('PDF OCR'),
					DeclarativeSettingsTypes::CHECKBOX,
					$this->l->t('OCR PDF pages with meaningful raster images; requires Poppler pdfimages.'),
				),
				$this->field(
					ConfigService::TESSERACT_PDF_LIMIT,
					$this->l->t('Limit PDF pages'),
					DeclarativeSettingsTypes::NUMBER,
					$this->l->t('Limit PDF OCR to the first number of pages; zero means no limit.'),
				),
			],
		];
	}


	public function getValue(string $fieldId, IUser $user): mixed {
		return $this->configService->getAppValue($fieldId);
	}


	public function setValue(string $fieldId, mixed $value, IUser $user): void {
		$this->configService->setConfig([$fieldId => $value]);
	}


	/**
	 * @return array<string, mixed>
	 */
	private function field(string $id, string $title, string $type, ?string $description = null): array {
		$default = $this->configService->getDefaultValue($id);
		if ($type === DeclarativeSettingsTypes::CHECKBOX || $type === DeclarativeSettingsTypes::NUMBER) {
			$default = (int)$default;
		}

		$field = [
			'id' => $id,
			'title' => $title,
			'type' => $type,
			'default' => $default,
		];
		if ($description !== null) {
			$field['description'] = $description;
		}

		return $field;
	}

}
