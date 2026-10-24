<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Migration 0057 — add `expires_at` to `user_credential` (TIGER-378).
 *
 * A personal access token (`type = 'personal_access_token'`) was mint-once and immortal: it died only by
 * an explicit revoke (soft-delete). That is the Zeabur-#1 echo for the long-lived MACHINE PATs the fleet
 * mints (a node↔control-plane `host`/`billing`/`vps` token) — a leaked one is valid until a human notices.
 *
 * This adds a single additive, NULLABLE column: `expires_at`. NULL = never expires (every existing row,
 * and every ordinary user PAT that opts out of a TTL — unchanged behaviour). A non-NULL value is enforced
 * at verify time (`verifyTokenCredential` rejects a token past it) and swept by the
 * `core.auth.purge_expired_tokens` housekeeping job. Machine PATs are minted WITH a TTL so a leak self-heals.
 *
 * Nullable + no default, so the ALTER is instant and no backfill is needed; it applies to every factor row
 * but only PATs ever set it.
 */
return [
    'up' => [
        "ALTER TABLE `user_credential` ADD COLUMN `expires_at` DATETIME NULL AFTER `verified_at`",
    ],
    'down' => [
        "ALTER TABLE `user_credential` DROP COLUMN `expires_at`",
    ],
];
