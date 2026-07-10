<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Controller_Plugin_RouteOverride — apply declared pretty-route overrides.
 *
 * At routeShutdown (after routing, before dispatch) this rewrites a request to a canonical
 * MVC target when a declared override's prefix matches — but ONLY for URLs that no real
 * controller already handles. That single guard (the PageDispatch trick, `isDispatchable`)
 * is what lets a module mount a pretty `/docs` alias WITHOUT shadowing its own real paths:
 *
 *   /docs                -> dispatchable (Docs_IndexController) -> left alone
 *   /docs/admin/settings -> dispatchable (Docs_AdminController) -> left alone
 *   /docs/getting-started-> NOT dispatchable -> rewritten to `docs/index/docs` + slug=getting-started
 *
 * Overrides are walked in explicit priority order (Tiger_Routing_Overrides::all(), DESC), and
 * the FIRST matching prefix wins — so ordering is decided here, in one place, never by Zend's
 * last-in-first-out route stack. The remainder of the path (possibly nested) becomes the `slug`
 * param. Registered ahead of PageDispatch so a pretty route claims its slug before the CMS
 * content fallback considers it. Graceful: no overrides / no match -> the request is untouched.
 *
 * @api
 */
class Tiger_Controller_Plugin_RouteOverride extends Zend_Controller_Plugin_Abstract
{
    /**
     * Rewrite an unmatched URL to a declared override's canonical MVC target, with the
     * remaining path handed on as the `slug` param.
     *
     * @param  Zend_Controller_Request_Abstract $request the current request
     * @return void
     */
    public function routeShutdown(Zend_Controller_Request_Abstract $request)
    {
        if (!$request instanceof Zend_Controller_Request_Http) {
            return;
        }

        // A real controller already claims this URL (a module's own pages, /api, /auth, /admin)
        // — never override it. This is the whole reason pretty aliases and admin paths coexist.
        $front = Zend_Controller_Front::getInstance();
        if ($front->getDispatcher()->isDispatchable($request)) {
            return;
        }

        $path = trim($request->getPathInfo(), '/');   // locale already stripped by LocalePrefix
        if ($path === '') {
            return;
        }

        foreach (Tiger_Routing_Overrides::all() as $o) {
            $prefix = $o['prefix'];
            if ($path !== $prefix && strpos($path, $prefix . '/') !== 0) {
                continue;
            }
            [$module, $controller, $action] = $o['mca'];
            $request->setModuleName($module)
                    ->setControllerName($controller)
                    ->setActionName($action);

            $slug = ($path === $prefix) ? '' : substr($path, strlen($prefix) + 1);
            if ($slug !== '') {
                $request->setParam('slug', $slug);
            }
            return;   // first (highest-priority) match wins
        }
    }
}
