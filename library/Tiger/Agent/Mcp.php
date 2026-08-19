<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Agent_Mcp — the OUTBOUND connections registry: external MCP servers whose tools the in-app agent may
 * call (the mirror of the inbound `/mcp` server; TIGERMCP.md §9). A connection is {id, label, url, token,
 * enabled}; the token is a secret so it's stored ENCRYPTED (Tiger_Crypto), like the agent's own BYO key.
 * Connections live in the lazy `option` tier (config-discipline — no schema, on-demand), and the aggregated,
 * namespaced tool list is cached (a remote tools/list per connection is a network call, not a per-turn cost).
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
    /** Option key (global) — a JSON array of connections: [{id, label, url, token_enc, enabled}]. */
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

    /** Every configured connection (raw; the token stays encrypted). */
    public static function all()
    {
        try {
            $c = (new Tiger_Model_Option())->getJson(Tiger_Model_Option::SCOPE_GLOBAL, '', self::OPT_CONNECTIONS, []);
            return is_array($c) ? array_values($c) : [];
        } catch (Throwable $e) {
            return [];
        }
    }

    /** Connections for a management UI — token replaced by a `has_token` flag (never leaks the secret). */
    public static function forAdmin()
    {
        return array_map(static function ($c) {
            return [
                'id'        => (string) ($c['id'] ?? ''),
                'label'     => (string) ($c['label'] ?? ''),
                'url'       => (string) ($c['url'] ?? ''),
                'enabled'   => !empty($c['enabled']),
                'has_token' => !empty($c['token_enc']),
            ];
        }, self::all());
    }

    /** The enabled connections with their token DECRYPTED (for the client). */
    public static function enabled()
    {
        $out = [];
        foreach (self::all() as $c) {
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

    /** One enabled connection (token decrypted) by id, or null. */
    public static function connection($id)
    {
        foreach (self::enabled() as $c) {
            if ($c['id'] === (string) $id) { return $c; }
        }
        return null;
    }

    /**
     * Create or update a connection. A blank token on an update KEEPS the existing one (the secret never
     * round-trips to the browser). Returns the id.
     *
     * @param  array $conn {id?, label, url, token?, enabled?}
     * @return string the id
     */
    public static function save(array $conn)
    {
        $list = self::all();
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
        self::_write($list);
        return $id;
    }

    /** Remove a connection. */
    public static function remove($id)
    {
        $list = array_values(array_filter(self::all(), static fn($c) => (string) ($c['id'] ?? '') !== (string) $id));
        self::_write($list);
    }

    /**
     * The aggregated, namespaced tool list across all ENABLED connections — what the agent advertises + calls.
     * Cached (a remote tools/list is a network round-trip); `save`/`remove` clear the cache.
     *
     * @param  bool $refresh re-scan the connections now
     * @return array<int,array{connection:string,connLabel:string,name:string,description:string,inputSchema:array}>
     */
    public static function tools($refresh = false)
    {
        $file   = self::_cacheFile();
        $cached = ($file !== '' && is_file($file)) ? json_decode((string) @file_get_contents($file), true) : null;
        if (!$refresh && is_array($cached) && isset($cached['at']) && (time() - (int) $cached['at']) < self::CACHE_TTL) {
            return (array) ($cached['tools'] ?? []);
        }

        $out = [];
        foreach (self::enabled() as $conn) {
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

    protected static function _write(array $list)
    {
        (new Tiger_Model_Option())->setJson(Tiger_Model_Option::SCOPE_GLOBAL, '', self::OPT_CONNECTIONS, array_values($list));
        @unlink(self::_cacheFile());   // tools may have changed
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

    protected static function _cacheFile()
    {
        return defined('APPLICATION_ROOT') ? APPLICATION_ROOT . '/var/cache/agent-mcp/tools.json' : '';
    }

    protected static function _writeCache($file, array $tools)
    {
        if ($file === '') { return; }
        if (!is_dir(dirname($file))) { @mkdir(dirname($file), 0775, true); }
        @file_put_contents($file, json_encode(['at' => time(), 'tools' => $tools]), LOCK_EX);
    }
}
