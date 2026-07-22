<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Controller_Plugin_PageDispatch — let CMS content own the site's public URLs.
 *
 * At routeShutdown (after routing, before dispatch) this checks whether a published `page`
 * row claims the requested slug. If one does it hands off to PageController::viewAction —
 * even when a shipped route already matched (e.g. the `/vibe` marketing alias), so admins
 * can REPLACE any built-in landing page with real, editable, SEO'd CMS content just by
 * giving a page that slug. No code, no route edit.
 *
 * The one guard is a reserved-slug blacklist: every registered MODULE namespace (`/admin`,
 * `/api`, `/docs`, `/pay`, …) plus the core default-module system controllers are off-limits,
 * so a page can never shadow real application routes. Everything else is CMS-claimable.
 *
 * If no page claims the slug and nothing else will dispatch it, a `page_redirect` 301s;
 * otherwise the request is left untouched so ZF's ErrorHandler renders a clean 404.
 *
 * Runs after LocalePrefix (so the path is locale-stripped and LANG is set) and before the
 * authorization plugin (which gates PageController — public in acl.ini). Public pages resolve
 * at global scope for now; per-tenant public sites (host -> org) are a later addition. The
 * root `/` is intentionally left to IndexController, which serves the admin-chosen home page.
 *
 * @api
 */
class Tiger_Controller_Plugin_PageDispatch extends Zend_Controller_Plugin_Abstract
{
    /**
     * Route a URL to a published CMS page (overriding shipped routes for non-reserved slugs),
     * or 301 a moved slug.
     *
     * @param  Zend_Controller_Request_Abstract $request the current request
     * @return void
     */
    public function routeShutdown(Zend_Controller_Request_Abstract $request)
    {
        if (!$request instanceof Zend_Controller_Request_Http) {
            return;
        }

        $slug = trim($request->getPathInfo(), '/');
        if ($slug === '') {
            return;   // the root belongs to IndexController (it serves the admin-chosen home page)
        }

        // Reserved namespaces a CMS page may never shadow — leave routing exactly as matched.
        $first = strtolower((string) strtok($slug, '/'));
        if (in_array($first, $this->_reserved(), true)) {
            return;
        }

        $front  = Zend_Controller_Front::getInstance();
        $locale = defined('LANG') ? LANG : 'en';

        try {
            $orgId = Tiger_Model_Org::siteOrgId();   // the org owning this public site (root org on a
                                                     // stock install; a multi-site module resolves
                                                     // host->org). Read scope [org, ''], so shared ''
                                                     // content still shows.

            // Only real pages answer at the site root — articles/posts route under /blog.
            $page = (new Tiger_Model_Page())->resolveBySlug($slug, $locale, $orgId, Tiger_Model_Page::TYPE_PAGE);
            if ($page) {
                // A published page claims this slug — it WINS, even over a matched shipped route.
                $request->setModuleName($front->getDispatcher()->getDefaultModule())
                        ->setControllerName('page')
                        ->setActionName('view')
                        ->setParam('cms_page_id', $page->page_id);
                return;
            }

            // No page claims it. If no real controller will dispatch it either, honour a moved slug.
            if (!$front->getDispatcher()->isDispatchable($request)) {
                $redirect = (new Tiger_Model_PageRedirect())->findFrom($slug, $locale, $orgId);
                if ($redirect) {
                    Zend_Controller_Action_HelperBroker::getStaticHelper('redirector')
                        ->setCode((int) $redirect->code)
                        ->gotoUrlAndExit('/' . ltrim($redirect->to_slug, '/'));
                }
            }
        } catch (Throwable $e) {
            // fail-open — a broken page/redirect lookup must never take down routing
        }
        // no page, no redirect -> untouched -> matched controller dispatches (or ErrorHandler 404)
    }

    /**
     * Slugs a CMS page may never shadow: every registered module namespace (so /docs, /pay, /api…
     * always reach their module) plus the core default-module system controllers. Built fresh each
     * request from the live module list, so a newly installed module is protected automatically.
     *
     * @return string[] lowercase reserved first path-segments
     */
    protected function _reserved()
    {
        $mods = array_map('strtolower', array_keys(
            Zend_Controller_Front::getInstance()->getControllerDirectory()
        ));
        // Default-module system controllers (not modules, so not in the list above).
        $sys = ['admin', 'auth', 'error', 'install', 'login', 'logout', 'page'];
        return array_values(array_unique(array_merge($mods, $sys)));
    }
}
