<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Migration 0054 — Tiger_Git data model (TIGER-334 / TIGER-366).
 *
 * Five tables, standard columns throughout (ARCHITECTURE §7a). UUID CHAR(36) PKs; unique text
 * VARCHAR(191). The two connection-identity uniques are ACTIVE-ONLY (a generated
 * `module_key_active = IF(deleted=0, module_key, NULL)` + a composite unique), so disconnecting a
 * module (soft-delete) FREES `(org_id, module_key)` and a reconnect is not blocked — the exact class of
 * bug that bit `hosting_node.name` on node re-enroll (see TIGER-274).
 *
 * No secret is stored in the clear: `git_credential.ciphertext` is a Tiger_Crypto blob; the only
 * readable credential fields are the public key and a fingerprint. `git_operation_log` is an
 * append-only audit trail — actor, operation, refs before/after, result — and holds no secret and no
 * file contents.
 */
return [
    'up' => [
        // --- a module <-> repo connection -------------------------------------------------
        "CREATE TABLE `git_repo_connection` (
            `git_repo_connection_id` CHAR(36) NOT NULL,
            `org_id`          CHAR(36) NOT NULL,
            `module_key`      VARCHAR(191) NOT NULL,
            `provider`        VARCHAR(32) NOT NULL DEFAULT 'github',
            `repo_owner`      VARCHAR(100) NOT NULL,
            `repo_name`       VARCHAR(100) NOT NULL,
            `default_branch`  VARCHAR(191) NOT NULL DEFAULT 'main',
            `transport`       VARCHAR(16) NOT NULL DEFAULT 'api',
            `write_enabled`   TINYINT(1) NOT NULL DEFAULT 0,
            `credential_ref`  VARCHAR(191) NULL,
            `last_verified_at` DATETIME NULL,
            `status`          VARCHAR(32) NOT NULL DEFAULT 'connected',
            `deleted`         TINYINT(1) NOT NULL DEFAULT 0,
            `created_by`      CHAR(36) NULL,
            `updated_by`      CHAR(36) NULL,
            `created_at`      DATETIME NOT NULL,
            `updated_at`      DATETIME NOT NULL,
            `module_key_active` VARCHAR(191) GENERATED ALWAYS AS (IF(`deleted`=0, `module_key`, NULL)) VIRTUAL,
            PRIMARY KEY (`git_repo_connection_id`),
            UNIQUE KEY `uq_git_conn_org_module_active` (`org_id`, `module_key_active`),
            KEY `ix_git_conn_org` (`org_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // --- a least-privilege credential, encrypted at rest ------------------------------
        "CREATE TABLE `git_credential` (
            `git_credential_id` CHAR(36) NOT NULL,
            `org_id`          CHAR(36) NOT NULL,
            `module_key`      VARCHAR(191) NOT NULL,
            `type`            VARCHAR(16) NOT NULL,
            `ciphertext`      LONGTEXT NOT NULL,
            `public_material` TEXT NULL,
            `fingerprint`     VARCHAR(191) NULL,
            `scopes`          VARCHAR(255) NULL,
            `rotated_at`      DATETIME NULL,
            `revoked_at`      DATETIME NULL,
            `status`          VARCHAR(32) NOT NULL DEFAULT 'active',
            `deleted`         TINYINT(1) NOT NULL DEFAULT 0,
            `created_by`      CHAR(36) NULL,
            `updated_by`      CHAR(36) NULL,
            `created_at`      DATETIME NOT NULL,
            `updated_at`      DATETIME NOT NULL,
            PRIMARY KEY (`git_credential_id`),
            KEY `ix_git_cred_org_module` (`org_id`, `module_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // --- append-only audit log --------------------------------------------------------
        "CREATE TABLE `git_operation_log` (
            `git_operation_log_id` CHAR(36) NOT NULL,
            `org_id`          CHAR(36) NOT NULL,
            `module_key`      VARCHAR(191) NOT NULL,
            `actor_type`      VARCHAR(16) NOT NULL DEFAULT 'user',
            `actor_id`        CHAR(36) NULL,
            `operation`       VARCHAR(64) NOT NULL,
            `branch`          VARCHAR(191) NULL,
            `ref_before`      VARCHAR(64) NULL,
            `ref_after`       VARCHAR(64) NULL,
            `result`          VARCHAR(32) NOT NULL,
            `error_code`      VARCHAR(64) NULL,
            `created_at`      DATETIME NOT NULL,
            PRIMARY KEY (`git_operation_log_id`),
            KEY `ix_git_log_org_module` (`org_id`, `module_key`, `created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // --- a submitted/approved release -------------------------------------------------
        "CREATE TABLE `module_release` (
            `module_release_id` CHAR(36) NOT NULL,
            `org_id`          CHAR(36) NOT NULL,
            `module_key`      VARCHAR(191) NOT NULL,
            `version`         VARCHAR(64) NOT NULL,
            `git_tag`         VARCHAR(191) NOT NULL,
            `commit_sha`      VARCHAR(64) NOT NULL,
            `qa_report_ref`   VARCHAR(191) NULL,
            `submitted_at`    DATETIME NULL,
            `decided_at`      DATETIME NULL,
            `status`          VARCHAR(32) NOT NULL DEFAULT 'submitted',
            `deleted`         TINYINT(1) NOT NULL DEFAULT 0,
            `created_by`      CHAR(36) NULL,
            `updated_by`      CHAR(36) NULL,
            `created_at`      DATETIME NOT NULL,
            `updated_at`      DATETIME NOT NULL,
            PRIMARY KEY (`module_release_id`),
            KEY `ix_module_release_org_module` (`org_id`, `module_key`, `status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // --- a consumer install's version pin/policy -------------------------------------
        "CREATE TABLE `module_install_pin` (
            `module_install_pin_id` CHAR(36) NOT NULL,
            `org_id`          CHAR(36) NOT NULL,
            `module_key`      VARCHAR(191) NOT NULL,
            `installed_version` VARCHAR(64) NULL,
            `pinned_version`  VARCHAR(64) NULL,
            `channel`         VARCHAR(32) NOT NULL DEFAULT 'notify',
            `previous_version` VARCHAR(64) NULL,
            `status`          VARCHAR(32) NOT NULL DEFAULT 'active',
            `deleted`         TINYINT(1) NOT NULL DEFAULT 0,
            `created_by`      CHAR(36) NULL,
            `updated_by`      CHAR(36) NULL,
            `created_at`      DATETIME NOT NULL,
            `updated_at`      DATETIME NOT NULL,
            `module_key_active` VARCHAR(191) GENERATED ALWAYS AS (IF(`deleted`=0, `module_key`, NULL)) VIRTUAL,
            PRIMARY KEY (`module_install_pin_id`),
            UNIQUE KEY `uq_module_pin_org_module_active` (`org_id`, `module_key_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ],
    'down' => [
        "DROP TABLE IF EXISTS `module_install_pin`",
        "DROP TABLE IF EXISTS `module_release`",
        "DROP TABLE IF EXISTS `git_operation_log`",
        "DROP TABLE IF EXISTS `git_credential`",
        "DROP TABLE IF EXISTS `git_repo_connection`",
    ],
];
