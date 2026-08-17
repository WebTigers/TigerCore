<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Routing_ModuleRoutes — ingests every ACTIVE module's `configs/routes.ini` into the router.
 *
 * This is the missing per-type consumer for module route config — the exact mirror of how
 * `Tiger_Admin_Nav` discovers `configs/navigation.ini`, `Tiger_Acl_Acl` discovers `configs/acl.ini`,
 * and `Tiger_Schedule` discovers `configs/schedule.ini`. A module declares its pretty URLs as native
 * ZF1 `resources.router.routes.*` in `configs/routes.ini` (declarative, no Bootstrap code), and the core
 * bootstrap (`Tiger_Application_Bootstrap::_initModuleRoutes`) hands them to the rewrite router here.
 *
 * WHY module routes.ini didn't work before: `Tiger_Application::buildConfig()` only merges the CORE and
 * APP `configs/routes.ini` into the global config — module route files had no consumer at all, so a
 * module's pretty URL only worked if it called `$router->addRoute()` from its Bootstrap (config-as-code).
 * This closes that gap: drop the file, get the route.
 *
 * Collision safety: every route is namespaced `<slug>__<name>`, so a module can never stomp a core route
 * or another module's route by reusing a name. App-dir modules override a same-slug core-dir module
 * (scanned last wins), matching the app-over-vendor precedence used everywhere else. Route *matching*
 * still respects declaration order within a file (ZF1 checks routes newest-first), so a module orders its
 * more-specific routes last to shadow its own catch-alls — exactly as `$router->addRoute()` did.
 *
 * @api
 * @see Tiger_Admin_Nav        the sibling discovery this mirrors (navigation.ini)
 * @see Tiger_Application_Bootstrap::_initModuleRoutes  the bootstrap seam that calls apply()
 */
class Tiger_Routing_ModuleRoutes
{
    /**
     * Collect namespaced route definitions from active modules' `configs/routes.ini`.
     *
     * @param  array<int,string>  $moduleDirs    dirs to scan; each holds `<slug>/configs/routes.ini`
     * @param  array<int,string>  $inactiveSlugs module slugs to skip (deactivated → no routes)
     * @param  string|null        $env           the ini env section (APPLICATION_ENV); null = flat file
     * @return array<string,array> namespaced name (`<slug>__<name>`) => a ZF1 route definition array
     */
    public static function collect(array $moduleDirs, array $inactiveSlugs = [], $env = null)
    {
        $out = [];
        foreach ($moduleDirs as $modsDir) {
            foreach (glob($modsDir . '/*', GLOB_ONLYDIR) ?: [] as $moduleDir) {
                $slug = basename($moduleDir);
                if (in_array($slug, $inactiveSlugs, true)) { continue; }   // deactivated → skipped (mirrors Nav)
                $ini = $moduleDir . '/configs/routes.ini';
                if (!is_file($ini)) { continue; }
                try {
                    $routes = self::_routesNode(new Zend_Config_Ini($ini, $env));
                    if ($routes === null) { continue; }
                    foreach ($routes as $name => $def) {
                        if ($def instanceof Zend_Config) { $out[$slug . '__' . $name] = $def->toArray(); }
                    }
                } catch (Throwable $e) {
                    error_log('Tiger_Routing_ModuleRoutes: failed to load ' . $ini . ' — ' . $e->getMessage());
                }
            }
        }
        return $out;
    }

    /**
     * Collect + apply active modules' routes to a rewrite router via ZF1's native route factory.
     *
     * @param  Zend_Controller_Router_Rewrite $router        the front controller's router
     * @param  array<int,string>              $moduleDirs    dirs to scan
     * @param  array<int,string>              $inactiveSlugs slugs to skip
     * @param  string|null                    $env           the ini env section
     * @return int the number of routes registered
     */
    public static function apply($router, array $moduleDirs, array $inactiveSlugs = [], $env = null)
    {
        $routes = self::collect($moduleDirs, $inactiveSlugs, $env);
        if (!$routes) { return 0; }
        // addConfig() runs each entry through ZF1's route factory (honors `type`, defaulting to
        // Zend_Controller_Router_Route) and preserves order → newest-first matching is retained.
        $router->addConfig(new Zend_Config($routes));
        return count($routes);
    }

    /** Navigate to `resources.router.routes` in a parsed routes.ini, or null if absent/malformed. */
    private static function _routesNode(Zend_Config $cfg)
    {
        $res    = $cfg->get('resources');
        $router = ($res instanceof Zend_Config) ? $res->get('router') : null;
        $routes = ($router instanceof Zend_Config) ? $router->get('routes') : null;
        return ($routes instanceof Zend_Config) ? $routes : null;
    }
}
