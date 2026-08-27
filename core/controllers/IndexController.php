<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Default-namespace Core controller.
 *
 * Lives in the `webtigers/tiger-core` package (vendor/), NOT in the app. The app's
 * Bootstrap points ZF1's default-module controller directory here. This is the proof
 * that a request resolves into Tiger-owned code shipped via Composer.
 */
class IndexController extends Zend_Controller_Action
{
    /**
     * Append the marketing-only stylesheet (a larger base font, etc.) to the END of the CSS stack so
     * it wins the cascade. Done once per request in init() (not per action), and only here — the
     * admin/auth/CMS surfaces never dispatch through IndexController, so they're untouched and no
     * body-class scoping is needed.
     *
     * @return void
     */
    public function init()
    {
        if (isset($this->view->themeAssets)) {
            $this->view->headLink()->appendStylesheet($this->view->asset($this->view->themeAssets . '/marketing.css'));
        }
    }

    /**
     * Serve the home page at "/": an admin-chosen CMS page, else the active theme's shipped
     * home (`content/index.phtml`), else the built-in landing.
     *
     * @return void
     */
    public function indexAction()
    {
        $home = $this->_homePageId();

        // 1a) An admin picked a PATH — a public module page (`/marketplace`, `/docs`) or any ad-hoc
        //     route. Forwarded, not redirected: the content has to be served AT "/" or it isn't the
        //     home page, it's a signpost pointing away from it.
        if ($home !== '' && $home[0] === '/') {
            if ($this->_forwardToPath($home)) { return; }
            // Unresolvable (the module was deactivated, the route retired) — fall through to the
            // theme home / built-in landing rather than 404ing the site's front door.
        }

        // 1b) An admin picked a CMS page as the home page (tiger.site.home_page)? Serve it.
        if ($home !== '' && $home[0] !== '/') {
            $page = (new Tiger_Model_Page())->findById($home);
            if ($page && $page->status === Tiger_Model_Page::STATUS_PUBLISHED) {
                $this->_forward('view', 'page', null, ['cms_page_id' => $home]);
                return;
            }
        }

        // 2) The active theme ships its own home (content/index.phtml)? Serve that — so "/" and the
        //    theme's stock "/index.html" link resolve to the same page (via PageController).
        if (Tiger_Theme::dir() !== '' && is_file(Tiger_Theme::dir() . '/content/index.phtml')) {
            $this->_forward('theme-content', 'page', null, ['theme_content_slug' => 'index']);
            return;
        }

        // 3) Rendered via index/index.phtml, wrapped in the active theme's layout.
        // The app Bootstrap already put theme/skin/themeAssets on the view.
        $this->view->servedBy     = __FILE__;
        $this->view->tigerVersion = Tiger_Version::VERSION;
        $this->view->zendVersion  = Zend_Version::VERSION;
        $this->view->localeView();   // index/index.es.phtml for es, else index/index.phtml
    }

    /**
     * `/vibe` — the SaaS-startup / "vibe coding" pitch (the former home page). A shipped marketing
     * page; the view owns its content. Routed via _initMarketingAliases.
     *
     * @return void
     */
    public function vibeAction()
    {
        $this->view->localeView();   // index/vibe.es.phtml for es, else index/vibe.phtml
    }

    /**
     * `/agency` — the agency story (one client or a hundred). A shipped marketing page.
     *
     * @return void
     */
    public function agencyAction()
    {
        $this->view->localeView();
    }

    /**
     * `/developers` — the open-source / builder story (free, BSD, extend via modules). Shipped marketing.
     *
     * @return void
     */
    public function developersAction()
    {
        $this->view->localeView();
    }

    /**
     * `/creators` — the plugin/theme creator story (build for the marketplace, keep your license).
     *
     * @return void
     */
    public function creatorsAction()
    {
        $this->view->localeView();
    }

    /**
     * `/hosting` — the hosting-partner story (add Tiger to the stack, zero licensing fees).
     *
     * @return void
     */
    public function hostingAction()
    {
        $this->view->localeView();
    }

    /**
     * `/features` — the full feature catalog: sectioned cards drawn from across the audience pages.
     * A shipped marketing page; the view owns its content.
     *
     * @return void
     */
    public function featuresAction()
    {
        $this->view->localeView();
    }

    /**
     * `/get-tiger` — the "Get Tiger" page: the four ways to run Tiger, the vibe-stack comparison, and
     * portability. A shipped marketing page; the view owns its content.
     *
     * @return void
     */
    public function getTigerAction()
    {
        $this->view->localeView();
    }

    /**
     * `/saas-vs-sias` — "You don't own your app. Your platform does." SaaS vs SiaS (Software *in* a
     * Service): the vibe-coding lock-in trap and what real ownership looks like. Shipped marketing.
     *
     * @return void
     */
    public function saasVsSiasAction()
    {
        $this->view->localeView();
    }

    /**
     * `/how-it-works` — "How Tiger Works": one framework, three paths (website / vibe-code / enterprise),
     * what ships in the box, the composition model, portability, and the comparisons. Shipped marketing.
     *
     * @return void
     */
    public function howItWorksAction()
    {
        $this->view->localeView();
    }

    /**
     * `/tech-stack` — "The Technology Stack": why each layer was chosen (proven, portable, fast,
     * secure, AI-native, owned). A shipped marketing page; the view owns its content.
     *
     * @return void
     */
    public function techStackAction()
    {
        $this->view->localeView();
    }

    /**
     * Forward "/" to an internal path — a public module page (`/marketplace`, `/docs`) or any
     * `module/controller/action`.
     *
     * Resolution mirrors `Tiger_Controller_Plugin_RouteOverride` on purpose: a module's PUBLIC page
     * is usually a registered override (a pretty prefix → a canonical MVC target), so matching the
     * override table first is what makes `/marketplace` work rather than only the long canonical
     * path. Anything not in that table falls back to a plain `module/controller/action` parse.
     *
     * Segments are sanitized to the same `[a-zA-Z0-9_-]` shape the dispatcher accepts, so a stored
     * value can't be used to reach outside normal dispatch.
     *
     * @param  string $path the configured path, leading slash included
     * @return bool         true when the request was forwarded
     */
    protected function _forwardToPath($path)
    {
        $clean = trim(parse_url($path, PHP_URL_PATH) ?: '', '/');
        if ($clean === '') { return false; }

        // A registered module override (the pretty public prefix).
        if (class_exists('Tiger_Routing_Overrides')) {
            foreach (Tiger_Routing_Overrides::all() as $o) {
                $prefix = (string) $o['prefix'];
                if ($clean !== $prefix && strpos($clean, $prefix . '/') !== 0) { continue; }
                [$module, $controller, $action] = $o['mca'];
                $slug = trim(substr($clean, strlen($prefix)), '/');
                $this->_forward($action, $controller, $module, $slug !== '' ? ['slug' => $slug] : []);
                return true;
            }
        }

        // Else a canonical module/controller/action path.
        $seg = array_values(array_filter(explode('/', $clean), 'strlen'));
        $ok  = static function ($s) { return (string) preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $s); };
        $module     = $ok($seg[0] ?? '');
        $controller = $ok($seg[1] ?? 'index');
        $action     = $ok($seg[2] ?? 'index');
        if ($module === '') { return false; }

        $front = Zend_Controller_Front::getInstance();
        $dirs  = (array) $front->getControllerDirectory();
        if (!isset($dirs[$module])) {
            // Not a module — treat the first segment as a default-namespace controller (/vibe).
            $this->_forward($ok($seg[1] ?? 'index'), $module, null);
            return true;
        }

        $this->_forward($action, $controller, $module);
        return true;
    }

    /**
     * The configured home page (`tiger.site.home_page`): a CMS `page_id`, a PATH beginning with "/",
     * or '' for the built-in landing.
     */
    protected function _homePageId()
    {
        if (!Zend_Registry::isRegistered('Zend_Config')) {
            return '';
        }
        $cfg  = Zend_Registry::get('Zend_Config');
        $site = $cfg->get('tiger') ? $cfg->tiger->get('site') : null;
        return $site ? (string) $site->get('home_page') : '';
    }
}
