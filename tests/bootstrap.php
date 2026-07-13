<?php
declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

use Composer\Autoload\ClassLoader;

/** @var ClassLoader $loader */
$loader = require dirname(__DIR__) . '/vendor/autoload.php';
$loader->setClassMapAuthoritative(false);
$loader->addPsr4('OCP\\', dirname(__DIR__) . '/vendor/nextcloud/ocp/OCP');
$loader->addPsr4('NCU\\', dirname(__DIR__) . '/vendor/nextcloud/ocp/NCU');
