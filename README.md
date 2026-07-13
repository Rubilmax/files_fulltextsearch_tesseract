<!--
  - SPDX-FileCopyrightText: 2018 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Full text search - Files - Tesseract OCR

[![REUSE status](https://api.reuse.software/badge/github.com/nextcloud/files_fulltextsearch_tesseract)](https://api.reuse.software/info/github.com/nextcloud/files_fulltextsearch_tesseract)

This Nextcloud app extracts searchable text from images and image-bearing PDF pages with
[Tesseract OCR](https://github.com/tesseract-ocr/tesseract). It extends
[Full text search - Files](https://github.com/nextcloud/files_fulltextsearch).

## Requirements

- Nextcloud 31 or newer, including Nextcloud 34. The manifest uses a deliberately high compatibility
  ceiling, so a new major is installable by default.
- PHP 8.2 or newer with the Imagick extension.
- Tesseract available as `tesseract` in the web/cron user's `PATH`.
- ImageMagick with PDF reading enabled and a PDF delegate such as Ghostscript for PDF OCR.
- The `fulltextsearch`, `files_fulltextsearch`, and a full-text search platform app.
- Poppler's `pdfimages` is required for PDF OCR so the app can select pages with meaningful raster
  images. Poppler's `pdfinfo` and `pdftoppm` are recommended for faster page counting and grayscale
  batch rendering; without them, those operations fall back to Imagick.

Download the required Tesseract language data from
[tessdata](https://github.com/tesseract-ocr/tessdata) and install it in the location expected by
your Tesseract package, commonly `/usr/share/tessdata/` or `/usr/share/tesseract-ocr/tessdata/`.

## Install directly as a Git submodule

Production Composer dependencies are committed in `vendor/`; no Composer command is needed on the
Nextcloud server. The checkout directory must match the app ID exactly:

```sh
cd /path/to/nextcloud
git submodule add https://github.com/nextcloud/files_fulltextsearch_tesseract.git \
  apps/files_fulltextsearch_tesseract
php occ app:enable files_fulltextsearch_tesseract
```

To update the submodule later:

```sh
git submodule update --remote apps/files_fulltextsearch_tesseract
php occ upgrade
```

## Configure

Open **Administration settings → Full text search → Files - Tesseract OCR**.

On a first install, OCR is enabled and PDF OCR remains disabled until an administrator enables it.
The settings page starts with page segmentation mode `4`, English (`eng`), one Tesseract thread per
job, and no PDF page limit. The shared CPU budget defaults to half of the available logical CPUs and
the parallel-job limit initially matches that budget.

By default, all OCR jobs share a CPU budget of half the available logical CPUs, and Tesseract uses
one thread per file. Container CPU quotas are taken into account when available. The effective job
and thread limits are always constrained so their product does not exceed the shared CPU budget.
Tesseract and Poppler helpers also run with a lower scheduler priority when POSIX `nice` is
available.

For multi-page PDFs, candidate pages are passed to one Tesseract process so language data is loaded
once per document instead of once per page. Small decorative images and transparency masks do not
make a page an OCR candidate; the app uses raster dimensions and effective PDF image resolution to
identify pages likely to contain scanned text. An existing text layer does not prevent OCR when a
page contains a meaningful raster image.

After changing OCR settings, reset or reindex existing documents with the commands provided by the
Full text search app so their indexed text reflects the new configuration.

## Build and test

```sh
composer install
composer test
make appstore
```

`make appstore` performs a locked, production-only Composer install and creates the app-store
archive under `build/artifacts/`.

For PDF and OCR background, see the
[original development notes](https://daita.github.io/files-fulltextsearch-tesseract-ocr-pdf/).
