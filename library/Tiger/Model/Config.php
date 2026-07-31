<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Config — the runtime config override layer (see migration 0009).
 *
 * Read by Tiger_Application_Bootstrap::_initConfigs, which folds the rows onto the
 * ini config cascade (global first, then the current org). Values are dot-notation
 * keys (`tiger.skin`) mapped into the nested Zend_Config. This is also the per-org
 * theming resolver — an org row `tiger.skin` reskins that org.
 *
 * @api
 */
class Tiger_Model_Config extends Tiger_Model_Table
{
    protected $_name    = 'config';
    protected $_primary = 'config_id';

    const SCOPE_GLOBAL = 'global';
    const SCOPE_ORG    = 'org';
    const SCOPE_USER   = 'user';

    /**
     * Active config rows for a scope (+ optional scope id). Global uses scope_id ''.
     *
     * @param  string $scope   the scope (global/org/user)
     * @param  string $scopeId the scope id ('' for global)
     * @return Zend_Db_Table_Rowset_Abstract the matching config rows
     */
    public function getForScope($scope, $scopeId = '')
    {
        return $this->fetchAll(
            $this->activeSelect()
                ->where('scope = ?', $scope)
                ->where('scope_id = ?', (string) $scopeId)
        );
    }

    /**
     * Fetch a single config value, or null.
     *
     * @param  string $scope   the scope (global/org/user)
     * @param  string $scopeId the scope id ('' for global)
     * @param  string $key     the dot-notation config key
     * @return string|null the config value, or null when unset
     */
    public function get($scope, $scopeId, $key)
    {
        $row = $this->fetchRow(
            $this->activeSelect()
                ->where('scope = ?', $scope)
                ->where('scope_id = ?', (string) $scopeId)
                ->where('config_key = ?', $key)
        );
        return $row ? $row->config_value : null;
    }

    /**
     * Upsert a config value for a scope. Returns the config_id.
     *
     * @param  string $scope   the scope (global/org/user)
     * @param  string $scopeId the scope id ('' for global)
     * @param  string $key     the dot-notation config key
     * @param  string $value   the config value to store
     * @return string the config_id
     */
    public function set($scope, $scopeId, $key, $value)
    {
        // Match ANY existing row for this key — INCLUDING a soft-deleted one (plain select(), not
        // activeSelect()). forget() soft-deletes, but the DB unique index (scope, scope_id, config_key)
        // still holds that row, so a fresh insert would collide. A set() after a forget() must REVIVE the
        // row (clear `deleted`) rather than insert a duplicate.
        $existing = $this->fetchRow(
            $this->select()
                ->where('scope = ?', $scope)
                ->where('scope_id = ?', (string) $scopeId)
                ->where('config_key = ?', $key)
        );
        if ($existing) {
            $data = ['config_value' => $value];
            if ((int) $existing->deleted === 1) { $data['deleted'] = 0; }   // revive a forgotten key
            $this->update($data, $this->getAdapter()->quoteInto('config_id = ?', $existing->config_id));
            return $existing->config_id;
        }
        return $this->insert([
            'scope'        => $scope,
            'scope_id'     => (string) $scopeId,
            'config_key'   => $key,
            'config_value' => $value,
        ]);
    }

    /**
     * Remove a config value (soft-delete) for a scope. No-op if it doesn't exist. The row drops out of the
     * config cascade next request; a later set() of the same key revives it.
     *
     * @param  string $scope   the scope (global/org/user)
     * @param  string $scopeId the scope id ('' for global)
     * @param  string $key     the dot-notation config key
     * @return void
     */
    public function forget($scope, $scopeId, $key)
    {
        $row = $this->fetchRow(
            $this->activeSelect()
                ->where('scope = ?', $scope)
                ->where('scope_id = ?', (string) $scopeId)
                ->where('config_key = ?', $key)
        );
        if ($row) {
            $this->softDelete($this->getAdapter()->quoteInto('config_id = ?', $row->config_id));
        }
    }
}
