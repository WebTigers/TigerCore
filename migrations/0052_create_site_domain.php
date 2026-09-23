<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Migration 0052 — the `site_domain` store (multi-site: host → org).
 *
 * Maps a request hostname to the org (tenant) whose public site it serves, so ONE Tiger install can
 * host many branded sites — one per org — resolved by the request Host. On a match the whole request
 * scopes to that org: CMS pages/redirects (PageDispatch) and the org-scoped config tier (theme, skin,
 * home page, settings). No mapping → the install behaves as a single site (the founding / configured
 * site org). See Tiger_Model_SiteDomain + Tiger_Application_Bootstrap::_initSiteOrg.
 *
 * `domain` is unique across the WHOLE install (a hostname resolves to exactly one org). A site's `www`
 * and apex are separate rows (the driver creates both, matching the vhost + AutoSSL names).
 */
return [
    'up' => [
        "CREATE TABLE IF NOT EXISTS `site_domain` (
            `site_domain_id` CHAR(36)     NOT NULL,
            `domain`         VARCHAR(191) NOT NULL,
            `org_id`         CHAR(36)     NOT NULL,
            `status`         VARCHAR(16)  NOT NULL DEFAULT 'active',
            `deleted`        TINYINT(1)   NOT NULL DEFAULT 0,
            `created_by`     CHAR(36)         NULL,
            `updated_by`     CHAR(36)         NULL,
            `created_at`     DATETIME     NOT NULL,
            `updated_at`     DATETIME         NULL,
            PRIMARY KEY (`site_domain_id`),
            UNIQUE KEY `uq_site_domain_host` (`domain`),
            KEY `idx_site_domain_org` (`org_id`, `deleted`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        "DROP TABLE IF EXISTS `site_domain`",
    ],
];
