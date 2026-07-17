<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Seo_RobotsController — serves /robots.txt (a route, never a docroot file — same real-file-first cPanel
 * hazard as the sitemap). Disallows the non-public app paths and points crawlers at /sitemap.xml. The
 * disallow list is config (tiger.seo.robots.disallow[], live-override, per-org) with sane defaults.
 * Public (guest ACL). Route declared in Seo_Bootstrap via Tiger_Routing_Overrides.
 */
class Seo_RobotsController extends Zend_Controller_Action
{
    /** The app paths a crawler has no business in, when the operator hasn't configured their own list. */
    const DEFAULT_DISALLOW = ['/admin', '/auth', '/api', '/system', '/access'];

    public function init()
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        $this->getResponse()->setHeader('Content-Type', 'text/plain; charset=UTF-8', true);
        @ini_set('display_errors', '0');
    }

    public function txtAction()
    {
        $request = $this->getRequest();
        $base    = $request->getScheme() . '://' . $request->getHttpHost();

        $lines   = ['User-agent: *'];
        foreach (self::_disallow() as $path) {
            $lines[] = 'Disallow: ' . $path;
        }
        $lines[] = '';
        $lines[] = 'Sitemap: ' . $base . '/sitemap.xml';

        $this->getResponse()->setBody(implode("\n", $lines) . "\n");
    }

    /** The disallow list: tiger.seo.robots.disallow (scalar or array) if set, else the defaults. */
    private static function _disallow()
    {
        if (Zend_Registry::isRegistered('Zend_Config')) {
            $t = Zend_Registry::get('Zend_Config')->get('tiger');
            $s = $t ? $t->get('seo') : null;
            $r = $s ? $s->get('robots') : null;
            $d = $r ? $r->get('disallow') : null;
            if ($d instanceof Zend_Config) { $d = $d->toArray(); }
            if (is_array($d) && $d !== []) {
                return array_values(array_filter(array_map('strval', $d), static function ($p) { return $p !== ''; }));
            }
            if (is_string($d) && $d !== '') {
                return [$d];
            }
        }
        return self::DEFAULT_DISALLOW;
    }
}
