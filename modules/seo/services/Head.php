<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Seo_Service_Head — the resolver that maps a page row's `meta.seo` onto the shared head registry
 * (TigerZF's headTitle/headMeta/headLink placeholder containers), which the layout renders.
 *
 * The single seam TigerSEO uses to contribute to the <head>: it never renders markup itself and never
 * touches the theme — it appends typed entries and the layout prints them. Reached two ways for the two
 * content paths: Seo_Plugin_Head for CMS pages (dispatched via PageDispatch → cms_page_id), and a direct,
 * class_exists-guarded call from the blog article controller (which has its own dispatch). Emits title /
 * description / robots / canonical, plus Open Graph + Twitter — og:image is resolved from a media id (the
 * blog's hero, or a per-page/per-org custom image) to a real absolute URL + true dimensions. Internal.
 */
class Seo_Service_Head
{
    /**
     * Populate the head containers from a page row's SEO metadata. Fail-soft — SEO never breaks a render.
     *
     * @param  mixed                            $page      a `page`/`post` row (Zend_Db_Table_Row) with a JSON `meta`
     * @param  Zend_Controller_Request_Abstract $request   the current request (for a self-referencing canonical)
     * @param  array                            $overrides caller fallbacks that fill BLANKS only (e.g. a blog
     *                                                     article's excerpt → description); an author-set value wins
     * @return void
     */
    public static function forRow($page, ?Zend_Controller_Request_Abstract $request = null, array $overrides = [])
    {
        if (!$page) {
            return;
        }
        $meta = self::_meta($page);
        $seo  = (isset($meta['seo']) && is_array($meta['seo'])) ? $meta['seo'] : [];
        foreach ($overrides as $k => $v) {
            if ($v !== null && $v !== '' && empty($seo[$k])) { $seo[$k] = $v; }
        }
        $view = self::_view();
        if (!$view) {
            return;
        }

        // Title — an author-set SEO title overrides the page title the layout would otherwise seed.
        $title = trim((string) ($seo['title'] ?? ''));
        if ($title !== '') {
            $view->headTitle()->set($title);
        }

        // Meta description.
        $desc = trim((string) ($seo['description'] ?? ''));
        if ($desc !== '') {
            $view->headMeta()->setName('description', $desc);
        }

        // Robots — the absence of the tag means index,follow; emit a directive ONLY when restricted.
        $robots = self::_robots($seo);
        if ($robots !== '') {
            $view->headMeta()->setName('robots', $robots);
        }

        // Canonical — explicit if the author set one, else self-referencing (clean path, no query).
        $canonical = trim((string) ($seo['canonical'] ?? ''));
        if ($canonical === '' && $request) {
            $canonical = self::_currentUrl($request);
        }
        if ($canonical !== '') {
            $view->headLink(['rel' => 'canonical', 'href' => $canonical]);
        }

        // --- Open Graph + Twitter -------------------------------------------------------------------
        $meta      = $view->headMeta();
        $isArticle = ((string) ($page->type ?? '') === 'article');
        $ogTitle   = $title !== '' ? $title : trim((string) ($page->title ?? ''));

        if ($ogTitle !== '') { $meta->setProperty('og:title', $ogTitle); }
        if ($desc !== '')    { $meta->setProperty('og:description', $desc); }
        $meta->setProperty('og:type', $isArticle ? 'article' : 'website');
        $ogUrl = $canonical !== '' ? $canonical : ($request ? self::_currentUrl($request) : '');
        if ($ogUrl !== '') { $meta->setProperty('og:url', $ogUrl); }
        $siteName = trim((string) self::_config('site.name', ''));
        if ($siteName !== '') { $meta->setProperty('og:site_name', $siteName); }

        // og:image — the page's own image (seo.og_image_id; the blog folds its hero/feature image into
        // that via the $overrides seam), else the site-wide fallback (tiger.seo.og_image, per-org config).
        // Resolved through the media row for a real absolute URL + true dimensions (better card layout).
        $img = self::_image((string) ($seo['og_image_id'] ?? ''), $request);
        if (!$img) {
            $img = self::_image((string) self::_config('seo.og_image', ''), $request);
        }
        if ($img && $img['url'] !== '') {
            $meta->setProperty('og:image', $img['url']);
            if (!empty($img['width']))  { $meta->setProperty('og:image:width',  (string) $img['width']); }
            if (!empty($img['height'])) { $meta->setProperty('og:image:height', (string) $img['height']); }
            if (!empty($img['mime']))   { $meta->setProperty('og:image:type',   (string) $img['mime']); }
            if (!empty($img['alt']))    { $meta->setProperty('og:image:alt',    (string) $img['alt']); }
        }

        if ($isArticle) {
            $pub = self::_iso8601((string) ($page->published_at ?? ''));
            $mod = self::_iso8601((string) ($page->updated_at ?? ''));
            if ($pub !== '') { $meta->setProperty('article:published_time', $pub); }
            if ($mod !== '') { $meta->setProperty('article:modified_time', $mod); }
        }

        // Twitter — just the card kind; Twitter reads the og:* tags for title/description/image/url, so
        // there's nothing to duplicate. A resolved image earns the large-image card.
        $meta->setName('twitter:card', ($img && $img['url'] !== '') ? 'summary_large_image' : 'summary');
    }

    // -- internals -----------------------------------------------------------------------------------

    /** Decode a row's JSON `meta` to an array (tolerates an already-decoded array). */
    private static function _meta($page)
    {
        $raw = $page->meta ?? null;
        if (is_array($raw)) {
            return $raw;
        }
        $decoded = $raw ? json_decode((string) $raw, true) : null;
        return is_array($decoded) ? $decoded : [];
    }

    /** Build the robots content from `seo.robots.{index,follow}`; '' means the default (index,follow). */
    private static function _robots(array $seo)
    {
        $r = (isset($seo['robots']) && is_array($seo['robots'])) ? $seo['robots'] : [];
        $parts = [];
        if (array_key_exists('index', $r) && !$r['index'])   { $parts[] = 'noindex'; }
        if (array_key_exists('follow', $r) && !$r['follow']) { $parts[] = 'nofollow'; }
        return implode(', ', $parts);
    }

    /** The current request's absolute URL, path only (a stable self-referencing canonical). */
    private static function _currentUrl(Zend_Controller_Request_Abstract $request)
    {
        if (!method_exists($request, 'getScheme')) {
            return '';
        }
        $path = (string) parse_url((string) $request->getRequestUri(), PHP_URL_PATH);
        return $request->getScheme() . '://' . $request->getHttpHost() . ($path !== '' ? $path : '/');
    }

    /** A Zend_View to reach the head helpers. Any instance shares the process-wide placeholder registry. */
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

    /** Read a `tiger.<dotKey>` config value (org-cascaded, live) with a default. */
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

    /**
     * Resolve an OG image reference to ['url','width','height','mime','alt']. A ref is a `media_id`
     * (looked up in `media` for a real absolute URL + true pixel dimensions) or an already-absolute URL
     * (used as-is, no dimensions). Null when unresolvable — the tag is simply omitted (fail-soft).
     */
    private static function _image($ref, $request)
    {
        $ref = trim((string) $ref);
        if ($ref === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $ref)) {
            return ['url' => $ref, 'width' => null, 'height' => null, 'mime' => null, 'alt' => null];
        }
        try {
            if (!class_exists('Tiger_Model_Media')) { return null; }
            $model = new Tiger_Model_Media();
            $row   = $model->findById($ref);
            if (!$row) { return null; }
            $arr = $row->toArray();
            $url = (string) $model->url($arr);
            if ($url === '') { return null; }
            if (!preg_match('#^https?://#i', $url) && $request && method_exists($request, 'getScheme')) {
                $url = $request->getScheme() . '://' . $request->getHttpHost() . '/' . ltrim($url, '/');
            }
            return [
                'url'    => $url,
                'width'  => $arr['width'] ?? null,
                'height' => $arr['height'] ?? null,
                'mime'   => $arr['mime_type'] ?? null,
                'alt'    => $arr['alt_text'] ?? ($arr['title'] ?? null),
            ];
        } catch (Throwable $e) {
            return null;
        }
    }

    /** A DB DATETIME ('Y-m-d H:i:s') as an ISO-8601 string for og:article times; '' if unparseable. */
    private static function _iso8601($datetime)
    {
        $datetime = trim((string) $datetime);
        if ($datetime === '') {
            return '';
        }
        $ts = strtotime($datetime);
        return $ts !== false ? date('c', $ts) : '';
    }
}
