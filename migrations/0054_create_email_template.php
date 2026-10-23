<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Migration 0054 — the `email_template` store (owner/agent-authorable transactional emails, TIGER-267).
 *
 * The DB-override tier for Tiger_Mail's file templates, exactly like `config`/`translation` override
 * their ini/file bases: a code-shipped `emails/<key>.phtml` is the branded default, and an active row
 * here OVERRIDES it (last wins) — an authored subject + HTML body (with `{{var}}` placeholders) that
 * Tiger_Mail::template() substitutes and wraps in the shared layout. No row → the file default renders
 * unchanged. Scoped `org_id` (''=global/platform default, else per-tenant), so one install's orgs can
 * each brand their own mail. See Tiger_Model_EmailTemplate + Tiger_Mail::template().
 *
 * `(org_id, template_key)` is unique across ALL rows (not active-only): unlike `user.email` — where a
 * soft-delete must FREE the value for a re-signup (TIGER-274) — an override's key is deliberately REUSED.
 * Tiger_Model_EmailTemplate::store() matches any row (soft-deleted included) and revives + overwrites it,
 * so "reset to default" then re-author updates the one row rather than inserting a second. A plain unique
 * is therefore the right integrity guard here and sidesteps a generated-column expression some engines
 * refuse (MySQL/MariaDB err 1901 on a multi-column CONCAT in GENERATED ALWAYS AS).
 */
return [
    'up' => [
        "CREATE TABLE IF NOT EXISTS `email_template` (
            `email_template_id` CHAR(36)     NOT NULL,
            `org_id`            CHAR(36)     NOT NULL DEFAULT '',
            `template_key`      VARCHAR(191) NOT NULL,
            `subject`           VARCHAR(255) NOT NULL DEFAULT '',
            `body`              LONGTEXT     NOT NULL,
            `status`            VARCHAR(16)  NOT NULL DEFAULT 'active',
            `deleted`           TINYINT(1)   NOT NULL DEFAULT 0,
            `created_by`        CHAR(36)         NULL,
            `updated_by`        CHAR(36)         NULL,
            `created_at`        DATETIME     NOT NULL,
            `updated_at`        DATETIME         NULL,
            PRIMARY KEY (`email_template_id`),
            UNIQUE KEY `uq_email_template_key` (`org_id`, `template_key`),
            KEY `idx_email_template_lookup` (`template_key`, `org_id`, `deleted`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        "DROP TABLE IF EXISTS `email_template`",
    ],
];
