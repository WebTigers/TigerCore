<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Standard columns on `login` (TIGER-488): every table carries status, deleted, created_by, updated_by,
 * created_at and updated_at. Adds `status`, `deleted`, `created_by`, `updated_by`, `updated_at`.
 * The sign-in audit log stays append-only: nothing soft-deletes or edits an attempt, so `deleted`
 * and `status` hold their defaults.
 */
return [
    'up' => [
        "ALTER TABLE `login`
            ADD COLUMN `status`     VARCHAR(32) NOT NULL DEFAULT 'active',
            ADD COLUMN `deleted`    TINYINT(1)  NOT NULL DEFAULT 0,
            ADD COLUMN `created_by` CHAR(36)    NULL,
            ADD COLUMN `updated_by` CHAR(36)    NULL,
            ADD COLUMN `updated_at` DATETIME    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        "UPDATE `login` SET `updated_at` = `created_at`",
    ],
    'down' => [
        "ALTER TABLE `login` DROP COLUMN `status`, DROP COLUMN `deleted`, DROP COLUMN `created_by`, DROP COLUMN `updated_by`, DROP COLUMN `updated_at`",
    ],
];
