<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Service_Locator — turn the module + service NAMES a caller sends (`/api`, MCP `tools/call`, the agent
 * Forge, the agent/MCP catalog) into the service's REAL class name, whatever the casing (TIGER-531).
 *
 * Every one of those paths used to rebuild the class as `ucfirst($module) . '_Service_' . ucfirst($service)`.
 * That is right for a one-word service (`page` → `Cms_Service_Page`) and wrong for a two-word one: the OpenAPI
 * / MCP name is lower-cased (`orgaddress`), so it rebuilt `Profile_Service_Orgaddress` — an ACL resource that
 * doesn't exist (Zend_Acl ids are case-sensitive, so the op was silently dropped from the catalog and refused
 * at dispatch) and a file (`services/Orgaddress.php`) a case-sensitive filesystem doesn't have. Only a caller
 * that happened to send the exact casing (`OrgAddress`, as the profile JS does) got through.
 *
 * The rule: the `ucfirst` name wins when it IS the declared class (the common case — no file scan). Otherwise
 * the module's own `services/` directory is the authority: the file whose name matches the service
 * case-insensitively declares the real class. Nothing outside that one module's services/ is ever consulted,
 * so a name can never resolve to another module's class.
 *
 * @api
 */
class Tiger_Service_Locator
{
    /** @var array<string,string|null> per-request memo: "module/service" (lower) => class|null */
    protected static $_memo = [];

    /**
     * The real `Module_Service_X` class for a module + service name, or the plain `ucfirst` rebuild when no
     * service file matches (so a caller's existing not-found / ACL-denied handling is unchanged).
     *
     * @param  string $module  the module slug (`profile`)
     * @param  string $service the service name, any casing (`orgaddress`, `OrgAddress`)
     * @return string
     */
    public static function className($module, $service)
    {
        $module  = (string) $module;
        $service = (string) $service;
        $guess   = ucfirst($module) . '_Service_' . ucfirst($service);
        if (!preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $module) || !preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $service)) {
            return $guess;   // not a well-formed name — never scan the filesystem for it
        }
        $key = strtolower($module . '/' . $service);
        if (array_key_exists($key, self::$_memo)) {
            return self::$_memo[$key] ?? $guess;
        }
        // Fast path: the ucfirst name is exactly a declared class (one-word services; exact-cased callers).
        if (class_exists($guess, true) && (new ReflectionClass($guess))->getName() === $guess) {
            return self::$_memo[$key] = $guess;
        }
        self::$_memo[$key] = self::_fromServicesDir(strtolower($module), $service);
        return self::$_memo[$key] ?? $guess;
    }

    /** Forget the memo (tests). */
    public static function reset()
    {
        self::$_memo = [];
    }

    /** The class declared by the module's services/<Service>.php whose name matches case-insensitively. */
    protected static function _fromServicesDir($slug, $service)
    {
        $dir = self::_servicesDir($slug);
        if ($dir === null) {
            return null;
        }
        foreach (glob($dir . '/*.php') ?: [] as $file) {
            if (strcasecmp(basename($file, '.php'), $service) !== 0) {
                continue;
            }
            $src    = (string) @file_get_contents($file);
            $prefix = preg_quote(ucfirst($slug), '/');
            if (preg_match('/\bclass\s+(' . $prefix . '_Service_[A-Za-z0-9]+)\b/i', $src, $m)) {
                return $m[1];
            }
        }
        return null;
    }

    /** The module's services/ dir (app area first, like discovery), or null if it has none. */
    protected static function _servicesDir($slug)
    {
        foreach ([defined('APPLICATION_PATH') ? APPLICATION_PATH : null, defined('TIGER_CORE_PATH') ? TIGER_CORE_PATH : null] as $base) {
            if ($base !== null && is_dir($base . '/modules/' . $slug . '/services')) {
                return $base . '/modules/' . $slug . '/services';
            }
        }
        return null;
    }
}
