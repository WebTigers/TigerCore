<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Migration 0051 — the `newsletter_subscriber` store (newsletter module).
 *
 * The one place a newsletter opt-in lands. A subscriber is a consent-first record: `status` moves
 * pending → confirmed (double opt-in) → unsubscribed, and `consent_source` + `consent_at` say how and
 * when the person agreed. `token` is the opaque handle in the confirm / unsubscribe links, so those
 * paths never expose the row id or the email.
 *
 * `email` is unique PER ORG (a tenant keeps its own list) — a re-subscribe updates the existing row
 * rather than stacking a second one. `user_id` is nullable: most subscribers are guests who only ever
 * gave an address, never an account.
 */
return [
    'up' => [
        "CREATE TABLE IF NOT EXISTS `newsletter_subscriber` (
            `newsletter_subscriber_id` CHAR(36)     NOT NULL,
            `org_id`         CHAR(36)     NOT NULL DEFAULT '',
            `email`          VARCHAR(191) NOT NULL,
            `name`           VARCHAR(191)     NULL,
            `user_id`        CHAR(36)         NULL,
            `status`         VARCHAR(16)  NOT NULL DEFAULT 'pending',
            `consent_source` VARCHAR(64)      NULL,
            `consent_at`     DATETIME         NULL,
            `token`          CHAR(36)         NULL,
            `source`         VARCHAR(191)     NULL,
            `ip`             VARCHAR(45)      NULL,
            `user_agent`     VARCHAR(255)     NULL,
            `unsubscribed_at` DATETIME        NULL,
            `deleted`        TINYINT(1)   NOT NULL DEFAULT 0,
            `created_by`     CHAR(36)         NULL,
            `updated_by`     CHAR(36)         NULL,
            `created_at`     DATETIME     NOT NULL,
            `updated_at`     DATETIME         NULL,
            PRIMARY KEY (`newsletter_subscriber_id`),
            UNIQUE KEY `uq_newsletter_org_email` (`org_id`, `email`),
            KEY `idx_newsletter_token`  (`token`),
            KEY `idx_newsletter_status` (`org_id`, `status`, `deleted`),
            KEY `idx_newsletter_recent` (`ip`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        "DROP TABLE IF EXISTS `newsletter_subscriber`",
    ],
];
