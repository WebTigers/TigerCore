<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Identity_Plugin_Favicon — contributes the site favicon to the head registry. The favicon is core
 * site identity (config `tiger.site.favicon`, a media id), not SEO, but like the SEO head tags it
 * rides TigerZF's headLink registry so the layout renders it with no theme edit. A single high-res
 * square source is emitted as both `rel="icon"` (browsers downscale it for every tab size) and
 * `rel="apple-touch-icon"` (iOS) — the modern, derivative-free approach. When Site Identity sets no
 * favicon, a baked-in Tiger paw default (DEFAULT_FAVICON — a puma base-theme asset whose `/_theme`
 * symlink is always present for both admin and public) is emitted, so EVERY page has a favicon out of
 * the box; a configured Site Identity favicon overrides it. Fail-open: any error emits nothing.
 */
class Identity_Plugin_Favicon extends Zend_Controller_Plugin_Abstract
{
    /** The stock Tiger paw, shipped in tiger-core (themes/puma/assets/img) + served at the always-present /_theme base. */
    const DEFAULT_FAVICON = '/_theme/img/tiger-favicon.png';

    /** Emit-once latch — the favicon is the same on every dispatch (incl. forwards). */
    private static $_done = false;

    /**
     * @param  Zend_Controller_Request_Abstract $request
     * @return void
     */
    public function preDispatch(Zend_Controller_Request_Abstract $request)
    {
        if (self::$_done) {
            return;
        }
        self::$_done = true;

        try {
            // A configured Site Identity favicon wins; otherwise fall back to the baked-in Tiger paw so
            // every page (public / admin / auth) has a favicon by default.
            $id  = self::_config('site.favicon');
            $url = ($id !== '') ? self::_mediaUrl($id, $request) : '';
            if ($url === '') {
                $url = self::DEFAULT_FAVICON;
            }
            $view = self::_view();
            $view->headLink(['rel' => 'icon', 'href' => $url]);
            $view->headLink(['rel' => 'apple-touch-icon', 'href' => $url]);
        } catch (Throwable $e) {
            // fail-open — the favicon must never break a request
        }
    }

    /** Resolve a media id to an absolute (or root-relative) URL, or '' when unresolvable. */
    private static function _mediaUrl($id, $request)
    {
        if (!class_exists('Tiger_Model_Media')) {
            return '';
        }
        $model = new Tiger_Model_Media();
        $row   = $model->findById($id);
        if (!$row) {
            return '';
        }
        $url = (string) $model->url($row->toArray());
        if ($url !== '' && !preg_match('#^https?://#i', $url) && strpos($url, '/') !== 0) {
            $url = '/' . ltrim($url, '/');   // keep it root-relative if the adapter returned a bare path
        }
        return $url;
    }

    /** A Zend_View to reach the head helpers (shares the process-wide placeholder registry). */
    private static function _view()
    {
        if (Zend_Registry::isRegistered('Zend_View')) {
            $v = Zend_Registry::get('Zend_View');
            if ($v instanceof Zend_View_Interface) {
                return $v;
            }
        }
        return new Zend_View();
    }

    /** Read a `tiger.<dotKey>` config value with a default. */
    private static function _config($dotKey, $default = '')
    {
        if (!Zend_Registry::isRegistered('Zend_Config')) {
            return $default;
        }
        $node = Zend_Registry::get('Zend_Config')->get('tiger');
        foreach (explode('.', $dotKey) as $seg) {
            if (!($node instanceof Zend_Config)) { return $default; }
            $node = $node->get($seg);
            if ($node === null) { return $default; }
        }
        return is_scalar($node) ? (string) $node : $default;
    }
}
