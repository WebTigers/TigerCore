<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Standard columns on `schedule_run` (TIGER-488): every table carries status, deleted, created_by, updated_by,
 * created_at and updated_at. Adds `status`, `deleted`.
 * A run's result stays in `outcome`; `status` is the standard row status and holds 'active'.
 */
return [
    'up' => [
        "ALTER TABLE `schedule_run`
            ADD COLUMN `status`     VARCHAR(32) NOT NULL DEFAULT 'active',
            ADD COLUMN `deleted`    TINYINT(1)  NOT NULL DEFAULT 0",
    ],
    'down' => [
        "ALTER TABLE `schedule_run` DROP COLUMN `status`, DROP COLUMN `deleted`",
    ],
];
