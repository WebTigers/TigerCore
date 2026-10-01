<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Migration 0053 — active-only UNIQUE indexes on `user.email` + `user.username` (TIGER-274).
 *
 * A plain UNIQUE spans soft-deleted rows too (ARCHITECTURE §7a: "a deleted row still holds its unique
 * value"), so soft-delete never frees an email/username — a re-signup with a deleted account's address
 * collides on the index instead of gracefully reusing it. Index a generated
 * `<col>_active = IF(deleted=0, col, NULL)` instead: MySQL/MariaDB allow multiple NULLs in a unique
 * index, so deleted rows drop out (NULL) and free the value, while live rows stay unique. The real
 * `email`/`username` columns are untouched — `Tiger_Model_User::findByEmail`/finders already filter
 * `deleted=0`, so no application code changes. This is the convention for every unique + soft-deletable
 * column platform-wide (see TIGER-274).
 */
return [
    'up' => [
        "ALTER TABLE `user` DROP INDEX `uq_user_email`",
        "ALTER TABLE `user` ADD COLUMN `email_active` VARCHAR(191) GENERATED ALWAYS AS (IF(`deleted`=0, `email`, NULL)) VIRTUAL",
        "ALTER TABLE `user` ADD UNIQUE KEY `uq_user_email_active` (`email_active`)",
        "ALTER TABLE `user` DROP INDEX `uq_user_username`",
        "ALTER TABLE `user` ADD COLUMN `username_active` VARCHAR(64) GENERATED ALWAYS AS (IF(`deleted`=0, `username`, NULL)) VIRTUAL",
        "ALTER TABLE `user` ADD UNIQUE KEY `uq_user_username_active` (`username_active`)",
    ],
    'down' => [
        "ALTER TABLE `user` DROP INDEX `uq_user_email_active`",
        "ALTER TABLE `user` DROP COLUMN `email_active`",
        "ALTER TABLE `user` ADD UNIQUE KEY `uq_user_email` (`email`)",
        "ALTER TABLE `user` DROP INDEX `uq_user_username_active`",
        "ALTER TABLE `user` DROP COLUMN `username_active`",
        "ALTER TABLE `user` ADD UNIQUE KEY `uq_user_username` (`username`)",
    ],
];
