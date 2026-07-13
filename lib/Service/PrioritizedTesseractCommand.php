<?php
declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Files_FullTextSearch_Tesseract\Service;


use thiagoalessio\TesseractOCR\Command;


class PrioritizedTesseractCommand extends Command {

	public function __construct(private ProcessPriorityService $processPriorityService) {
		parent::__construct();
	}


	public function __toString() {
		return $this->processPriorityService->prioritizeShellCommand(parent::__toString());
	}
}
