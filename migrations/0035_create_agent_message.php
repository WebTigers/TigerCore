<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Migration 0035 — create `agent_message` (the conversation transcript).
 *
 * One row per message. `role` follows the chat convention (`user` | `assistant` |
 * `tool` | `system`); `content` is the human-facing prose (the model's `say`, or the
 * user's text). `meta` is a JSON sidecar for the structured payload that rode with an
 * assistant turn — the parsed actions + navigate + done contract (TIGERAGENT.md §5a) —
 * so the aside can re-render an old turn's action chips on page reload without re-running
 * anything. `run_id` links the turn that produced it.
 */
return [
    'up' => [
        "CREATE TABLE `agent_message` (
            `message_id`      CHAR(36)    NOT NULL,
            `conversation_id` CHAR(36)    NOT NULL,
            `run_id`          CHAR(36)        NULL,
            `role`            VARCHAR(16) NOT NULL DEFAULT 'user',   -- user | assistant | tool | system
            `content`         LONGTEXT        NULL,                  -- the prose (say / user text)
            `meta`            LONGTEXT        NULL,                  -- JSON: actions[], navigate, done
            `status`          VARCHAR(32) NOT NULL DEFAULT 'active',
            `deleted`         TINYINT(1)  NOT NULL DEFAULT 0,
            `created_by`      CHAR(36)        NULL,
            `updated_by`      CHAR(36)        NULL,
            `created_at`      DATETIME    NOT NULL,
            `updated_at`      DATETIME        NULL,
            PRIMARY KEY (`message_id`),
            KEY `ix_agent_msg_conv` (`conversation_id`, `deleted`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        "DROP TABLE IF EXISTS `agent_message`",
    ],
];
