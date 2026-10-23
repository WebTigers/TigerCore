<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Migration 0055 — the `sms_consent` store (the TCPA / 10DLC consent record, TIGER-376).
 *
 * A verified mobile number is an auth factor (user_credential type `sms`); CONSENT to actually TEXT
 * that number is a separate, auditable fact carriers/TCR require you to be able to produce — who
 * consented, when, from what IP, to which message categories (scopes), and whether they have since
 * opted out (STOP). This is that record. One active row per user (the live-override shape: the model
 * revives + overwrites the user's row rather than inserting duplicates, exactly like
 * Tiger_Model_EmailTemplate::store()), so the `(user_id)` unique spans soft-deleted rows deliberately.
 *
 * `phone` is the E.164 the consent was captured for; `scopes` is a CSV of consented categories
 * (`twofa`,`alerts` — the 10DLC campaign's sub-use-cases); `consent_text` snapshots the exact language
 * shown; `opt_out_at` is stamped when a STOP arrives (the suppression signal every Tiger_Sms alert
 * sender consults). See Tiger_Model_SmsConsent and modules/profile Profile_Service_Sms.
 */
return [
    'up' => [
        "CREATE TABLE IF NOT EXISTS `sms_consent` (
            `sms_consent_id` CHAR(36)     NOT NULL,
            `user_id`        CHAR(36)     NOT NULL,
            `phone`          VARCHAR(32)  NOT NULL DEFAULT '',
            `scopes`         VARCHAR(191) NOT NULL DEFAULT '',
            `consent_text`   VARCHAR(255) NOT NULL DEFAULT '',
            `consent_ip`     VARCHAR(45)      NULL,
            `consent_at`     DATETIME         NULL,
            `opt_out_at`     DATETIME         NULL,
            `status`         VARCHAR(16)  NOT NULL DEFAULT 'active',
            `deleted`        TINYINT(1)   NOT NULL DEFAULT 0,
            `created_by`     CHAR(36)         NULL,
            `updated_by`     CHAR(36)         NULL,
            `created_at`     DATETIME     NOT NULL,
            `updated_at`     DATETIME         NULL,
            PRIMARY KEY (`sms_consent_id`),
            UNIQUE KEY `uq_sms_consent_user` (`user_id`),
            KEY `idx_sms_consent_phone` (`phone`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    ],
    'down' => [
        "DROP TABLE IF EXISTS `sms_consent`",
    ],
];
