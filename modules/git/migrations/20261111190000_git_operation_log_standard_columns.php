<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Standard columns on `git_operation_log` (TIGER-488): every table carries status, deleted, created_by, updated_by,
 * created_at and updated_at. Adds `status`, `deleted`, `created_by`, `updated_by`, `updated_at`.
 * Defaults keep every existing row and query behaving exactly as before.
 */
return [
    'up' => [
        "ALTER TABLE `git_operation_log`
            ADD COLUMN `status`     VARCHAR(32) NOT NULL DEFAULT 'active',
            ADD COLUMN `deleted`    TINYINT(1)  NOT NULL DEFAULT 0,
            ADD COLUMN `created_by` CHAR(36)    NULL,
            ADD COLUMN `updated_by` CHAR(36)    NULL,
            ADD COLUMN `updated_at` DATETIME    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        "UPDATE `git_operation_log` SET `updated_at` = `created_at`",
    ],
    'down' => [
        "ALTER TABLE `git_operation_log` DROP COLUMN `status`, DROP COLUMN `deleted`, DROP COLUMN `created_by`, DROP COLUMN `updated_by`, DROP COLUMN `updated_at`",
    ],
];
