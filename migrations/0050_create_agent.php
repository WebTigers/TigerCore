<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Migration 0050 — the agent registry (TIGER-151).
 *
 * TigerAgent was a singleton: one provider/model/key in the `tiger.agent.*` config tier. This
 * table lets an org register MANY agents, each with its own persona, provider, model and BYO key,
 * and marks one as the org's default. TigerRoundtable then seats REGISTERED agents rather than
 * minting throwaway ones of its own.
 *
 * No data is copied here on purpose. `Tiger_Agent::default()` reads through to this table and,
 * while it is empty, falls back to the legacy `tiger.agent.*` config keys — so an install that
 * has never opened the new UI behaves EXACTLY as before, still one agent. The first save in the
 * settings screen writes the real "Default" row and the fallback stops mattering. `mode_max`
 * stays an install-wide governance setting (`tiger.agent.mode_max`), not a per-agent column.
 *
 * `org_id` scopes the registry per tenant ('' = the platform/global default). `is_default` marks
 * the one agent the facade resolves to for a scope; the service keeps at most one default per org.
 * The key is stored encrypted (Tiger_Crypto), never in plaintext, exactly as the config key was.
 */
return [
    'up' => [
        "CREATE TABLE IF NOT EXISTS `agent` (
            `agent_id`    CHAR(36)     NOT NULL,
            `org_id`      CHAR(36)     NOT NULL DEFAULT '',
            `name`        VARCHAR(191) NOT NULL,
            `persona`     TEXT             NULL,
            `provider`    VARCHAR(64)  NOT NULL DEFAULT '',
            `model`       VARCHAR(191) NOT NULL DEFAULT '',
            `api_key_enc` TEXT             NULL,
            `enabled`     TINYINT(1)   NOT NULL DEFAULT 1,
            `is_default`  TINYINT(1)   NOT NULL DEFAULT 0,
            `status`      TINYINT(1)   NOT NULL DEFAULT 1,
            `deleted`     TINYINT(1)   NOT NULL DEFAULT 0,
            `created_by`  CHAR(36)         NULL,
            `updated_by`  CHAR(36)         NULL,
            `created_at`  DATETIME     NOT NULL,
            `updated_at`  DATETIME         NULL,
            PRIMARY KEY (`agent_id`),
            KEY `idx_agent_default` (`org_id`, `is_default`, `deleted`),
            KEY `idx_agent_org`     (`org_id`, `deleted`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        "DROP TABLE IF EXISTS `agent`",
    ],
];
