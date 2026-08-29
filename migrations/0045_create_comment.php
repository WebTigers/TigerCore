<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Migration 0045 — the `comment` store (COMMENTS.md §3).
 *
 * ONE table for comments AND reviews: a review is a comment whose `rating` is set. That single
 * nullable column is the whole distinction — there is deliberately no `review` table, so there is
 * one moderation queue, one spam path and one admin screen.
 *
 * `subject_type` + `subject_id` is the polymorphic attachment. `subject_id` is VARCHAR(191) rather
 * than CHAR(36) because it has to hold whatever the owning module keys by — a UUID, a TID, a slug,
 * or an integer id.
 */
return [
    'up' => [
        "CREATE TABLE IF NOT EXISTS `comment` (
            `comment_id`   CHAR(36)     NOT NULL,
            `org_id`       CHAR(36)     NOT NULL DEFAULT '',
            `subject_type` VARCHAR(64)  NOT NULL,
            `subject_id`   VARCHAR(191) NOT NULL,
            `parent_id`    CHAR(36)         NULL,
            `depth`        TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `user_id`      CHAR(36)         NULL,
            `author_name`  VARCHAR(191)     NULL,
            `author_email` VARCHAR(191)     NULL,
            `body`         TEXT             NULL,
            `rating`       TINYINT UNSIGNED NULL,
            `verified`     TINYINT(1)   NOT NULL DEFAULT 0,
            `ip`           VARCHAR(45)      NULL,
            `user_agent`   VARCHAR(255)     NULL,
            `status`       VARCHAR(16)  NOT NULL DEFAULT 'pending',
            `deleted`      TINYINT(1)   NOT NULL DEFAULT 0,
            `created_by`   CHAR(36)         NULL,
            `updated_by`   CHAR(36)         NULL,
            `created_at`   DATETIME     NOT NULL,
            `updated_at`   DATETIME         NULL,
            PRIMARY KEY (`comment_id`),
            KEY `idx_comment_thread`  (`subject_type`, `subject_id`, `status`, `deleted`, `created_at`),
            KEY `idx_comment_parent`  (`parent_id`),
            KEY `idx_comment_user`    (`user_id`, `created_at`),
            KEY `idx_comment_moderate`(`status`, `deleted`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        "DROP TABLE IF EXISTS `comment`",
    ],
];
