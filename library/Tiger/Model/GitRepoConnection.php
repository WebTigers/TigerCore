<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Model_GitRepoConnection — a module's connection to its GitHub repo (TIGER-334, migration 0054).
 *
 * One row per `(org_id, module_key)` while active; the unique is active-only, so disconnecting frees
 * the pair for a clean reconnect. Holds no secret — only a `credential_ref`. Named finders only; a
 * service never builds a `$db` predicate.
 *
 * @api
 * @since 1.38.0
 */
class Tiger_Model_GitRepoConnection extends Tiger_Model_Table
{
    protected $_name    = 'git_repo_connection';
    protected $_primary = 'git_repo_connection_id';

    /** The active connection for a module in an org, or null. */
    public function forModule($orgId, $moduleKey)
    {
        return $this->fetchRow(
            $this->activeSelect()
                ->where('org_id = ?', (string) $orgId)
                ->where('module_key = ?', (string) $moduleKey)
        );
    }

    /** Every active connection in an org (for the instance-wide Updates panel). */
    public function forOrg($orgId)
    {
        return $this->fetchAll(
            $this->activeSelect()->where('org_id = ?', (string) $orgId)->order('module_key ASC')
        );
    }
}
