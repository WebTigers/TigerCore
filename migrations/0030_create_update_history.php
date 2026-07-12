<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Migration 0030 — create `update_history` (the durable record behind the Updates screen).
 *
 * Every apply of the one-click Updates screen writes a row here: what was updated, from→to versions,
 * the outcome (success | failed | rolled_back | advisory), and the full step `log` (JSON) — so "what
 * ran, and what broke?" is answerable long after the live run scrolled away (steps also go to
 * Tiger_Log in the moment). `created_by` = the operator; `created_at` = when. Read via
 * Tiger_Model_UpdateHistory. Additive; no dependency on any other table.
 */
return [
    'up' => [
        "CREATE TABLE `update_history` (
            `update_id`    CHAR(36)     NOT NULL,                     -- UUID v7 (time-ordered)
            `item_type`    VARCHAR(16)  NOT NULL,                     -- core | module
            `item_slug`    VARCHAR(191) NOT NULL,                     -- tiger-core | <module slug>
            `item_name`    VARCHAR(191)     NULL,
            `from_version` VARCHAR(64)      NULL,
            `to_version`   VARCHAR(64)      NULL,
            `outcome`      VARCHAR(16)  NOT NULL,                     -- success | failed | rolled_back | advisory
            `log`          LONGTEXT         NULL,                     -- JSON: [{step, ok, detail}, …]
            `status`       VARCHAR(16)  NOT NULL DEFAULT 'active',
            `deleted`      TINYINT(1)   NOT NULL DEFAULT 0,
            `created_by`   CHAR(36)         NULL,                     -- the operator who ran it
            `updated_by`   CHAR(36)         NULL,
            `created_at`   DATETIME     NOT NULL,
            `updated_at`   DATETIME         NULL,
            PRIMARY KEY (`update_id`),
            KEY `ix_update_history_created` (`created_at`),
            KEY `ix_update_history_slug` (`item_slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        "DROP TABLE IF EXISTS `update_history`",
    ],
];
