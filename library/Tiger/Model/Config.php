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
 * Per-site settings (TIGER-511) are written at the caller's org (settingScope()) and read for ONE org,
 * falling back to the install-wide global row then the ini file: currentValue() on an admin screen,
 * siteValue() on a public path — never another org's value.
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

    /** @var array<string,array<string,string>> per-request tier cache: "<scope>|<scopeId>" => [config_key => value] */
    private static $_tiers = [];

    /** @var array|null the ini (file) tier — the bootstrap's options, captured by _initConfigs; null outside a boot */
    private static $_iniBase = null;

    // =====================================================================================
    //  Org-scoped setting resolution (TIGER-511)
    // =====================================================================================

    /**
     * The org a settings screen acts for: the signed-in caller's org, else the current site's org
     * (Tiger_Model_Org::siteOrgId — the host's tenant, else the install org). This is the same org the
     * config tier folds for that request, so an admin screen that reads and writes here shows exactly
     * what it saves.
     *
     * @api
     * @return string the org id, or '' only when no org exists yet (a pre-install install)
     */
    public static function currentOrgId()
    {
        $identity = class_exists('Zend_Auth') ? Zend_Auth::getInstance()->getIdentity() : null;
        if ($identity && !empty($identity->org_id)) {
            return (string) $identity->org_id;
        }
        return class_exists('Tiger_Model_Org') ? (string) Tiger_Model_Org::siteOrgId() : '';
    }

    /**
     * The [scope, scopeId] a per-org setting is WRITTEN at: the given org (null = currentOrgId()). Only
     * an install with no org at all — nothing else exists to bleed into — falls back to the global tier.
     *
     * @api
     * @param  string|null $orgId the org to write for; null = the caller's current org
     * @return array{0:string,1:string} [scope, scopeId]
     */
    public static function settingScope($orgId = null)
    {
        $orgId = ($orgId === null) ? self::currentOrgId() : (string) $orgId;
        return ($orgId !== '') ? [self::SCOPE_ORG, $orgId] : [self::SCOPE_GLOBAL, ''];
    }

    /**
     * Resolve a setting for ONE org: that org's row → the install-wide (global) row → the ini file tier
     * → `$default`. Never another org's value, and never a user-tier row — unlike the merged
     * `Zend_Config`, which folds the SIGNED-IN visitor's org and user tiers on top.
     *
     * Rows are read once per request per tier (two small queries) and cached; set()/forget() clear the
     * cache. Fail-soft: with no DB (CLI tools, unit tests) only the ini tier answers.
     *
     * @api
     * @param  string      $orgId   the org whose value to read ('' = install-wide only)
     * @param  string      $key     the dot-notation config key (e.g. `tiger.site.name`)
     * @param  mixed       $default returned when no tier sets the key
     * @return mixed the stored string value, the ini scalar, or $default
     */
    public static function orgValue($orgId, $key, $default = null)
    {
        $key   = (string) $key;
        $orgId = (string) $orgId;
        if ($orgId !== '') {
            $org = self::_tier(self::SCOPE_ORG, $orgId);
            if (array_key_exists($key, $org)) { return $org[$key]; }
        }
        $global = self::_tier(self::SCOPE_GLOBAL, '');
        if (array_key_exists($key, $global)) { return $global[$key]; }

        $ini = self::_iniValue($key);
        return ($ini !== null) ? $ini : $default;
    }

    /**
     * Read ONE org's own row for a setting — no global row, no ini, no other org. For a value that must
     * never be inherited from the install (an org's AI key, TIGER-511): absent means "this org has none".
     *
     * @api
     * @since 1.60.0
     * @param  string $orgId   the org whose own row to read ('' always answers $default)
     * @param  string $key     the dot-notation config key
     * @param  mixed  $default returned when the org has no row for the key
     * @return mixed the stored string value, or $default
     */
    public static function orgOwnValue($orgId, $key, $default = null)
    {
        $orgId = (string) $orgId;
        if ($orgId === '') { return $default; }
        $org = self::_tier(self::SCOPE_ORG, $orgId);
        return array_key_exists((string) $key, $org) ? $org[(string) $key] : $default;
    }

    /**
     * Resolve a SITE-FACING setting — what the public site being served shows every visitor: the site
     * org's row (Tiger_Model_Org::siteOrgId) → global → ini → `$default`. Use this on public paths and
     * per-request plugins (the analytics tag, the favicon, the reCAPTCHA widget, head tags): a visitor
     * signed in to ANOTHER org still sees this site's value, never their own org's.
     *
     * @api
     * @param  string $key     the dot-notation config key
     * @param  mixed  $default returned when no tier sets the key
     * @return mixed
     */
    public static function siteValue($key, $default = null)
    {
        $site = class_exists('Tiger_Model_Org') ? (string) Tiger_Model_Org::siteOrgId() : '';
        return self::orgValue($site, $key, $default);
    }

    /**
     * Resolve a setting for the caller's current org (currentOrgId) — what an admin settings screen shows
     * and, via settingScope(), saves.
     *
     * @api
     * @param  string $key     the dot-notation config key
     * @param  mixed  $default returned when no tier sets the key
     * @return mixed
     */
    public static function currentValue($key, $default = null)
    {
        return self::orgValue(self::currentOrgId(), $key, $default);
    }

    /**
     * Record the ini (file) tier — the bootstrap's merged .ini options, before any DB tier is folded.
     * Called by Tiger_Application_Bootstrap::_initConfigs. Null clears it; with none recorded (no boot:
     * CLI tools, tests) the registered Zend_Config stands in, since nothing has folded DB tiers into it.
     *
     * @param  array|null $options the bootstrap options
     * @return void
     */
    public static function setIniBase(?array $options)
    {
        self::$_iniBase = $options;
    }

    /**
     * Drop the per-request tier cache (set()/forget() do this; tests call it between cases).
     *
     * @return void
     */
    public static function resetCache()
    {
        self::$_tiers = [];
    }

    /** One tier's active rows as [config_key => value], read once and cached. Fail-soft: [] on any error. */
    private static function _tier($scope, $scopeId)
    {
        $k = $scope . '|' . $scopeId;
        if (!array_key_exists($k, self::$_tiers)) {
            $map = [];
            try {
                foreach ((new self())->getForScope($scope, $scopeId) as $row) {
                    $map[(string) $row->config_key] = (string) $row->config_value;
                }
            } catch (Throwable $e) {
                $map = [];   // no DB / no config table — the ini tier answers
            }
            self::$_tiers[$k] = $map;
        }
        return self::$_tiers[$k];
    }

    /** A scalar from the ini tier (see setIniBase), or null when unset / a section. */
    private static function _iniValue($key)
    {
        $node = self::$_iniBase;
        if ($node === null) {
            if (!Zend_Registry::isRegistered('Zend_Config')) { return null; }
            $node = Zend_Registry::get('Zend_Config')->toArray();
        }
        foreach (explode('.', (string) $key) as $seg) {
            if (!is_array($node) || !array_key_exists($seg, $node)) { return null; }
            $node = $node[$seg];
        }
        return is_scalar($node) ? $node : null;
    }

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
        self::resetCache();
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
        self::resetCache();
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
