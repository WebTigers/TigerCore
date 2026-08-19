<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Mcp_Service_Settings — the /api behind the MCP Connect screen: turn the `/mcp` endpoint on/off. Thin +
 * admin-gated; the value lives in the `config` live-override tier (effective next request, no deploy).
 * Token minting reuses the core Tiger_Service_Token (module=tiger, service=token); this service owns only
 * the enable flag.
 *
 * @api
 */
class Mcp_Service_Settings extends Tiger_Service_Service
{
    /**
     * Enable or disable the MCP server (`tiger.mcp.enabled`).
     *
     * @param  array $params {enabled: bool-ish}
     * @return void
     */
    public function save(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $on = !empty($params['enabled']) && $params['enabled'] !== '0' && $params['enabled'] !== 'false';
        try {
            (new Tiger_Model_Config())->set(Tiger_Model_Config::SCOPE_GLOBAL, '', Tiger_Mcp::CONFIG_ENABLED, $on ? '1' : '0');
            $this->_success(['enabled' => $on], $on ? 'mcp.settings.enabled' : 'mcp.settings.disabled');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Mint an MCP access token WITH a scope (module allow-list + read-only + org-scoping). The plaintext is
     * shown once. Least-privilege: an empty/omitted module set becomes the curated starter set.
     *
     * @param  array $params {modules?: string[]|csv, read_only?: bool, org_scoped?: bool}
     * @return void
     */
    public function mintToken(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $identity = Zend_Auth::getInstance()->getIdentity();
        $userId   = $identity->user_id ?? null;
        if ($userId === null) { $this->_error('core.api.error.login_required'); return; }

        try {
            $r = (new Tiger_Model_UserCredential())->createToken($userId);
            Tiger_Mcp_Token::saveConfig($r['credential_id'], [
                'modules'    => $this->_modules($params),
                'read_only'  => $this->_flag($params, 'read_only'),
                'org_scoped' => $this->_flag($params, 'org_scoped'),
                'role'       => (string) ($identity->role ?? ''),
                'org_id'     => (string) ($identity->org_id ?? ''),
            ]);
            $this->_success(['token' => $r['token'], 'prefix' => $r['prefix'], 'credential_id' => $r['credential_id']], 'mcp.token.created');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /** List the caller's MCP tokens with their scope (prefix + timestamps + modules/read_only/org_scoped). */
    public function tokens(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $userId = Zend_Auth::getInstance()->getIdentity()->user_id ?? null;
        if ($userId === null) { $this->_error('core.api.error.login_required'); return; }

        $rows = (new Tiger_Model_UserCredential())->tokensFor($userId);
        foreach ($rows as &$t) {
            $c = Tiger_Mcp_Token::config((string) $t['credential_id']);
            $t['modules']    = $c['modules'];
            $t['read_only']  = $c['read_only'];
            $t['org_scoped'] = $c['org_scoped'];
        }
        unset($t);
        $this->_success(['tokens' => $rows]);
    }

    /** Revoke one of the caller's tokens + drop its MCP policy. */
    public function revokeToken(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $userId = Zend_Auth::getInstance()->getIdentity()->user_id ?? null;
        $id     = (string) ($params['credential_id'] ?? '');
        if ($userId === null || $id === '') { $this->_error('core.api.error.general'); return; }
        (new Tiger_Model_UserCredential())->revokeToken($userId, $id);
        Tiger_Mcp_Token::clearConfig($id);
        $this->_success([], 'mcp.token.revoked');
    }

    /** Sanitize the requested module scope to slugs; default to the curated starter set. */
    protected function _modules(array $params): array
    {
        $req = $params['modules'] ?? null;
        if (is_string($req)) { $req = array_filter(explode(',', $req)); }
        if (!is_array($req) || !$req) { return Tiger_Mcp_Token::DEFAULT_MODULES; }
        $out = array_filter(array_map(static fn($m) => preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $m)), $req));
        return $out ? array_values(array_unique($out)) : Tiger_Mcp_Token::DEFAULT_MODULES;
    }

    /** A truthy checkbox/flag param. */
    protected function _flag(array $params, string $key): bool
    {
        return !empty($params[$key]) && $params[$key] !== '0' && $params[$key] !== 'false';
    }
}
