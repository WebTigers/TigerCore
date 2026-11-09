<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Mcp_Scope — the MCP token SCOPE registry: the set of "scope offers" the mint screen draws
 * from, and the resolver that turns a chosen set into a concrete token policy.
 *
 * The problem it solves (TIGER-470): the mint UI used to offer a HARDCODED module list and minted
 * every token with the operator's OWN role, so a least-privilege, role-scoped token (e.g. the
 * TigerOps `observer`) was unreachable from the screen. This registry makes the offerable set
 * DERIVED and lets any module CONTRIBUTE its own scope — a named role + an optional resource/method
 * surface — WITHOUT touching the core `mcp` module, mirroring every other additive seam in Tiger
 * (Tiger_Admin_Settings::register, Tiger_Comment::registerSubject, Tiger_Routing_Overrides, the
 * Tiger_Code_Modules file-discovery).
 *
 * Two offer shapes, unified:
 *   - a MODULE offer — grants one module to the token's allow-list; the token keeps the caller's role
 *     (the base cms/blog/media/… set are these).
 *   - a PROFILE offer — a complete least-privilege identity: a named ACL `role` the token acts AS,
 *     an optional `resources` fence, and a forced `read_only`. A profile is EXCLUSIVE (it defines the
 *     whole token), so it is never combined with other offers. `ops.observer` is the first one.
 *
 * Contribution, zero core change:
 *   - a module ships `configs/mcp-scope.ini` (one `[section]` per offer; the navigation.ini/menus.ini
 *     pattern) — discovered live, and SKIPPED when the module is deactivated (so the offer lives and
 *     dies with its module, like the ACL role it points at), or
 *   - a module Bootstrap calls `Tiger_Mcp_Scope::register([...])`.
 *
 * Enforcement is UNCHANGED and lives elsewhere: `Tiger_Mcp_Token` (modules/read_only/role/resources)
 * + the ACL at dispatch. This registry only feeds the MINT step and the UI — it never gates a call.
 *
 * @api
 * @see Tiger_Mcp_Token        the per-token policy this resolves into
 * @see Mcp_Service_Settings   the /api that reads offers() and mints against resolve()
 */
class Tiger_Mcp_Scope
{
    /** The base MODULE offers — the curated starter set (`default`) plus the opt-in extras. */
    const BASE = [
        ['key' => 'cms',       'module' => 'cms',       'default' => true],
        ['key' => 'blog',      'module' => 'blog',      'default' => true],
        ['key' => 'media',     'module' => 'media',     'default' => true],
        ['key' => 'search',    'module' => 'search',    'default' => true],
        ['key' => 'docs',      'module' => 'docs',      'default' => true],
        ['key' => 'git',       'module' => 'git',       'default' => true],
        ['key' => 'access',    'module' => 'access',    'default' => false],
        ['key' => 'analytics', 'module' => 'analytics', 'default' => false],
    ];

    /** Config filename a module drops in its `configs/` to contribute offers with no code. */
    const INI_FILE = 'mcp-scope.ini';

    /** @var array<string,array> code-registered offers (module Bootstrap path), keyed by `key`. */
    protected static $registered = [];

    /**
     * Register a scope offer programmatically (a module Bootstrap). Last write wins on `key`.
     *
     * @param  array $offer {key, label?, module, role?, read_only?, resources?, resource?, default?, gated?}
     * @return void
     * @throws InvalidArgumentException when `key` or `module` is missing
     */
    public static function register(array $offer)
    {
        $o = self::_normalize($offer);
        self::$registered[$o['key']] = $o;
    }

    /** Drop all code-registered offers (test hygiene; does not affect ini/base discovery). */
    public static function resetRegistered()
    {
        self::$registered = [];
    }

    /**
     * Every known offer — base module offers, then ini-discovered offers from ACTIVE modules, then
     * code-registered offers (later wins on a key collision), keyed by `key` and ksorted.
     *
     * @param  string[]|null $roots module-dir roots to scan (default: APPLICATION_PATH + TIGER_CORE_PATH)
     * @return array<string,array>
     */
    public static function all(?array $roots = null)
    {
        $out = [];
        foreach (self::BASE as $o) { $o = self::_normalize($o); $out[$o['key']] = $o; }
        foreach (self::_fromIni($roots) as $o) { $out[$o['key']] = $o; }
        foreach (self::$registered as $o)      { $out[$o['key']] = $o; }
        ksort($out);
        return $out;
    }

    /**
     * The offers a caller holding `$role` may mint — `all()` minus any whose gating ACL `resource`
     * the role is not allowed to reach. An offer with no `resource` is always offered (the mintToken
     * ACL already gates who can mint at all). Discovery/ACL failures fail CLOSED (the offer is hidden).
     *
     * @param  string        $role the caller's role
     * @param  Zend_Acl|null $acl   the ACL (default: the registered `Zend_Acl`)
     * @param  string[]|null  $roots module-dir roots (test seam)
     * @return array<int,array> a display-ready, ordered list (profiles last)
     */
    public static function offers($role, $acl = null, ?array $roots = null)
    {
        if ($acl === null) {
            $acl = Zend_Registry::isRegistered('Zend_Acl') ? Zend_Registry::get('Zend_Acl') : null;
        }
        $out = [];
        foreach (self::all($roots) as $o) {
            if ($o['resource'] !== '') {
                try {
                    if (!$acl || !$acl->has($o['resource']) || !$acl->isAllowed($role, $o['resource'])) {
                        continue;   // not allowed to mint this profile → never offered
                    }
                } catch (Throwable $e) {
                    continue;       // fail closed
                }
            }
            $out[] = $o;
        }
        // Module offers first (the checkboxes), profiles after (the selector) — stable within each.
        usort($out, static function ($a, $b) {
            if ($a['profile'] !== $b['profile']) { return $a['profile'] ? 1 : -1; }
            return strcmp($a['key'], $b['key']);
        });
        return $out;
    }

    /** One offer by key, or null. */
    public static function get($key, ?array $roots = null)
    {
        $all = self::all($roots);
        return $all[(string) $key] ?? null;
    }

    /**
     * Resolve a chosen set of offer keys into a concrete token policy, authorizing each against the
     * caller's role. The rules that keep this least-privilege and escalation-proof:
     *   - every key must exist AND (if it declares a gating `resource`) be allowed to `$callerRole`;
     *   - a PROFILE offer is EXCLUSIVE — selected alone, it defines the whole token (role, modules,
     *     read_only, resources). Mixed with anything else, or two profiles together, is refused;
     *   - a token's non-inherited `role` can ONLY come from a profile the caller was authorized to
     *     mint, so a token can never carry a role the caller couldn't grant;
     *   - an EMPTY selection is refused — "select nothing" never silently widens to a default set.
     *
     * @param  string[]      $keys        the chosen offer keys
     * @param  string        $callerRole  the minting caller's role
     * @param  bool          $readOnlyFlag the operator's read-only toggle (profiles may force it)
     * @param  Zend_Acl|null $acl         the ACL (default: the registered `Zend_Acl`)
     * @param  string[]|null  $roots       module-dir roots (test seam)
     * @return array{modules:string[],role:string,read_only:bool,resources:array}
     * @throws InvalidArgumentException with a translation key on an empty/invalid/unauthorized set
     */
    public static function resolve(array $keys, $callerRole, $readOnlyFlag = false, $acl = null, ?array $roots = null)
    {
        $keys = array_values(array_unique(array_filter(array_map('strval', $keys))));
        if (!$keys) { throw new InvalidArgumentException('mcp.scope.error.empty'); }

        if ($acl === null) {
            $acl = Zend_Registry::isRegistered('Zend_Acl') ? Zend_Registry::get('Zend_Acl') : null;
        }
        $all      = self::all($roots);
        $chosen   = [];
        $profiles = [];
        foreach ($keys as $k) {
            if (!isset($all[$k])) { throw new InvalidArgumentException('mcp.scope.error.unknown'); }
            $o = $all[$k];
            if ($o['resource'] !== '') {
                $ok = false;
                try { $ok = $acl && $acl->has($o['resource']) && $acl->isAllowed($callerRole, $o['resource']); }
                catch (Throwable $e) { $ok = false; }
                if (!$ok) { throw new InvalidArgumentException('mcp.scope.error.not_allowed'); }
            }
            $chosen[] = $o;
            if ($o['profile']) { $profiles[] = $o; }
        }

        if ($profiles) {
            if (count($chosen) > 1) { throw new InvalidArgumentException('mcp.scope.error.profile_exclusive'); }
            $p = $profiles[0];
            return [
                'modules'   => array_values(array_unique(array_merge([$p['module']], $p['extra_modules']))),
                'role'      => $p['role'],
                'read_only' => $p['read_only'] === null ? (bool) $readOnlyFlag : (bool) $p['read_only'],
                'resources' => $p['resources'],
            ];
        }

        $modules = [];
        foreach ($chosen as $o) { $modules[] = $o['module']; }
        return [
            'modules'   => array_values(array_unique(array_filter($modules))),
            'role'      => '',             // inherit the caller's role (a plain module token)
            'read_only' => (bool) $readOnlyFlag,
            'resources' => [],
        ];
    }

    /** Discover offers from every ACTIVE module's `configs/mcp-scope.ini`. */
    protected static function _fromIni(?array $roots = null)
    {
        $inactive = [];
        try { $inactive = array_flip((new Tiger_Model_Module())->inactiveSlugs()); } catch (Throwable $e) {}

        $out = [];
        foreach (self::_roots($roots) as $root) {
            foreach (glob($root . '/*/configs/' . self::INI_FILE) ?: [] as $file) {
                $slug = basename(dirname(dirname($file)));
                if (isset($inactive[$slug])) { continue; }
                try {
                    $cfg = (new Zend_Config_Ini($file))->toArray();
                } catch (Throwable $e) { continue; }   // a malformed file never breaks the screen
                foreach ($cfg as $key => $row) {
                    if (!is_array($row)) { continue; }
                    $row['key'] = (string) $key;
                    if (!isset($row['module'])) { $row['module'] = $slug; }
                    try { $out[(string) $key] = self::_normalize($row); } catch (Throwable $e) { /* skip bad */ }
                }
            }
        }
        return $out;
    }

    /** Normalize + validate one offer into the canonical shape. */
    protected static function _normalize(array $o)
    {
        $key    = isset($o['key']) ? preg_replace('/[^a-z0-9._-]/', '', strtolower((string) $o['key'])) : '';
        $module = isset($o['module']) ? preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $o['module'])) : '';
        if ($key === '' || $module === '') {
            throw new InvalidArgumentException('a scope offer needs a key and a module');
        }
        $role = isset($o['role']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $o['role']) : '';

        // read_only: null = operator chooses; a present flag forces it on/off.
        $readOnly = null;
        if (array_key_exists('read_only', $o) && $o['read_only'] !== '' && $o['read_only'] !== null) {
            $readOnly = !in_array((string) $o['read_only'], ['0', 'false', 'no', ''], true);
        }

        return [
            'key'           => $key,
            'label'         => isset($o['label']) && $o['label'] !== '' ? (string) $o['label'] : $key,
            'module'        => $module,
            'extra_modules' => self::_csv($o['extra_modules'] ?? ''),   // extra modules a profile also grants
            'role'          => $role,
            'read_only'     => $readOnly,
            'resources'     => self::_resources($o['resources'] ?? []),
            'resource'      => isset($o['resource']) ? (string) $o['resource'] : '',
            'default'       => !empty($o['default']) && !in_array((string) $o['default'], ['0', 'false'], true),
            'gated'         => !empty($o['gated']) && !in_array((string) $o['gated'], ['0', 'false'], true),
            'profile'       => $role !== '',
        ];
    }

    /** Normalize a `{service: methods}` resources map — methods as a csv string or an array. */
    protected static function _resources($raw)
    {
        if (!is_array($raw)) { return []; }
        $out = [];
        foreach ($raw as $service => $methods) {
            $svc = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $service));
            if ($svc === '') { continue; }
            $list = is_array($methods) ? array_map('strval', $methods) : self::_csv((string) $methods);
            $list = array_values(array_filter(array_map(static fn($m) => preg_replace('/[^a-zA-Z0-9_]/', '', (string) $m), $list)));
            if ($list) { $out[$svc] = array_values(array_unique($list)); }
        }
        return $out;
    }

    /** A csv-or-nothing → trimmed string list. */
    protected static function _csv($raw)
    {
        if (is_array($raw)) { return array_values(array_filter(array_map('strval', $raw))); }
        return array_values(array_filter(array_map('trim', explode(',', (string) $raw))));
    }

    /** The module-dir roots to scan. */
    protected static function _roots(?array $roots = null)
    {
        if ($roots !== null) { return $roots; }
        $r = [];
        if (defined('APPLICATION_PATH')) { $r[] = APPLICATION_PATH . '/modules'; }
        if (defined('TIGER_CORE_PATH'))  { $r[] = TIGER_CORE_PATH . '/modules'; }
        return $r;
    }
}
