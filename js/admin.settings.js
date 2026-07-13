/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

/** global: OC */
/** global: fts_tesseract_elements */
/** global: fts_admin_settings */



var fts_tesseract_settings = {

	pendingData: null,
	saving: false,

	refreshSettingPage: function () {

		$.ajax({
			method: 'GET',
			url: OC.generateUrl('/apps/files_fulltextsearch_tesseract/admin/settings')
		}).done(function (res) {
			fts_tesseract_settings.updateSettingPage(res);
		});

	},


	updateSettingPage: function (result) {
		fts_tesseract_elements.tesseract_ocr.prop('checked', (result.tesseract_enabled === '1'));
		fts_tesseract_elements.tesseract_psm.val(result.tesseract_psm);
		fts_tesseract_elements.tesseract_lang.val(result.tesseract_lang);
		fts_tesseract_elements.tesseract_cpu_budget.val(result.tesseract_cpu_budget);
		fts_tesseract_elements.tesseract_parallel_jobs.val(result.tesseract_parallel_jobs);
		fts_tesseract_elements.tesseract_threads.val(result.tesseract_threads);
		fts_tesseract_elements.tesseract_pdf.prop('checked', (result.tesseract_pdf === '1'));
		fts_tesseract_elements.tesseract_pdf_limit.val(result.tesseract_pdf_limit);
		fts_tesseract_elements.tesseract_pdf_skip_text.prop(
			'checked', (result.tesseract_pdf_skip_text === '1')
		);

		fts_admin_settings.tagSettingsAsSaved(fts_tesseract_elements.tesseract_div);

		if (result.tesseract_enabled === '1') {
			fts_tesseract_elements.tesseract_div.find('.tesseract_ocr_enabled').fadeTo(300, 1);
			fts_tesseract_elements.tesseract_div.find('.tesseract_ocr_enabled').find('*').prop(
				'disabled', false);
		} else {
			fts_tesseract_elements.tesseract_div.find('.tesseract_ocr_enabled').fadeTo(300, 0.6);
			fts_tesseract_elements.tesseract_div.find('.tesseract_ocr_enabled').find('*').prop(
				'disabled', true);
		}
	},


	saveSettings: function () {
		fts_tesseract_settings.pendingData = fts_tesseract_settings.readSettings();
		fts_tesseract_settings.flushSettings();
	},


	readSettings: function () {
		return {
			tesseract_enabled: (fts_tesseract_elements.tesseract_ocr.is(':checked')) ? 1 : 0,
			tesseract_psm: fts_tesseract_elements.tesseract_psm.val(),
			tesseract_lang: fts_tesseract_elements.tesseract_lang.val(),
			tesseract_cpu_budget: fts_tesseract_elements.tesseract_cpu_budget.val(),
			tesseract_parallel_jobs: fts_tesseract_elements.tesseract_parallel_jobs.val(),
			tesseract_threads: fts_tesseract_elements.tesseract_threads.val(),
			tesseract_pdf: (fts_tesseract_elements.tesseract_pdf.is(':checked')) ? 1 : 0,
			tesseract_pdf_limit: fts_tesseract_elements.tesseract_pdf_limit.val(),
			tesseract_pdf_skip_text: (
				fts_tesseract_elements.tesseract_pdf_skip_text.is(':checked')
			) ? 1 : 0
		};
	},


	flushSettings: function () {
		if (fts_tesseract_settings.saving || fts_tesseract_settings.pendingData === null) {
			return;
		}

		var data = fts_tesseract_settings.pendingData;
		fts_tesseract_settings.pendingData = null;
		fts_tesseract_settings.saving = true;

		$.ajax({
			method: 'POST',
			url: OC.generateUrl('/apps/files_fulltextsearch_tesseract/admin/settings'),
			data: {
				data: data
			}
		}).done(function (res) {
			if (fts_tesseract_settings.pendingData === null) {
				fts_tesseract_settings.updateSettingPage(res);
			}
		}).always(function () {
			fts_tesseract_settings.saving = false;
			fts_tesseract_settings.flushSettings();
		});
	}


};
