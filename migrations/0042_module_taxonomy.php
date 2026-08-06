<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Migration 0042 — add taxonomy (`type` + `category`) to `module`.
 *
 * Captured at INSTALL from the source (the registry listing / the module's manifest) so an installed
 * module RETAINS the same taxonomy the Add Module screen showed. The Modules admin resolves a module's
 * type/category as: this stored value → the live manifest (Tiger_Module_Discovery) → the default
 * (plugin | theme | code). `category` is a comma-joined list (usually one). See AUTHORING.md.
 */
return [
    'up' => [
        "ALTER TABLE `module`
            ADD COLUMN `type`     VARCHAR(32)  NULL DEFAULT NULL AFTER `ref`,
            ADD COLUMN `category` VARCHAR(191) NULL DEFAULT NULL AFTER `type`",
    ],
    'down' => [
        "ALTER TABLE `module` DROP COLUMN `category`, DROP COLUMN `type`",
    ],
];
