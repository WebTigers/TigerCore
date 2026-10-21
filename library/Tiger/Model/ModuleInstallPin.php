<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Model_ModuleInstallPin — a consumer install's version policy + rollback anchor (TIGER-334, 0054).
 *
 * One active row per `(org_id, module_key)` (active-only unique). `channel` is the update policy
 * (auto|notify|pinned); `pinned_version` holds the pin after a rollback so auto-update does not
 * immediately reapply a bad release; `previous_version` is what a rollback restores to. Named finders
 * only.
 *
 * @api
 * @since 1.38.0
 */
class Tiger_Model_ModuleInstallPin extends Tiger_Model_Table
{
    protected $_name    = 'module_install_pin';
    protected $_primary = 'module_install_pin_id';

    /** The active pin/policy row for a module, or null. */
    public function forModule($orgId, $moduleKey)
    {
        return $this->fetchRow(
            $this->activeSelect()
                ->where('org_id = ?', (string) $orgId)
                ->where('module_key = ?', (string) $moduleKey)
        );
    }

    /** Every active pin in an org (the Updates panel). */
    public function forOrg($orgId)
    {
        return $this->fetchAll(
            $this->activeSelect()->where('org_id = ?', (string) $orgId)->order('module_key ASC')
        );
    }
}
