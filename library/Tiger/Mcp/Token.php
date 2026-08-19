<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Mcp_Token — the per-token policy for MCP: scope (module allow-list + read-only), org-scoping, and a
 * soft rate limit. Kept in the lazy `option` tier keyed by the token's credential id (config-discipline — no
 * schema change; the token primitive stays the plain `Tiger_Service_Token` credential). See TIGERMCP.md §7-8.
 *
 * The scope is enforced in the MCP layer (Mcp_ServerController), NOT in core auth: `tools/list` is clipped to
 * the allow-list, `tools/call` refuses an out-of-scope tool or a write on a read-only token, and each call is
 * metered + audited. A token with no saved config gets the **curated starter set** (the mainstream verbs), so
 * a fresh MCP token is least-privilege by default.
 *
 * @api
 * @see Tiger_Mcp_Server  the JSON-RPC engine whose tools/list this clips
 */
class Tiger_Mcp_Token
{
    /** Option key prefix — `mcp.token.<credentialId>` => {modules[], read_only, org_scoped, role, org_id}. */
    const OPT_CONFIG = 'mcp.token';

    /** Option key prefix — `mcp.meter.<prefix>` => {window, count}. */
    const OPT_METER = 'mcp.meter';

    /** The curated starter set a fresh MCP token is scoped to (the mainstream, mostly-content verbs). */
    const DEFAULT_MODULES = ['cms', 'blog', 'media', 'search', 'docs'];

    /** Soft rate limit: at most CAP tool calls per WINDOW seconds, per token. */
    const RATE_CAP    = 600;
    const RATE_WINDOW = 3600;

    /**
     * The MCP policy for a token credential — the saved config, or the curated default if none.
     *
     * @param  string $credentialId
     * @return array{modules:array,read_only:bool,org_scoped:bool,role:string,org_id:string}
     */
    public static function config($credentialId)
    {
        $c = null;
        try {
            $c = (new Tiger_Model_Option())->getJson(
                Tiger_Model_Option::SCOPE_GLOBAL, '', self::OPT_CONFIG . '.' . $credentialId, null
            );
        } catch (Throwable $e) { /* option tier not ready → curated default */ }

        $mods = (is_array($c) && !empty($c['modules']) && is_array($c['modules']))
            ? array_values(array_map('strval', $c['modules']))
            : self::DEFAULT_MODULES;

        return [
            'modules'    => $mods,
            'read_only'  => (bool) (is_array($c) ? ($c['read_only'] ?? false) : false),
            'org_scoped' => (bool) (is_array($c) ? ($c['org_scoped'] ?? false) : false),
            'role'       => (string) (is_array($c) ? ($c['role'] ?? '') : ''),
            'org_id'     => (string) (is_array($c) ? ($c['org_id'] ?? '') : ''),
        ];
    }

    /** Save a token's MCP policy. */
    public static function saveConfig($credentialId, array $config)
    {
        (new Tiger_Model_Option())->setJson(Tiger_Model_Option::SCOPE_GLOBAL, '', self::OPT_CONFIG . '.' . $credentialId, [
            'modules'    => array_values(array_filter(array_map('strval', (array) ($config['modules'] ?? [])))),
            'read_only'  => !empty($config['read_only']),
            'org_scoped' => !empty($config['org_scoped']),
            'role'       => (string) ($config['role'] ?? ''),
            'org_id'     => (string) ($config['org_id'] ?? ''),
        ]);
    }

    /** Drop a token's policy (on revoke); best-effort. */
    public static function clearConfig($credentialId)
    {
        try {
            (new Tiger_Model_Option())->setJson(Tiger_Model_Option::SCOPE_GLOBAL, '', self::OPT_CONFIG . '.' . $credentialId, []);
        } catch (Throwable $e) { /* best-effort */ }
    }

    /** Is a module within a token's scope? */
    public static function allowsModule(array $config, $module)
    {
        return in_array((string) $module, (array) ($config['modules'] ?? []), true);
    }

    /**
     * Why the token's POLICY denies a tool call (scope + read-only), or null if allowed. Pure — the
     * enforcement decision, separate from metering (soft rate limit) + the ACL (which still gates every
     * dispatch regardless). A write is any method that isn't a known read verb (fail-closed).
     *
     * @param  array  $config the token config
     * @param  string $module
     * @param  string $method
     * @return string|null  'out_of_scope' | 'read_only' | null(allowed)
     */
    public static function denyReason(array $config, $module, $method)
    {
        if (!self::allowsModule($config, $module)) {
            return 'out_of_scope';
        }
        $isWrite = !in_array(strtolower((string) $method), Tiger_Agent_Forge::READ_VERBS, true);
        if (!empty($config['read_only']) && $isWrite) {
            return 'read_only';
        }
        return null;
    }

    /**
     * Soft per-token rate limit — returns true if the call is within CAP over the rolling WINDOW (and counts
     * it). Best-effort (option-tier read-modify-write, no lock): never hard-fails a call on a metering error.
     *
     * @param  string $prefix the token's 12-hex lookup prefix
     * @return bool  true = allowed
     */
    public static function meter($prefix)
    {
        $prefix = preg_replace('/[^a-f0-9]/', '', (string) $prefix);
        if ($prefix === '') { return true; }
        try {
            $opt = new Tiger_Model_Option();
            $key = self::OPT_METER . '.' . $prefix;
            $m   = $opt->getJson(Tiger_Model_Option::SCOPE_GLOBAL, '', $key, null);
            $now = time();
            if (!is_array($m) || ($now - (int) ($m['window'] ?? 0)) > self::RATE_WINDOW) {
                $m = ['window' => $now, 'count' => 0];
            }
            if ((int) $m['count'] >= self::RATE_CAP) { return false; }
            $m['count'] = (int) $m['count'] + 1;
            $opt->setJson(Tiger_Model_Option::SCOPE_GLOBAL, '', $key, $m);
            return true;
        } catch (Throwable $e) {
            return true;
        }
    }
}
