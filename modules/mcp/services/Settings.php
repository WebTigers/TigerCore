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
        if (!$this->_isAllowed('save')) { $this->_error('core.api.error.not_allowed'); return; }

        $on = !empty($params['enabled']) && $params['enabled'] !== '0' && $params['enabled'] !== 'false';
        try {
            (new Tiger_Model_Config())->set(Tiger_Model_Config::SCOPE_GLOBAL, '', Tiger_Mcp::CONFIG_ENABLED, $on ? '1' : '0');
            $this->_success(['enabled' => $on], $on ? 'mcp.settings.enabled' : 'mcp.settings.disabled');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * The scope offers the caller may mint a token against (TIGER-470) — the registry-derived,
     * ACL-filtered set that drives the mint UI (module checkboxes + role profiles), replacing the
     * hardcoded JS list.
     *
     * @param  array $params unused
     * @return void
     */
    public function scopes(array $params): void
    {
        if (!$this->_isAllowed('scopes')) { $this->_error('core.api.error.not_allowed'); return; }
        $role = (string) (Zend_Auth::getInstance()->getIdentity()->role ?? 'guest');
        $this->_success(['scopes' => Tiger_Mcp_Scope::offers($role)]);
    }

    /**
     * Mint an MCP access token against a chosen set of SCOPE OFFERS (TIGER-470). The chosen offers are
     * resolved + authorized by Tiger_Mcp_Scope: a module set grants those modules and keeps the caller's
     * role; a role PROFILE (e.g. `ops.observer`) is exclusive and sets the token's role + resource fence +
     * forced read-only. An EMPTY selection is REFUSED — "select nothing" never silently widens to a
     * default set (the old footgun). The plaintext is shown once.
     *
     * @param  array $params {scopes: string[]|csv of offer keys, read_only?: bool, org_scoped?: bool}
     * @return void
     */
    public function mintToken(array $params): void
    {
        if (!$this->_isAllowed('mintToken')) { $this->_error('core.api.error.not_allowed'); return; }
        $identity = Zend_Auth::getInstance()->getIdentity();
        $userId   = $identity->user_id ?? null;
        if ($userId === null) { $this->_error('core.api.error.login_required'); return; }
        $role = (string) ($identity->role ?? '');

        try {
            $scope = Tiger_Mcp_Scope::resolve($this->_scopeKeys($params), $role, $this->_flag($params, 'read_only'));
        } catch (InvalidArgumentException $e) {
            $this->_error($e->getMessage());   // a translation key (mcp.scope.error.*)
            return;
        }

        try {
            $r = (new Tiger_Model_UserCredential())->createToken($userId);
            Tiger_Mcp_Token::saveConfig($r['credential_id'], [
                'modules'    => $scope['modules'],
                'read_only'  => $scope['read_only'],
                'org_scoped' => $this->_flag($params, 'org_scoped'),
                'role'       => $scope['role'],   // '' = act as the owner's live role; else the profile role
                'org_id'     => (string) ($identity->org_id ?? ''),
                'resources'  => $scope['resources'],
                'profile'    => $scope['role'] !== '',   // a role profile → dispatch AS that role
            ]);
            $this->_success(['token' => $r['token'], 'prefix' => $r['prefix'], 'credential_id' => $r['credential_id']], 'mcp.token.created');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /** List the caller's MCP tokens with their scope (prefix + timestamps + modules/read_only/org_scoped). */
    public function tokens(array $params): void
    {
        if (!$this->_isAllowed('tokens')) { $this->_error('core.api.error.not_allowed'); return; }
        $userId = Zend_Auth::getInstance()->getIdentity()->user_id ?? null;
        if ($userId === null) { $this->_error('core.api.error.login_required'); return; }

        $rows = (new Tiger_Model_UserCredential())->tokensFor($userId);
        foreach ($rows as &$t) {
            $c = Tiger_Mcp_Token::config((string) $t['credential_id']);
            $t['modules']    = $c['modules'];
            $t['read_only']  = $c['read_only'];
            $t['org_scoped'] = $c['org_scoped'];
            $t['role']       = $c['role'];
            $t['resources']  = $c['resources'];
        }
        unset($t);
        $this->_success(['tokens' => $rows]);
    }

    /** Revoke one of the caller's tokens + drop its MCP policy. */
    public function revokeToken(array $params): void
    {
        if (!$this->_isAllowed('revokeToken')) { $this->_error('core.api.error.not_allowed'); return; }
        $userId = Zend_Auth::getInstance()->getIdentity()->user_id ?? null;
        $id     = (string) ($params['credential_id'] ?? '');
        if ($userId === null || $id === '') { $this->_error('core.api.error.general'); return; }
        // Ownership comes from revokeToken()'s own predicate (credential_id AND user_id AND type=PAT),
        // and its boolean result IS that answer. Ignoring it meant a caller could name a credential
        // they do not own: the credential correctly survived, but clearConfig() — which has no owner
        // predicate — still wiped that token's MCP policy, dropping it to the permissive defaults
        // (DEFAULT_MODULES, read_only=false, org_scoped=false). That silently WIDENS someone else's
        // live token, and the call reported success either way. (TIGER-66)
        if (!(new Tiger_Model_UserCredential())->revokeToken($userId, $id)) {
            $this->_error('core.api.error.not_allowed');
            return;   // nothing revoked → nothing cleared
        }
        Tiger_Mcp_Token::clearConfig($id);
        $this->_success([], 'mcp.token.revoked');
    }

    /** The chosen scope-offer keys from the request (csv or array), sanitized — no silent default. */
    protected function _scopeKeys(array $params): array
    {
        $req = $params['scopes'] ?? [];
        if (is_string($req)) { $req = explode(',', $req); }
        if (!is_array($req)) { return []; }
        return array_values(array_filter(array_map(static fn($k) => preg_replace('/[^a-z0-9._-]/', '', strtolower((string) $k)), $req)));
    }

    /** A truthy checkbox/flag param. */
    protected function _flag(array $params, string $key): bool
    {
        return !empty($params[$key]) && $params[$key] !== '0' && $params[$key] !== 'false';
    }
}
