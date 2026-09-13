<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Migration 0047 — the `message` store (TIGER-114).
 *
 * ONE row per message, however many people receive it. Who received it and whether they have read
 * it lives in `message_recipient` (0048): a message to five admins is one body plus five recipient
 * rows, not five copies, and "unread count" is an indexed count on the recipient table rather than
 * a scan of bodies.
 *
 * `sender_user_id` NULL means THE APP sent it. NULL rather than a reserved system user, so there is
 * never a row in `user` that someone can edit, delete, or log in as. `kind` says which surface a row
 * came from — `system` (the app talking to operators) or `user` — because the two obey different
 * rules: a system message ignores blocks, and user-to-user is off unless an operator turns it on.
 *
 * `parent_id` records a reply. It is in the schema so a reply is not lost, but v1 shows a flat list;
 * threading UI is deliberately later.
 */
return [
    'up' => [
        "CREATE TABLE IF NOT EXISTS `message` (
            `message_id`     CHAR(36)     NOT NULL,
            `org_id`         CHAR(36)     NOT NULL DEFAULT '',
            `parent_id`      CHAR(36)         NULL,
            `sender_user_id` CHAR(36)         NULL,
            `kind`           VARCHAR(16)  NOT NULL DEFAULT 'user',
            `subject`        VARCHAR(191) NOT NULL DEFAULT '',
            `body`           TEXT             NULL,
            `status`         TINYINT(1)   NOT NULL DEFAULT 1,
            `deleted`        TINYINT(1)   NOT NULL DEFAULT 0,
            `created_by`     CHAR(36)         NULL,
            `updated_by`     CHAR(36)         NULL,
            `created_at`     DATETIME     NOT NULL,
            `updated_at`     DATETIME         NULL,
            PRIMARY KEY (`message_id`),
            KEY `idx_message_org`    (`org_id`, `created_at`),
            KEY `idx_message_sender` (`sender_user_id`, `created_at`),
            KEY `idx_message_parent` (`parent_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        "DROP TABLE IF EXISTS `message`",
    ],
];
