<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Migration 0043 — menu provenance (`source` + `source_key`).
 *
 * Records where a menu came from so the CMS Menus admin can show a Type (Theme vs Custom) and the
 * originating theme's name, and so a forked theme menu survives — and stays labelled — after the
 * theme is deactivated (THEMES.md §4b). Rows are stamped on fork/import:
 *   - `source`     'user' (admin-authored, the default) | 'theme' (materialized from a theme's menus.ini)
 *   - `source_key` the provider key, e.g. the theme key 'crafto-interior' (NULL for user menus)
 * A theme's *un-forked* menus stay files (menus.ini) — these columns only describe DB rows.
 */
return [
    'up' => [
        "ALTER TABLE `menu`
            ADD COLUMN `source`     VARCHAR(16)  NOT NULL DEFAULT 'user' AFTER `menu_key`,
            ADD COLUMN `source_key` VARCHAR(191)     NULL                AFTER `source`",
    ],
    'down' => [
        "ALTER TABLE `menu` DROP COLUMN `source_key`, DROP COLUMN `source`",
    ],
];
