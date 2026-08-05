<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Convert JSON columns to LONGTEXT — the database stores TEXT; validating JSON is the app's job.
 *
 * MariaDB's JSON type is really LONGTEXT + an implicit `CHECK(json_valid(col))`, and that check REJECTS
 * JSON nested ≥ 32 levels — which PHP's json_encode produces without complaint. It broke the CMS GrapesJS
 * visual builder, whose deep project blob is stored in `page.meta` (SQLSTATE 23000 / err 4025). LONGTEXT
 * holds any depth, and every one of these values is already json_encode/decode'd in PHP.
 *
 * Two steps: MODIFY the four known core JSON columns to LONGTEXT, then SWEEP the schema for any remaining
 * `json_valid` CHECK constraint (a MODIFY can leave the implicit check behind, and a module table may carry
 * its own) and drop it. Idempotent — the sweep drops only what exists.
 *
 * ONE-WAY by design: no `down` back to JSON. Re-adding a json_valid CHECK would re-introduce the depth bug,
 * so a rollback here would be actively harmful. New tables must use LONGTEXT, never JSON (see AGENTS.md).
 */
return [
    'up' => [
        'ALTER TABLE `page`         MODIFY `meta`      LONGTEXT DEFAULT NULL',
        'ALTER TABLE `page_version` MODIFY `meta`      LONGTEXT DEFAULT NULL',
        'ALTER TABLE `media`        MODIFY `variants`  LONGTEXT DEFAULT NULL',
        'ALTER TABLE `media`        MODIFY `scan_meta` LONGTEXT DEFAULT NULL',

        // Drop EVERY json_valid CHECK still present in this schema (core leftovers + any module table).
        function ($db) {
            $rows = $db->fetchAll(
                "SELECT TABLE_NAME AS t, CONSTRAINT_NAME AS c
                   FROM information_schema.CHECK_CONSTRAINTS
                  WHERE CONSTRAINT_SCHEMA = DATABASE()
                    AND LOWER(CHECK_CLAUSE) LIKE '%json_valid%'"
            );
            foreach ($rows as $r) {
                $db->query('ALTER TABLE `' . $r['t'] . '` DROP CONSTRAINT `' . $r['c'] . '`');
            }
        },
    ],
    'down' => [
        // Intentionally empty — never re-validate JSON in the DB.
    ],
];
