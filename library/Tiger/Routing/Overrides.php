<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Routing_Overrides — the pretty-route registry (module hook + admin override tier).
 *
 * A module's feature is always reachable at its canonical MVC path — `<module>/<controller>/
 * <action>` — via ZF1's built-in route, with ZERO registration. A *pretty* alias (e.g. `/docs`
 * -> `docs/index/docs`) is an OPTIONAL override declared here:
 *
 *   // in the module Bootstrap (the shipped default):
 *   Tiger_Routing_Overrides::register('docs', [
 *       'pattern'  => 'docs',            // public path prefix; the remainder becomes `slug`
 *       'target'   => 'docs/index/docs', // canonical module/controller/action
 *       'priority' => 100,               // higher = checked first when prefixes overlap
 *   ]);
 *
 * The admin overrides a declaration BY NAME through the `config` DB tier — no new table, no
 * deploy (config-discipline: the config store, not a settings table). Any of pattern / target
 * / priority / enabled can be set; the DB value wins over the module default:
 *
 *   tiger.routing.override.docs.pattern  = "help"   ; retarget the public prefix to /help
 *   tiger.routing.override.docs.enabled  = 0        ; turn the pretty route off entirely
 *   tiger.routing.override.docs.priority = 250      ; reorder vs. another module's override
 *
 * Order is honored by Tiger_Controller_Plugin_RouteOverride, which walks all() (sorted by
 * priority DESC) and rewrites the FIRST matching path — but ONLY when no real controller claims
 * it, so a module's own admin paths (/docs/admin/settings) and the reserved kernel prefixes
 * (/api, /auth, /admin) are never shadowed. Because the plugin controls iteration order itself,
 * pretty routes are immune to Zend's last-in-first-out route matching.
 *
 * Priority is intentionally OPEN — a module may declare any weight (even one that outranks
 * core). Open is open; guardrails (band-clamping) can be added later if abuse ever shows up.
 *
 * @api
 */
class Tiger_Routing_Overrides
{
    /** Public path heads a pretty route may never claim (kernel surfaces). */
    protected static $_reserved = ['api', 'auth', 'admin'];

    /** @var array<string,array> name => raw declared spec (module defaults) */
    protected static $_declared = [];

    /**
     * Declare a module's default pretty route. Called from a module Bootstrap. The admin can
     * override it by name via config. Re-registering the same name replaces the default.
     *
     * @param  string $name the override's unique name
     * @param  array  $spec the declared spec (pattern/target/priority/enabled)
     * @return void
     */
    public static function register($name, array $spec)
    {
        $name = self::_key($name);
        if ($name === '') {
            return;
        }
        self::$_declared[$name] = [
            'pattern'  => isset($spec['pattern'])  ? (string) $spec['pattern'] : '',
            'target'   => isset($spec['target'])   ? (string) $spec['target']  : '',
            'priority' => isset($spec['priority']) ? (int) $spec['priority']    : 100,
            'enabled'  => array_key_exists('enabled', $spec) ? (bool) $spec['enabled'] : true,
        ];
    }

    /**
     * The effective, resolved spec for one override (module default merged UNDER any config
     * override), regardless of enabled state — for the admin settings screen. Null if neither
     * a declaration nor config exists for the name.
     *
     * @param  string           $name   the override name
     * @param  Zend_Config|null $config config to read the override tier from (defaults to the registry)
     * @return array{name:string,pattern:string,prefix:string,target:string,mca:array,priority:int,enabled:bool}|null
     */
    public static function get($name, $config = null)
    {
        $name = self::_key($name);
        $base = self::$_declared[$name] ?? null;
        $over = self::_fromConfig($config)[$name] ?? null;
        if ($base === null && $over === null) {
            return null;
        }
        $base = $base ?: ['pattern' => '', 'target' => '', 'priority' => 100, 'enabled' => true];
        $over = is_array($over) ? $over : [];

        $pattern = isset($over['pattern']) ? (string) $over['pattern'] : $base['pattern'];
        $target  = isset($over['target'])  ? (string) $over['target']  : $base['target'];

        return [
            'name'     => $name,
            'pattern'  => $pattern,
            'prefix'   => self::_prefix($pattern),
            'target'   => $target,
            'mca'      => self::_mca($target),
            'priority' => isset($over['priority']) ? (int) $over['priority'] : (int) $base['priority'],
            'enabled'  => isset($over['enabled']) ? (bool) (int) $over['enabled'] : (bool) $base['enabled'],
        ];
    }

    /**
     * All ENABLED, valid overrides, sorted by priority DESC (highest checked first). Invalid
     * (no prefix/target) or reserved-prefix entries are dropped. This is what the plugin walks.
     *
     * @param  Zend_Config|null $config config to read the override tier from (defaults to the registry)
     * @return array<int,array>
     */
    public static function all($config = null)
    {
        $names = array_unique(array_merge(
            array_keys(self::$_declared),
            array_keys(self::_fromConfig($config))
        ));

        $out = [];
        foreach ($names as $name) {
            $spec = self::get($name, $config);
            if (!$spec || !$spec['enabled']) {
                continue;
            }
            if ($spec['prefix'] === '' || $spec['target'] === '' || self::_isReserved($spec['prefix'])) {
                continue;
            }
            $out[] = $spec;
        }
        usort($out, function ($a, $b) {
            return [$b['priority'], $a['name']] <=> [$a['priority'], $b['name']];   // priority DESC, then name ASC
        });
        return $out;
    }

    /**
     * Reset declarations (tests).
     *
     * @return void
     */
    public static function clear()
    {
        self::$_declared = [];
    }

    /** The `tiger.routing.override.*` config subtree as [name => partial spec], or []. */
    protected static function _fromConfig($config)
    {
        $cfg = $config;
        if ($cfg === null && Zend_Registry::isRegistered('Zend_Config')) {
            $cfg = Zend_Registry::get('Zend_Config');
        }
        if (!$cfg instanceof Zend_Config) {
            return [];
        }
        foreach (['tiger', 'routing', 'override'] as $seg) {
            $cfg = $cfg->get($seg);
            if (!$cfg instanceof Zend_Config) {
                return [];
            }
        }
        $arr = $cfg->toArray();
        return is_array($arr) ? $arr : [];
    }

    /** Derive the public path prefix from a pattern: strip trailing route vars (`:slug`, `*`). */
    protected static function _prefix($pattern)
    {
        $segs = explode('/', trim((string) $pattern, '/'));
        $out  = [];
        foreach ($segs as $s) {
            if ($s === '' || $s[0] === ':' || $s === '*') {
                break;
            }
            $out[] = $s;
        }
        return implode('/', $out);
    }

    /** Split a `module/controller/action` target into [m,c,a], defaulting missing parts. */
    protected static function _mca($target)
    {
        $parts = array_values(array_filter(explode('/', trim((string) $target, '/')), 'strlen'));
        return array_pad($parts, 3, 'index');
    }

    /** True if the prefix's first segment is a reserved kernel surface. */
    protected static function _isReserved($prefix)
    {
        $head = strtolower((string) strtok((string) $prefix, '/'));
        return in_array($head, self::$_reserved, true);
    }

    /** Normalize an override name to a safe key. */
    protected static function _key($name)
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', (string) $name));
    }
}
