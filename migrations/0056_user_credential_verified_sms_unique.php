<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Migration 0056 — a GLOBAL unique on a VERIFIED `sms` factor's phone number (TIGER-376).
 *
 * A phone number is a login identifier (Tiger_Model_User::findByIdentifier() resolves a verified `sms`
 * factor → its user, so you can log in by phone + get an OTP/magic link). For that to be unambiguous a
 * verified number must map to exactly ONE user — the same guarantee `user.email`/`user.username` carry.
 * The base table (migration 0004) only has `UNIQUE(user_id,type,identifier)` (per-user) + a NON-unique
 * `KEY(type,identifier)`, so two users could both verify the same number and break login-by-phone.
 *
 * This adds the active-only unique the convention uses platform-wide (the `IF(...)` generated-column +
 * UNIQUE pattern of migration 0053): a VIRTUAL column that holds the identifier ONLY for a live, VERIFIED
 * sms factor and NULL otherwise, so (a) only verified rows contend (a pending/unverified enrollment, an
 * oauth/webauthn/password/pat row, or a soft-deleted/removed number all fall out as NULL, which a UNIQUE
 * index ignores), and (b) removing a number frees it for another account. The real columns are untouched;
 * the service still re-checks findVerifiedByIdentifier() before confirming, with this index as the backstop.
 */
return [
    'up' => [
        "ALTER TABLE `user_credential`
            ADD COLUMN `verified_sms_key` VARCHAR(191)
            GENERATED ALWAYS AS (IF(`type` = 'sms' AND `verified_at` IS NOT NULL AND `deleted` = 0, `identifier`, NULL)) VIRTUAL",
        "ALTER TABLE `user_credential`
            ADD UNIQUE KEY `uq_cred_verified_sms` (`verified_sms_key`)",
    ],
    'down' => [
        "ALTER TABLE `user_credential` DROP INDEX `uq_cred_verified_sms`",
        "ALTER TABLE `user_credential` DROP COLUMN `verified_sms_key`",
    ],
];
