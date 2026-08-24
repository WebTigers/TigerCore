<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Seo_Plugin_Head — populates the head registry for CMS pages, which dispatch through PageDispatch
 * (it resolves the slug → a `page` row and sets `cms_page_id`). Runs at preDispatch (after routing), so
 * the head containers are filled before the view + layout render. Fail-open: SEO never breaks a request.
 *
 * Blog articles render via their own controller (no `cms_page_id`) and call Seo_Service_Head directly.
 *
 * At postDispatch it then fills the SITE-level baseline (Seo_Service_Head::site) for everything else —
 * the shipped marketing pages (/vibe, /agency, …) are plain controller actions with no `page` row, so
 * without this they emitted NO og:* at all and a crawler fell back to scraping the DOM for an image.
 * postDispatch is the right seam: the view has rendered (the title is known) but the layout has not
 * (this plugin sits at stack index 90, ahead of Zend_Layout's 99), so the head registry is still open.
 */
class Seo_Plugin_Head extends Zend_Controller_Plugin_Abstract
{
    /**
     * @param  Zend_Controller_Request_Abstract $request
     * @return void
     */
    public function preDispatch(Zend_Controller_Request_Abstract $request)
    {
        // Site-identity structured data (Organization + WebSite + SiteNavigationElement) — the same for
        // every page, emitted once (the service latches). Independent of whether this is a CMS page, so
        // it rides every public render; non-public layouts simply don't output the placeholder.
        if (class_exists('Seo_Service_Schema')) {
            Seo_Service_Schema::emitSite($request);
        }

        $pageId = (string) $request->getParam('cms_page_id', '');
        if ($pageId === '') {
            return;   // not a CMS page dispatch — no per-page head to build
        }
        try {
            $page = (new Tiger_Model_Page())->findById($pageId);
            if ($page) {
                Seo_Service_Head::forRow($page, $request);
                if (class_exists('Seo_Service_Schema')) {
                    Seo_Service_Schema::emitPageBreadcrumb($request, (string) $page->title);
                }
            }
        } catch (Throwable $e) {
            // fail-open — a broken SEO lookup must never take down a page render
        }
    }

    /**
     * Fill the site-level head baseline for any page that didn't set its own (fills BLANKS only, so a
     * CMS page's or an article's tags always win). Skipped when there's no layout — that's how a
     * non-HTML render (the /api JSON gateway, sitemap.xml, robots.txt, llms.txt) opts out.
     *
     * @param  Zend_Controller_Request_Abstract $request
     * @return void
     */
    public function postDispatch(Zend_Controller_Request_Abstract $request)
    {
        try {
            if (!class_exists('Seo_Service_Head')) {
                return;
            }
            $layout = class_exists('Zend_Layout') ? Zend_Layout::getMvcInstance() : null;
            if (!$layout || !$layout->isEnabled()) {
                return;   // no layout = not an HTML page render (JSON, XML, plain text) — nothing to head
            }
            Seo_Service_Head::site($request);
        } catch (Throwable $e) {
            // fail-open — SEO never breaks a render
        }
    }
}
