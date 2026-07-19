<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Migration 0036 — create `agent_run` (one turn's execution record).
 *
 * A "turn" = one aside POST → one model call → the Forge acting on the response
 * (TIGERAGENT.md §5). The run is the audit + control record for that turn: what the
 * model proposed, what the Forge actually executed, and the outcome of each action.
 *
 * `actions` holds the full JSON ledger (each action's type, params, status, result) so
 * the human-in-the-loop approval flow can find a still-`proposed` write action later and
 * execute it (Agent_Service_Agent::approve) without re-calling the model. `status` is the
 * turn verdict (`ok` | `partial` | `blocked` | `error`); `blocked` means at least one
 * write action is waiting on approval. Token counts are captured for the budget/cost view.
 */
return [
    'up' => [
        "CREATE TABLE `agent_run` (
            `run_id`          CHAR(36)    NOT NULL,
            `conversation_id` CHAR(36)    NOT NULL,
            `user_id`         CHAR(36)        NULL,
            `status`          VARCHAR(16) NOT NULL DEFAULT 'ok',    -- ok | partial | blocked | error
            `steps`           INT         NOT NULL DEFAULT 1,
            `input_tokens`    INT             NULL,
            `output_tokens`   INT             NULL,
            `actions`         LONGTEXT        NULL,                 -- JSON ledger of proposed/executed actions
            `error`           TEXT            NULL,
            `deleted`         TINYINT(1)  NOT NULL DEFAULT 0,
            `created_by`      CHAR(36)        NULL,
            `updated_by`      CHAR(36)        NULL,
            `created_at`      DATETIME    NOT NULL,
            `updated_at`      DATETIME        NULL,
            PRIMARY KEY (`run_id`),
            KEY `ix_agent_run_conv` (`conversation_id`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        "DROP TABLE IF EXISTS `agent_run`",
    ],
];
