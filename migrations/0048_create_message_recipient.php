<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Migration 0048 — who received each message, and what they did with it (TIGER-114).
 *
 * Read state is PER PERSON, so it cannot live on the message. `read_at` NULL = unread; `archived_at`
 * NULL = in the inbox. `deleted` here is the recipient deleting THEIR copy — the message row and every
 * other recipient's copy are untouched, which is what a person expects "delete" to mean in an inbox.
 *
 * The (`user_id`, `read_at`, `deleted`) index is the one the header bell hits on every admin page
 * render, so it must be a pure index count.
 */
return [
    'up' => [
        "CREATE TABLE IF NOT EXISTS `message_recipient` (
            `message_recipient_id` CHAR(36)   NOT NULL,
            `message_id`           CHAR(36)   NOT NULL,
            `user_id`              CHAR(36)   NOT NULL,
            `read_at`              DATETIME       NULL,
            `archived_at`          DATETIME       NULL,
            `status`               TINYINT(1) NOT NULL DEFAULT 1,
            `deleted`              TINYINT(1) NOT NULL DEFAULT 0,
            `created_by`           CHAR(36)       NULL,
            `updated_by`           CHAR(36)       NULL,
            `created_at`           DATETIME   NOT NULL,
            `updated_at`           DATETIME       NULL,
            PRIMARY KEY (`message_recipient_id`),
            UNIQUE KEY `uq_message_recipient` (`message_id`, `user_id`),
            KEY `idx_recipient_inbox`  (`user_id`, `deleted`, `archived_at`, `created_at`),
            KEY `idx_recipient_unread` (`user_id`, `read_at`, `deleted`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        "DROP TABLE IF EXISTS `message_recipient`",
    ],
];
