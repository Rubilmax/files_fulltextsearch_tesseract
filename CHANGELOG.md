<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Changelog

## 34.0.3

### Added

- Register all displayed app strings for translation through Nextcloud's Transifex workflow.

### Changed

- Replace the legacy administration template and custom AJAX scripts with Nextcloud's native
  declarative settings form.
- Use standard Nextcloud 34 controls and automatic persistence for all OCR settings.

### Fixed

- Keep server-side validation and normalization when native autosave updates a setting.

## 34.0.2

### Fixed

- Save number and text settings when their value changes, including spinner-button updates.

## 34.0.1

### Changed

- Enable OCR by default on a first install.
- Select PDF pages for OCR solely by meaningful raster images, regardless of any existing text
  layer; require Poppler's `pdfimages` for this inspection.
- Remove PDF text-layer inspection and its administrator setting.

### Fixed

- Populate the administration form immediately with effective defaults and saved values.

## 34.0.0

### Added

- Compatibility with Nextcloud 34 and installability on later Nextcloud majors by default.
- A shared OCR CPU budget with container-aware defaults and lower-priority helper processes.
- Optional Poppler inspection to skip PDF pages that do not need OCR.
- Poppler-based grayscale batch rendering for selected PDF page ranges.
- Production Composer dependencies in `vendor/` for direct Git submodule installation.
- Automated unit tests for configuration, event handling, file localization, locking, and PDF text
  inspection.

### Changed

- Use Nextcloud's public, app-scoped configuration and file APIs.
- Support local, external, and object-storage files through local temporary copies when needed.
- Validate and normalize all administrator-provided command options.
- Serialize settings writes so rapid changes cannot be persisted out of order.
- Use deterministic locked dependencies in app-store builds.
- Reuse one Tesseract process for all OCR candidate pages in a PDF.
- Avoid initializing Imagick or inspecting PDF images when every page already has useful text.
- Ignore small decorative PDF images and masks when selecting pages for OCR.
- Back off lock polling while all OCR capacity is occupied.

### Fixed

- Avoid crashes when unrelated generic events have non-string subjects.
- Clean up temporary input and PDF image files on success and failure.
- Log OCR failures with document context instead of silently dropping them.
