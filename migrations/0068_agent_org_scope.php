<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Migration 0068 — AI agent keys become org-level only (TIGER-511 / TIGER-513).
 *
 * The agent used to resolve an org's own agent, else a PLATFORM agent (`agent.org_id = ''`), else the
 * install-wide `tiger.agent.*` config — so an org that never connected an AI account silently spent the
 * install's key. Now an org resolves ONLY its own agent (Tiger_Agent::forOrg — no fallback). To keep a
 * single-tenant install working, everything that was install-wide moves to the INSTALL org
 * (`Tiger_Model_Org::installOrgId()` — the configured `tiger.site.org_id`, else the founding org):
 *
 *   1. `agent` registry rows with `org_id = ''` → the install org. If the install org already has a
 *      default agent, a moved default is demoted (one default per org); otherwise it stays the default.
 *   2. GLOBAL `config` rows for the agent keys (enabled / provider / model / api_key_enc / mode_max) →
 *      org-scope rows for the install org. An org row that already exists is NEVER overwritten (the
 *      org's own value wins); a soft-deleted one is revived with the moved value. The global row is
 *      then soft-deleted either way, so the merged config no longer carries an install-wide key.
 *   3. The GLOBAL outbound-MCP connections option (`agent.mcp.connections`, which holds encrypted
 *      tokens) → the install org's option, under the same never-overwrite rule.
 *
 * MULTI-SITE CAVEAT: install-wide agent data cannot be attributed to the site it was meant for (that
 * was never recorded), so on a multi-site install it ALL lands on the install org. Every other org
 * starts with no agent and must connect its own AI account; an operator who wants another org to
 * share the old key re-enters it in that org's AI Agent settings.
 *
 * DATA migration (a PHP callable — see Tiger_Db_Migrator). Idempotent: a re-run finds no live ''
 * agents and no live global agent rows. One-way: `down` is a no-op.
 */
return [
    'up' => [
        function ($db) {
            $configured = '';
            if (Zend_Registry::isRegistered('Zend_Config')) {
                $t = Zend_Registry::get('Zend_Config')->get('tiger');
                $s = $t ? $t->get('site') : null;
                $configured = ($s && $s->get('org_id')) ? (string) $s->get('org_id') : '';
            }
            $org = Tiger_Model_Org::installOrgId($configured);
            if ($org === '') {
                return;   // a pre-install database: no org to own anything yet
            }
            $now = gmdate('Y-m-d H:i:s');

            // ---- 1. platform agents → the install org ------------------------------------------
            $legacy = $db->fetchAll(
                $db->select()->from('agent', ['agent_id', 'is_default'])
                    ->where('org_id = ?', '')
                    ->where('deleted = ?', 0)
                    ->order(['is_default DESC', 'created_at ASC'])
            );
            if ($legacy) {
                $hasDefault = (bool) $db->fetchOne(
                    $db->select()->from('agent', ['agent_id'])
                        ->where('org_id = ?', $org)
                        ->where('is_default = ?', 1)
                        ->where('deleted = ?', 0)
                        ->limit(1)
                );
                foreach ($legacy as $row) {
                    $data = ['org_id' => $org, 'updated_at' => $now];
                    if ((int) $row['is_default'] === 1) {
                        if ($hasDefault) {
                            $data['is_default'] = 0;   // the install org keeps its own default
                        } else {
                            $hasDefault = true;        // the platform default becomes the install org's
                        }
                    }
                    $db->update('agent', $data, $db->quoteInto('agent_id = ?', (string) $row['agent_id']));
                }
            }

            // ---- 2 + 3. global rows of a scope-keyed table → the install org ---------------------
            // $table is `config` or `option` (identical shapes: <t>_id, scope, scope_id, <t>_key, <t>_value).
            $move = function ($table, $key) use ($db, $org, $now) {
                $id  = $table . '_id';
                $k   = $table . '_key';
                $v   = $table . '_value';
                $tbl = $db->quoteIdentifier($table);

                $global = $db->fetchRow(
                    'SELECT * FROM ' . $tbl . ' WHERE scope = ? AND scope_id = ? AND ' . $k . ' = ? AND deleted = 0',
                    ['global', '', $key]
                );
                if (!$global) {
                    return;
                }
                $mine = $db->fetchRow(
                    'SELECT * FROM ' . $tbl . ' WHERE scope = ? AND scope_id = ? AND ' . $k . ' = ?',
                    ['org', $org, $key]
                );
                if (!$mine) {
                    $db->insert($table, [
                        $id          => Tiger_Uuid::v7(),
                        'scope'      => 'org',
                        'scope_id'   => $org,
                        $k           => $key,
                        $v           => $global[$v],
                        'deleted'    => 0,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                } elseif ((int) $mine['deleted'] === 1) {
                    $db->update($table, [$v => $global[$v], 'deleted' => 0, 'updated_at' => $now],
                        $db->quoteInto($id . ' = ?', (string) $mine[$id]));
                }
                // A LIVE org row is left exactly as it is — the org's own value wins.

                $db->update($table, ['deleted' => 1, 'updated_at' => $now],
                    $db->quoteInto($id . ' = ?', (string) $global[$id]));
            };

            foreach ([
                'tiger.agent.enabled',
                'tiger.agent.provider',
                'tiger.agent.model',
                'tiger.agent.api_key_enc',
                'tiger.agent.mode_max',
            ] as $key) {
                $move('config', $key);
            }
            $move('option', 'agent.mcp.connections');
        },
    ],
    'down' => [],
];
