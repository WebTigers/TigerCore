<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Migration 0034 — create `agent_conversation` (a TigerAgent chat thread).
 *
 * One row per conversation held in the right-aside agent. Org-scoped (the tenant) and
 * user-owned (the person the agent acts *as* — its whole capability derives from that
 * user's role, see TIGERAGENT.md §0). The conversation is the durable spine; individual
 * turns are `agent_run` rows and the transcript is `agent_message` rows.
 *
 * `provider`/`model` are captured per conversation so a thread stays coherent even if the
 * install later changes its default provider. Standard columns per AGENTS.md.
 */
return [
    'up' => [
        "CREATE TABLE `agent_conversation` (
            `conversation_id` CHAR(36)     NOT NULL,
            `org_id`          CHAR(36)         NULL,               -- the tenant
            `user_id`         CHAR(36)         NULL,               -- the owner (whose role the agent inherits)
            `title`           VARCHAR(191) NOT NULL DEFAULT '',    -- derived from the first message
            `provider`        VARCHAR(32)  NOT NULL DEFAULT '',    -- anthropic | openai | …
            `model`           VARCHAR(64)  NOT NULL DEFAULT '',
            `status`          VARCHAR(32)  NOT NULL DEFAULT 'active',
            `deleted`         TINYINT(1)   NOT NULL DEFAULT 0,
            `created_by`      CHAR(36)         NULL,
            `updated_by`      CHAR(36)         NULL,
            `created_at`      DATETIME     NOT NULL,
            `updated_at`      DATETIME         NULL,
            PRIMARY KEY (`conversation_id`),
            KEY `ix_agent_conv_owner` (`org_id`, `user_id`, `deleted`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        "DROP TABLE IF EXISTS `agent_conversation`",
    ],
];
