<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Migration 0046 — `comment_aggregate`, the denormalized per-subject rollup (COMMENTS.md §3).
 *
 * Not premature optimization: a marketplace grid renders 60 cards, and computing an average per card
 * on read is 60 aggregate queries (or a join that defeats the multi-source merge). One row per
 * subject, recomputed inside the same transaction that approves, edits or removes a comment.
 *
 * `comment_count` and `rating_count` are separate on purpose — ratings are optional, so conflating
 * them would overstate engagement on any subject where people rate without writing.
 */
return [
    'up' => [
        "CREATE TABLE IF NOT EXISTS `comment_aggregate` (
            `comment_aggregate_id` CHAR(36)     NOT NULL,
            `org_id`               CHAR(36)     NOT NULL DEFAULT '',
            `subject_type`         VARCHAR(64)  NOT NULL,
            `subject_id`           VARCHAR(191) NOT NULL,
            `comment_count`        INT UNSIGNED NOT NULL DEFAULT 0,
            `rating_count`         INT UNSIGNED NOT NULL DEFAULT 0,
            `rating_avg`           DECIMAL(3,2) NOT NULL DEFAULT 0.00,
            `star_1`               INT UNSIGNED NOT NULL DEFAULT 0,
            `star_2`               INT UNSIGNED NOT NULL DEFAULT 0,
            `star_3`               INT UNSIGNED NOT NULL DEFAULT 0,
            `star_4`               INT UNSIGNED NOT NULL DEFAULT 0,
            `star_5`               INT UNSIGNED NOT NULL DEFAULT 0,
            `status`               VARCHAR(32)  NOT NULL DEFAULT 'active',
            `deleted`              TINYINT(1)   NOT NULL DEFAULT 0,
            `created_by`           CHAR(36)         NULL,
            `updated_by`           CHAR(36)         NULL,
            `created_at`           DATETIME     NOT NULL,
            `updated_at`           DATETIME         NULL,
            PRIMARY KEY (`comment_aggregate_id`),
            UNIQUE KEY `uq_comment_aggregate_subject` (`subject_type`, `subject_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        "DROP TABLE IF EXISTS `comment_aggregate`",
    ],
];
