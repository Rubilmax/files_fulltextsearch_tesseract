/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** global: OCA */
/** global: fts_admin_settings */
/** global: fts_tesseract_settings */



var fts_tesseract_elements = {
	tesseract_div: null,
	tesseract_ocr: null,
	tesseract_psm: null,
	tesseract_lang: null,
	tesseract_cpu_budget: null,
	tesseract_parallel_jobs: null,
	tesseract_threads: null,
	tesseract_pdf: null,
	tesseract_pdf_limit: null,
	settingsSaveTimer: null,

	init: function () {
		fts_tesseract_elements.tesseract_div = $('#files_ocr-tesseract');
		fts_tesseract_elements.tesseract_psm = $('#tesseract_psm');
		fts_tesseract_elements.tesseract_lang = $('#tesseract_lang');
		fts_tesseract_elements.tesseract_cpu_budget = $('#tesseract_cpu_budget');
		fts_tesseract_elements.tesseract_parallel_jobs = $('#tesseract_parallel_jobs');
		fts_tesseract_elements.tesseract_threads = $('#tesseract_threads');
		fts_tesseract_elements.tesseract_ocr = $('#tesseract_ocr');
		fts_tesseract_elements.tesseract_pdf = $('#tesseract_pdf');
		fts_tesseract_elements.tesseract_pdf_limit = $('#tesseract_pdf_limit');

		var valueInputs = fts_tesseract_elements.tesseract_div.find(
			'input[type="number"], input[type="text"]'
		);
		valueInputs.on('input', fts_tesseract_elements.scheduleSettingsUpdate);
		valueInputs.on('change', fts_tesseract_elements.flushScheduledSettingsUpdate);

		fts_tesseract_elements.tesseract_div.find('input[type="checkbox"]').on(
			'change',
			fts_tesseract_elements.updateSettings
		);
	},


	scheduleSettingsUpdate: function () {
		fts_admin_settings.tagSettingsAsNotSaved($(this));
		clearTimeout(fts_tesseract_elements.settingsSaveTimer);
		fts_tesseract_elements.settingsSaveTimer = setTimeout(function () {
			fts_tesseract_elements.settingsSaveTimer = null;
			fts_tesseract_settings.saveSettings();
		}, 500);
	},


	flushScheduledSettingsUpdate: function () {
		if (fts_tesseract_elements.settingsSaveTimer === null) {
			return;
		}

		clearTimeout(fts_tesseract_elements.settingsSaveTimer);
		fts_tesseract_elements.settingsSaveTimer = null;
		fts_tesseract_settings.saveSettings();
	},


	updateSettings: function () {
		fts_admin_settings.tagSettingsAsNotSaved($(this));
		clearTimeout(fts_tesseract_elements.settingsSaveTimer);
		fts_tesseract_elements.settingsSaveTimer = null;
		fts_tesseract_settings.saveSettings();
	}


};
