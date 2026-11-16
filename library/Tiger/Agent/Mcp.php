<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Agent_Mcp — the OUTBOUND connections registry: external MCP servers whose tools the in-app agent may
 * call (the mirror of the inbound `/mcp` server; TIGERMCP.md §9). A connection is {id, label, url, token,
 * enabled}; the token is a secret so it's stored ENCRYPTED (Tiger_Crypto), like the agent's own BYO key.
 * Connections live in the lazy `option` tier (config-discipline — no schema, on-demand) AT THE ORG: they hold
 * credentials, so like the agent's own key they are org-level only (TIGER-511) — an org sees and uses only its
 * own list, and there is no install-wide one (migration 0068 moved it to the install org). The aggregated,
 * namespaced tool list is cached per org (a remote tools/list per connection is a network call, not a per-turn cost).
 *
 * The agent turn advertises these tools (Tiger_Agent_Tools) and dispatches a call to them via the Forge's
 * `mcp` action → Tiger_Agent_Mcp_Client → the remote server. MCP adds reach; a call is a WRITE (approval-
 * gated like any other agent write) — an external server is trusted at the operator's discretion.
 *
 * @api
 * @see Tiger_Agent_Mcp_Client  the remote HTTP JSON-RPC caller
 */
class Tiger_Agent_Mcp
{
    /** Option key (org scope) — a JSON array of connections: [{id, label, url, token_enc, enabled}]. */
    const OPT_CONNECTIONS = 'agent.mcp.connections';

    /** Aggregated tool-list cache TTL (seconds). */
    const CACHE_TTL = 3600;

    /**
     * May a role use the connected MCP tools? Gated to the roles that manage the connections (admin+) — the
     * SAME ACL as the outbound admin service, so tool advertisement + execution can't outrun who may connect.
     *
     * @param  string $role
     * @return bool
     */
    public static function allowedForRole($role)
    {
        try {
            $acl = Zend_Registry::isRegistered('Zend_Acl') ? Zend_Registry::get('Zend_Acl') : null;
            return $acl && $acl->has('Agent_Service_Mcp') && $acl->isAllowed($role, 'Agent_Service_Mcp', 'save');
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * The org the registry reads/writes when none is named: the signed-in org ('' with none → no
     * connections). Connections hold credentials, so — like the agent's own key (TIGER-511) — they are
     * org-level only: an org sees, uses and edits only its own, never another org's or an install-wide list.
     *
     * @param  string|null $orgId an explicit org, or null for the signed-in org
     * @return string
     */
    protected static function _org($orgId = null)
    {
        if ($orgId !== null) { return (string) $orgId; }
        $org = Tiger_Model_Table::org();
        return $org === null ? '' : (string) $org;
    }

    /**
     * Every configured connection of an org (raw; the token stays encrypted).
     *
     * @param  string|null $orgId the org (null = the signed-in org)
     * @return array<int,array>
     */
    public static function all($orgId = null)
    {
        $org = self::_org($orgId);
        if ($org === '') { return []; }
        try {
            $c = (new Tiger_Model_Option())->getJson(Tiger_Model_Option::SCOPE_ORG, $org, self::OPT_CONNECTIONS, []);
            return is_array($c) ? array_values($c) : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /**
     * Connections for a management UI — token replaced by a `has_token` flag (never leaks the secret).
     *
     * @param  string|null $orgId the org (null = the signed-in org)
     * @return array<int,array>
     */
    public static function forAdmin($orgId = null)
    {
        return array_map(static function ($c) {
            return [
                'id'        => (string) ($c['id'] ?? ''),
                'label'     => (string) ($c['label'] ?? ''),
                'url'       => (string) ($c['url'] ?? ''),
                'enabled'   => !empty($c['enabled']),
                'has_token' => !empty($c['token_enc']),
            ];
        }, self::all($orgId));
    }

    /**
     * An org's enabled connections with their token DECRYPTED (for the client).
     *
     * @param  string|null $orgId the org (null = the signed-in org)
     * @return array<int,array{id:string,label:string,url:string,token:string}>
     */
    public static function enabled($orgId = null)
    {
        $out = [];
        foreach (self::all($orgId) as $c) {
            if (empty($c['enabled']) || empty($c['url'])) { continue; }
            $out[] = [
                'id'    => (string) ($c['id'] ?? ''),
                'label' => (string) ($c['label'] ?? ($c['id'] ?? '')),
                'url'   => (string) $c['url'],
                'token' => self::_decrypt((string) ($c['token_enc'] ?? '')),
            ];
        }
        return $out;
    }

    /**
     * One of an org's enabled connections (token decrypted) by id, or null — never another org's.
     *
     * @param  string      $id    the connection id
     * @param  string|null $orgId the org (null = the signed-in org)
     * @return array|null
     */
    public static function connection($id, $orgId = null)
    {
        foreach (self::enabled($orgId) as $c) {
            if ($c['id'] === (string) $id) { return $c; }
        }
        return null;
    }

    /**
     * Create or update a connection. A blank token on an update KEEPS the existing one (the secret never
     * round-trips to the browser). Returns the id.
     *
     * @param  array       $conn  {id?, label, url, token?, enabled?}
     * @param  string|null $orgId the org (null = the signed-in org)
     * @return string the id
     * @throws RuntimeException with no org to save it for (there is no install-wide list)
     */
    public static function save(array $conn, $orgId = null)
    {
        $org = self::_org($orgId);
        if ($org === '') {
            throw new RuntimeException('An MCP connection belongs to an org — there is no org to save it for.');
        }
        $list = self::all($org);
        $id   = (string) ($conn['id'] ?? '');
        if ($id === '') { $id = 'mcp-' . bin2hex(random_bytes(4)); }

        $token = (string) ($conn['token'] ?? '');
        $found = false;
        foreach ($list as &$c) {
            if ((string) ($c['id'] ?? '') !== $id) { continue; }
            $found        = true;
            $c['label']   = (string) ($conn['label'] ?? $c['label'] ?? '');
            $c['url']     = (string) ($conn['url'] ?? $c['url'] ?? '');
            $c['enabled'] = array_key_exists('enabled', $conn) ? !empty($conn['enabled']) : !empty($c['enabled']);
            if ($token !== '') { $c['token_enc'] = self::_encrypt($token); }   // blank → keep the existing token
        }
        unset($c);
        if (!$found) {
            $list[] = [
                'id'        => $id,
                'label'     => (string) ($conn['label'] ?? ''),
                'url'       => (string) ($conn['url'] ?? ''),
                'enabled'   => !empty($conn['enabled']),
                'token_enc' => $token !== '' ? self::_encrypt($token) : '',
            ];
        }
        self::_write($org, $list);
        return $id;
    }

    /**
     * Remove one of an org's connections (another org's id is simply not in the list — a no-op).
     *
     * @param  string      $id    the connection id
     * @param  string|null $orgId the org (null = the signed-in org)
     * @return void
     */
    public static function remove($id, $orgId = null)
    {
        $org = self::_org($orgId);
        if ($org === '') { return; }
        $list = array_values(array_filter(self::all($org), static fn($c) => (string) ($c['id'] ?? '') !== (string) $id));
        self::_write($org, $list);
    }

    /**
     * The aggregated, namespaced tool list across an org's ENABLED connections — what the agent advertises
     * + calls. Cached per org (a remote tools/list is a network round-trip); `save`/`remove` clear it.
     *
     * @param  bool        $refresh re-scan the connections now
     * @param  string|null $orgId   the org (null = the signed-in org)
     * @return array<int,array{connection:string,connLabel:string,name:string,description:string,inputSchema:array}>
     */
    public static function tools($refresh = false, $orgId = null)
    {
        $org = self::_org($orgId);
        if ($org === '') { return []; }
        $file   = self::_cacheFile($org);
        $cached = ($file !== '' && is_file($file)) ? json_decode((string) @file_get_contents($file), true) : null;
        if (!$refresh && is_array($cached) && isset($cached['at']) && (time() - (int) $cached['at']) < self::CACHE_TTL) {
            return (array) ($cached['tools'] ?? []);
        }

        $out = [];
        foreach (self::enabled($org) as $conn) {
            foreach (Tiger_Agent_Mcp_Client::listTools($conn) as $t) {
                $out[] = [
                    'connection'  => $conn['id'],
                    'connLabel'   => $conn['label'],
                    'name'        => $t['name'],
                    'description' => $t['description'],
                    'inputSchema' => $t['inputSchema'],
                ];
            }
        }
        self::_writeCache($file, $out);
        return $out;
    }

    // ----- internals ---------------------------------------------------------

    protected static function _write($org, array $list)
    {
        (new Tiger_Model_Option())->setJson(Tiger_Model_Option::SCOPE_ORG, (string) $org, self::OPT_CONNECTIONS, array_values($list));
        @unlink(self::_cacheFile($org));   // tools may have changed
    }

    protected static function _encrypt($plain)
    {
        try { return $plain === '' ? '' : Tiger_Crypto::encrypt($plain); }
        catch (Throwable $e) { return ''; }
    }

    protected static function _decrypt($blob)
    {
        try { return $blob === '' ? '' : (string) Tiger_Crypto::decrypt($blob); }
        catch (Throwable $e) { return ''; }
    }

    /** The per-org tool-list cache file (one org's tools never answer for another). */
    protected static function _cacheFile($org)
    {
        return defined('APPLICATION_ROOT')
            ? APPLICATION_ROOT . '/var/cache/agent-mcp/tools-' . md5((string) $org) . '.json'
            : '';
    }

    protected static function _writeCache($file, array $tools)
    {
        if ($file === '') { return; }
        if (!is_dir(dirname($file))) { @mkdir(dirname($file), 0775, true); }
        @file_put_contents($file, json_encode(['at' => time(), 'tools' => $tools]), LOCK_EX);
    }
}
