<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Standard columns on `session` (TIGER-488): every table carries status, deleted, created_by, updated_by,
 * created_at and updated_at. Adds `status`, `deleted`, `created_by`, `updated_by`, `created_at`, `updated_at`.
 * The table is written by Zend's save handler, not Tiger_Model_Table, so the timestamps are
 * kept by the database (DEFAULT / ON UPDATE CURRENT_TIMESTAMP) and the rest hold their defaults.
 */
return [
    'up' => [
        "ALTER TABLE `session`
            ADD COLUMN `status`     VARCHAR(32) NOT NULL DEFAULT 'active',
            ADD COLUMN `deleted`    TINYINT(1)  NOT NULL DEFAULT 0,
            ADD COLUMN `created_by` CHAR(36)    NULL,
            ADD COLUMN `updated_by` CHAR(36)    NULL,
            ADD COLUMN `created_at` DATETIME    NULL DEFAULT CURRENT_TIMESTAMP,
            ADD COLUMN `updated_at` DATETIME    NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
    ],
    'down' => [
        "ALTER TABLE `session` DROP COLUMN `status`, DROP COLUMN `deleted`, DROP COLUMN `created_by`, DROP COLUMN `updated_by`, DROP COLUMN `created_at`, DROP COLUMN `updated_at`",
    ],
];
