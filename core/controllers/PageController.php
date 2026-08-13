<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * PageController — renders a CMS `page` (default namespace).
 *
 * Reached only via Tiger_Controller_Plugin_PageDispatch, which resolves the slug and
 * passes the page id. It renders the page through Tiger_Cms_Renderer and outputs:
 *
 *   - page HAS a layout_key  -> the CMS layout is a full-page template, so the output
 *     is self-contained: disable the theme layout and send the HTML as the body.
 *   - page has NO layout_key -> the body is content only, so it renders inside the
 *     active theme's PUBLIC layout (site header/footer/chrome) via page/view.phtml.
 *
 * Public in acl.ini. A missing page throws a 404 (handled by ErrorController).
 */
class PageController extends Tiger_Controller_Action
{
    /**
     * Render the resolved CMS page, self-contained or wrapped in the theme's public layout.
     *
     * @return void
     * @throws Zend_Controller_Action_Exception when the page id resolves to no page (404).
     */
    public function viewAction()
    {
        $pageId = $this->getParam('cms_page_id');
        $page   = $pageId ? (new Tiger_Model_Page())->findById($pageId) : null;

        if (!$page) {
            throw new Zend_Controller_Action_Exception('Page not found', 404);
        }

        $html = (new Tiger_Cms_Renderer())->render($page);
        $this->getResponse()->setHeader('Content-Type', 'text/html; charset=utf-8');

        // Per-page head + body scripts (admin-authored, from the page `meta`). These are output
        // VERBATIM so external CSS/JS and inline scripts actually run on the public page.
        $meta = [];
        if (!empty($page->meta)) {
            $decoded = is_array($page->meta) ? $page->meta : json_decode((string) $page->meta, true);
            if (is_array($decoded)) { $meta = $decoded; }
        }
        $head    = (string) ($meta['head_html'] ?? '');    // admin-authored raw <head> — the escape hatch
        $scripts = (string) ($meta['body_scripts'] ?? '');
        // The meta description (and other SEO) is no longer synthesized here — it lives in meta.seo and is
        // rendered through the head registry by TigerSEO (Seo_Plugin_Head → headMeta/headLink).

        // Every CMS page renders through the theme's public shell (layout.phtml). The SHELL owns the
        // document + ALL injection points (SEO head registry, analytics/tracking, consent, assets,
        // scripts, code-inject) — a layout never re-implements those. Tiger_Cms_Renderer has already
        // wrapped the body in its layout_key CONTENT-REGION layout (a full-width / sidebar / column
        // composition that renders INSIDE the shell's <main>), or returned the bare body. A layout is a
        // content-region template, not a whole page — so a CMS user never touches the shell plumbing.
        // (Formerly a layout_key was treated as a self-contained full document; retired — see AUTHORING.md.)
        $this->view->title       = $page->title;
        $this->view->cmsContent  = $html;
        $this->view->pageHead    = $head;      // the shell emits this in <head>
        $this->view->pageScripts = $scripts;   // …and this before </body>
        $this->view->pageMeta    = $meta;      // whole meta -> the shell can read theme hints (e.g. skin)

        // A page CUSTOMIZED FROM a theme (Cms_Service_Page fork stamps meta.source='theme') IS that
        // theme's page, so render it in the theme's OWN layout — exactly like the theme's file pages
        // (themeContentAction) — instead of the base site shell. This keeps the theme's chrome, CSS
        // (loaded by its layout, so no baking needed) and body context even for a 'content'-scoped
        // theme. Only when its source theme is the ACTIVE one (layout + assets live); a deactivated
        // source theme falls back to the base shell + the page's baked head_html. Same view script
        // (page/view.phtml echoes cmsContent) — only the layout differs.
        if ((string) ($meta['source'] ?? '') === 'theme' && (string) ($meta['source_key'] ?? '') !== '') {
            $active = '';
            try { $m = Tiger_Theme::manifest(); $active = (string) ($m['key'] ?? ''); } catch (Throwable $e) {}
            $dir = ((string) $meta['source_key'] === $active) ? (string) Tiger_Theme::dir() : '';
            if ($dir !== '' && is_dir($dir . '/layouts/scripts')) {
                $lay = isset($meta['layout']) ? preg_replace('/[^a-z0-9_-]/i', '', (string) $meta['layout']) : '';
                $this->_helper->layout()->setLayoutPath($dir . '/layouts/scripts');
                $this->_helper->layout()->setLayout($lay !== '' && $lay !== 'none' ? $lay : 'layout');
            }
        }
    }

    /**
     * Render a THEME's bundled static page (a `content/<slug>.phtml` body partial) inside the
     * active theme layout. Reached only via Tiger_Controller_Plugin_ThemeContent, which resolves
     * the slug and confirms no controller/CMS-page claimed it first. The partial may lead with a
     *   <!-- tiger:page title="…" skin="…" css="demos/x.css" view="view.foo" -->
     * hint line declaring the page's title + per-page head/scripts (the axes that vary across a
     * vendor theme's pages); the rest is the page body, wrapped by the shared layout.
     *
     * @return void
     * @throws Zend_Controller_Action_Exception when the slug resolves to no partial (404).
     */
    public function themeContentAction()
    {
        $slug = (string) $this->getParam('theme_content_slug', '');
        $dir  = Tiger_Theme::dir();
        $file = ($dir !== '' && $slug !== '') ? $dir . '/content/' . $slug . '.phtml' : '';

        if ($file === '' || !is_file($file)) {
            throw new Zend_Controller_Action_Exception('Page not found', 404);
        }

        $raw  = (string) file_get_contents($file);
        $meta = Tiger_Theme::hint($raw, 'tiger:page');
        $body = preg_replace('/^\s*<!--\s*tiger:page\b.*?-->\s*/s', '', $raw, 1);   // strip the hint line

        // Per-page head/scripts from the hint, resolved against the theme's own asset base.
        $base = Tiger_Theme::assetBase();
        $head = '';
        foreach (array_filter(array_map('trim', explode(',', (string) ($meta['css'] ?? '')))) as $css) {
            $head .= '<link rel="stylesheet" href="' . htmlspecialchars($base . '/css/' . $css, ENT_QUOTES) . '">' . "\n";
        }
        $scripts = '';
        if (!empty($meta['view'])) {
            $scripts .= '<script src="' . htmlspecialchars($base . '/js/views/' . $meta['view'] . '.js', ENT_QUOTES) . '"></script>' . "\n";
        }

        $this->view->title       = $meta['title'] ?? ucfirst(str_replace('-', ' ', $slug));
        $this->view->pageMeta     = $meta;         // 'skin' -> the layout selects the skin file
        $this->view->cmsContent   = $body;         // page/view.phtml echoes this; the theme layout wraps it
        $this->view->pageHead     = $head;
        $this->view->pageScripts  = $scripts;
        // A theme may ship several chrome variants (layouts/scripts/<layout>.phtml) — a landing
        // header vs an inner-page header, etc. The hint's `layout` picks one (default `layout`);
        // sanitized to a bare name so it can't escape the theme's layout dir. `layout="none"` means
        // the partial is a COMPLETE, self-contained document (its own head/chrome) — a bespoke page
        // (e.g. a vendor's prebuilt homepage) served verbatim with no theme layout wrapped around it.
        $layout = isset($meta['layout']) ? preg_replace('/[^a-z0-9_-]/i', '', $meta['layout']) : '';
        if ($layout === 'none') {
            $this->_helper->layout()->disableLayout();
        } else {
            // A theme's OWN pages always render in the theme's layout — even when the theme is
            // 'content'-scoped and the global site chrome is the base theme (see Bootstrap::_initTheme).
            if (is_dir($dir . '/layouts/scripts')) {
                $this->_helper->layout()->setLayoutPath($dir . '/layouts/scripts');
            }
            $this->_helper->layout()->setLayout($layout !== '' ? $layout : 'layout');
        }
        $this->_helper->viewRenderer->setScriptAction('view');   // reuse core/views/scripts/page/view.phtml
    }

}
