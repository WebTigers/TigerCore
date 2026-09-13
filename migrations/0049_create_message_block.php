<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Migration 0049 — one user blocking another (TIGER-114).
 *
 * One-directional and private: `user_id` no longer receives from `blocked_user_id`. The blocked
 * person is not told, and their send appears to succeed — telling them turns a mute into a
 * confrontation. System messages ignore this table entirely; an operator must not be able to make
 * the platform unable to reach them.
 *
 * `org_id` is here because role lives on org_user, not user: "cannot block an admin" is a question
 * about a role IN AN ORG, and the block is scoped to match.
 */
return [
    'up' => [
        "CREATE TABLE IF NOT EXISTS `message_block` (
            `message_block_id` CHAR(36)   NOT NULL,
            `org_id`           CHAR(36)   NOT NULL DEFAULT '',
            `user_id`          CHAR(36)   NOT NULL,
            `blocked_user_id`  CHAR(36)   NOT NULL,
            `status`           TINYINT(1) NOT NULL DEFAULT 1,
            `deleted`          TINYINT(1) NOT NULL DEFAULT 0,
            `created_by`       CHAR(36)       NULL,
            `updated_by`       CHAR(36)       NULL,
            `created_at`       DATETIME   NOT NULL,
            `updated_at`       DATETIME       NULL,
            PRIMARY KEY (`message_block_id`),
            UNIQUE KEY `uq_message_block` (`org_id`, `user_id`, `blocked_user_id`),
            KEY `idx_block_blocked` (`blocked_user_id`, `org_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        "DROP TABLE IF EXISTS `message_block`",
    ],
];
