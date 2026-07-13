<?php
declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use OCA\Files_FullTextSearch_Tesseract\AppInfo\Application;
use OCP\Util;


Util::addScript(Application::APP_ID, 'admin.elements');
Util::addScript(Application::APP_ID, 'admin.settings');
Util::addScript(Application::APP_ID, 'admin');

$settings = $_['settings'];

?>

<div id="files_ocr-tesseract" class="section">
	<h2><?php p($l->t('Files - Tesseract OCR')); ?></h2>

	<div class="div-table">
		<div class="div-table-row">
			<div class="div-table-col div-table-col-left">
				<label class="leftcol" for="tesseract_ocr"><?php p($l->t('Enable OCR')); ?>:</label>
				<br/>
				<em><?php p($l->t('Extract searchable text with Tesseract.')); ?></em>
			</div>
			<div class="div-table-col">
				<input type="checkbox" id="tesseract_ocr" value="1"
					<?php if ($settings['tesseract_enabled'] === '1') { ?>checked<?php } ?>/>
			</div>
		</div>

		<div class="div-table-row tesseract_ocr_enabled">
			<div class="div-table-col div-table-col-left">
				<label class="leftcol" for="tesseract_psm"><?php p($l->t('Page segmentation method')); ?></label>
				<br/>
				<em><a href="https://tesseract-ocr.github.io/tessdoc/ImproveQuality.html#page-segmentation-method">
					<?php p($l->t('Tesseract documentation')); ?></a></em>
			</div>
			<div class="div-table-col">
				<input type="number" class="small" id="tesseract_psm" min="0" max="13" step="1"
					value="<?php p($settings['tesseract_psm']); ?>"/>
			</div>
		</div>

		<div class="div-table-row tesseract_ocr_enabled">
			<div class="div-table-col div-table-col-left">
				<label class="leftcol" for="tesseract_lang"><?php p($l->t('Languages')); ?></label>
				<br/>
				<em><?php p($l->t('Comma-separated list of installed Tesseract languages.')); ?></em>
			</div>
			<div class="div-table-col">
				<input type="text" class="big" id="tesseract_lang"
					value="<?php p($settings['tesseract_lang']); ?>"/>
			</div>
		</div>

		<div class="div-table-row tesseract_ocr_enabled">
			<div class="div-table-col div-table-col-left">
				<label class="leftcol" for="tesseract_cpu_budget"><?php p($l->t('OCR CPU budget')); ?></label>
				<br/>
				<em><?php p($l->t('Maximum CPU threads shared by all OCR jobs; defaults to half of the available CPUs.')); ?></em>
			</div>
			<div class="div-table-col">
				<input type="number" class="small" id="tesseract_cpu_budget" min="1" step="1"
					value="<?php p($settings['tesseract_cpu_budget']); ?>"/>
			</div>
		</div>

		<div class="div-table-row tesseract_ocr_enabled">
			<div class="div-table-col div-table-col-left">
				<label class="leftcol" for="tesseract_parallel_jobs"><?php p($l->t('Parallel OCR jobs')); ?></label>
				<br/>
				<em><?php p($l->t('Maximum files processed at once, additionally constrained by the CPU budget.')); ?></em>
			</div>
			<div class="div-table-col">
				<input type="number" class="small" id="tesseract_parallel_jobs" min="1" step="1"
					value="<?php p($settings['tesseract_parallel_jobs']); ?>"/>
			</div>
		</div>

		<div class="div-table-row tesseract_ocr_enabled">
			<div class="div-table-col div-table-col-left">
				<label class="leftcol" for="tesseract_threads"><?php p($l->t('Threads per OCR job')); ?></label>
				<br/>
				<em><?php p($l->t('Maximum Tesseract threads per file; all files share the CPU budget.')); ?></em>
			</div>
			<div class="div-table-col">
				<input type="number" class="small" id="tesseract_threads" min="1" step="1"
					value="<?php p($settings['tesseract_threads']); ?>"/>
			</div>
		</div>

		<div class="div-table-row tesseract_ocr_enabled">
			<div class="div-table-col div-table-col-left">
				<label class="leftcol" for="tesseract_pdf"><?php p($l->t('PDF OCR')); ?></label>
				<br/>
				<em><?php p($l->t('OCR PDF pages with meaningful raster images; requires Poppler pdfimages.')); ?></em>
			</div>
			<div class="div-table-col">
				<input type="checkbox" id="tesseract_pdf" value="1"
					<?php if ($settings['tesseract_pdf'] === '1') { ?>checked<?php } ?>/>
			</div>
		</div>

		<div class="div-table-row tesseract_ocr_enabled">
			<div class="div-table-col div-table-col-left">
				<label class="leftcol" for="tesseract_pdf_limit"><?php p($l->t('Limit PDF pages')); ?></label>
				<br/>
				<em><?php p($l->t('Limit PDF OCR to the first number of pages; zero means no limit.')); ?></em>
			</div>
			<div class="div-table-col">
				<input type="number" class="small" id="tesseract_pdf_limit" min="0" step="1"
					value="<?php p($settings['tesseract_pdf_limit']); ?>"/>
			</div>
		</div>

	</div>


</div>
