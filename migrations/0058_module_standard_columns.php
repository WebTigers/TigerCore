<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Standard columns on `module` (TIGER-488): every table carries status, deleted, created_by, updated_by,
 * created_at and updated_at. Adds `deleted`.
 * Defaults keep every existing row and query behaving exactly as before.
 */
return [
    'up' => [
        "ALTER TABLE `module`
            ADD COLUMN `deleted`    TINYINT(1)  NOT NULL DEFAULT 0",
    ],
    'down' => [
        "ALTER TABLE `module` DROP COLUMN `deleted`",
    ],
];
