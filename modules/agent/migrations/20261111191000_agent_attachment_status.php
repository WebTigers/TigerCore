<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Standard columns on `agent_attachment` (TIGER-488): every table carries status, deleted, created_by,
 * updated_by, created_at and updated_at. Adds `status`. Defaults keep every existing row and query
 * behaving exactly as before.
 */
return [
    'up' => [
        "ALTER TABLE `agent_attachment` ADD COLUMN `status` VARCHAR(32) NOT NULL DEFAULT 'active'",
    ],
    'down' => [
        "ALTER TABLE `agent_attachment` DROP COLUMN `status`",
    ],
];
