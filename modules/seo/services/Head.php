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

    /**
     * Fill the SITE-level head baseline — the last-resort tier, so every public page has a usable social
     * card even when it isn't a CMS row. Only ever fills BLANKS: whatever a page already set (via forRow,
     * a blog article, or a view) always wins, so this can run late without clobbering anything.
     *
     * Why this exists: `forRow()` is reached only for CMS pages (Seo_Plugin_Head) and blog articles. The
     * SHIPPED marketing pages (/vibe, /agency, …) are plain controller actions with no `page` row, so
     * nothing emitted og:* for them at all — and a crawler with no og:image falls back to scraping the
     * DOM, which on a Tiger page means the first `<img>` it finds (the language switcher's flag). Emitting
     * the site defaults makes that impossible.
     *
     * Runs at postDispatch (the view has rendered, so the page title is known; the layout has NOT, so the
     * head registry is still open).
     *
     * Two tiers feed it, both filling blanks only, page-level first:
     *   1. PAGE — `tiger.seo.page.<key>.{title,description,image}` for a public VIEW page (a .phtml
     *      action with no `page` row: /agency, /vibe, …). Base values ship in an `.ini`; the `config`
     *      DB tier overrides them live per install/org — the standard live-override cascade, so this
     *      needs no storage of its own. See pageKey().
     *   2. SITE — `tiger.site.{name,description}` + `tiger.seo.og_image`, the last resort.
     *
     * @param  Zend_Controller_Request_Abstract|null $request the current request (canonical/absolute URLs)
     * @return void
     */
    public static function site(?Zend_Controller_Request_Abstract $request = null)
    {
        $view = self::_view();
        if (!$view) {
            return;
        }
        $meta     = $view->headMeta();
        $siteName = trim((string) self::_config('site.name', ''));
        $page     = $request ? self::pageDefaults(self::pageKey($request)) : [];

        // Title: whatever the page settled on (headTitle, else the controller's `$this->title`), else the
        // site name. NOTE the `title` property lives on the RENDERED view (the ViewRenderer's instance) —
        // only the placeholder containers are process-wide, so read that view, not the registry one.
        $title = trim((string) ($page['title'] ?? ''));
        if ($title === '') {
            foreach ($view->headTitle() as $t) {
                $t = trim((string) $t);
                if ($t !== '') { $title = $t; }
            }
        }
        if ($title === '') {
            $rendered = self::_renderedView();
            if ($rendered) { $title = trim((string) ($rendered->title ?? '')); }
        }
        if ($title === '') { $title = $siteName; }

        // Description: the page's own meta description, else this page's configured one, else the site's.
        $desc = self::_metaContent($meta, 'name', 'description');
        if ($desc === '') {
            $desc = trim((string) ($page['description'] ?? ''));
            if ($desc === '') { $desc = trim((string) self::_config('site.description', '')); }
            if ($desc !== '') { $meta->setName('description', $desc); }
        }

        if ($title !== '' && !self::_metaHas($meta, 'property', 'og:title')) {
            $meta->setProperty('og:title', $title);
        }
        if ($desc !== '' && !self::_metaHas($meta, 'property', 'og:description')) {
            $meta->setProperty('og:description', $desc);
        }
        if (!self::_metaHas($meta, 'property', 'og:type')) {
            $meta->setProperty('og:type', 'website');
        }
        if ($siteName !== '' && !self::_metaHas($meta, 'property', 'og:site_name')) {
            $meta->setProperty('og:site_name', $siteName);
        }
        if (!self::_metaHas($meta, 'property', 'og:url') && $request) {
            $url = self::_currentUrl($request);
            if ($url !== '') { $meta->setProperty('og:url', $url); }
        }

        // og:image — this page's configured image, else the site-wide default (tiger.seo.og_image).
        // Either may be a media id (resolved to a real URL + true dimensions) or an absolute URL.
        $hasImage = self::_metaHas($meta, 'property', 'og:image');
        if (!$hasImage) {
            $ref = trim((string) ($page['image'] ?? ''));
            if ($ref === '') { $ref = (string) self::_config('seo.og_image', ''); }
            $img = self::_image($ref, $request);
            if ($img && $img['url'] !== '') {
                $meta->setProperty('og:image', $img['url']);
                if (!empty($img['width']))  { $meta->setProperty('og:image:width',  (string) $img['width']); }
                if (!empty($img['height'])) { $meta->setProperty('og:image:height', (string) $img['height']); }
                if (!empty($img['mime']))   { $meta->setProperty('og:image:type',   (string) $img['mime']); }
                if (!empty($img['alt']))    { $meta->setProperty('og:image:alt',    (string) $img['alt']); }
                $hasImage = true;
            }
        }
        if (!self::_metaHas($meta, 'name', 'twitter:card')) {
            $meta->setName('twitter:card', $hasImage ? 'summary_large_image' : 'summary');
        }
    }

    /**
     * The stable config key for a public VIEW page — the addressable identity of a page that has no
     * `page` row (a shipped .phtml action like /agency or /vibe), so its OG can still be authored.
     *
     * Shape: the dispatch triple, collapsed for the common case so the key reads like the URL —
     *   default/index/agency  ->  "agency"          (every shipped marketing page)
     *   blog/index/view       ->  "blog-index-view"
     * Segments are sanitised to [a-z0-9-] so the key is always a safe config segment.
     *
     * @param  Zend_Controller_Request_Abstract $request the dispatched request
     * @return string                                   the key, or '' when it can't be derived
     */
    public static function pageKey(Zend_Controller_Request_Abstract $request)
    {
        if (!method_exists($request, 'getActionName')) {
            return '';
        }
        $module     = self::_keySegment((string) $request->getModuleName());
        $controller = self::_keySegment((string) $request->getControllerName());
        $action     = self::_keySegment((string) $request->getActionName());
        if ($action === '') {
            return '';
        }
        if (($module === '' || $module === 'default') && ($controller === '' || $controller === 'index')) {
            return $action;   // the shipped marketing pages — key reads like the URL
        }
        return trim(($module !== '' ? $module . '-' : '') . ($controller !== '' ? $controller . '-' : '') . $action, '-');
    }

    /**
     * The authored OG defaults for a page key — `tiger.seo.page.<key>.{title,description,image}`.
     *
     * Storage is the ordinary config cascade, so there is nothing new to persist: an `.ini` supplies the
     * shipped base and a `config` DB row overrides it live (per install or per org), no deploy. `image`
     * is a media id or an absolute URL. Returns only the keys that are actually set.
     *
     * @param  string $key the page key (see pageKey())
     * @return array<string,string> the authored values: title, description, image
     */
    public static function pageDefaults($key)
    {
        $key = self::_keySegment((string) $key);
        if ($key === '') {
            return [];
        }
        $out = [];
        foreach (['title', 'description', 'image'] as $field) {
            $v = trim((string) self::_config('seo.page.' . $key . '.' . $field, ''));
            if ($v !== '') { $out[$field] = $v; }
        }
        return $out;
    }

    // -- internals -----------------------------------------------------------------------------------

    /** Normalise one config-key segment: lowercase, [a-z0-9-] only ('' when nothing survives). */
    private static function _keySegment($seg)
    {
        $seg = strtolower(trim((string) $seg));
        $seg = preg_replace('/[^a-z0-9-]+/', '-', $seg);
        return trim((string) $seg, '-');
    }

    /**
     * Is a head-meta entry of this type already present? ($type: 'name' | 'property' | 'http-equiv').
     * The container holds stdClass items with ->type/->name/->content.
     */
    private static function _metaHas($meta, $type, $key)
    {
        return self::_metaContent($meta, $type, $key) !== '' || self::_metaFind($meta, $type, $key) !== null;
    }

    /** The content of an existing head-meta entry, or '' when absent. */
    private static function _metaContent($meta, $type, $key)
    {
        $item = self::_metaFind($meta, $type, $key);
        return $item ? trim((string) ($item->content ?? '')) : '';
    }

    /**
     * Locate a head-meta item by type + key; null when absent. Fail-soft on any odd container.
     *
     * NOTE the container shape: Zend_View_Helper_HeadMeta::createData() stores the key under a property
     * NAMED BY THE TYPE — a property meta is {type:'property', property:'og:title'}, a name meta is
     * {type:'name', name:'description'}. So the lookup is `$item->{$item->type}`, never a fixed ->name.
     */
    private static function _metaFind($meta, $type, $key)
    {
        try {
            foreach ($meta as $item) {
                if (!isset($item->type) || (string) $item->type !== $type) {
                    continue;
                }
                $prop = (string) $item->type;
                if (isset($item->$prop) && (string) $item->$prop === $key) {
                    return $item;
                }
            }
        } catch (Throwable $e) {
            // fail-open — never break a render over head introspection
        }
        return null;
    }

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

    /**
     * The view the ACTION actually rendered into (the ViewRenderer's instance) — the only one carrying
     * per-request view properties like `title`. Distinct from _view(), which is only good for the
     * process-wide placeholder containers. Null when there's no ViewRenderer (CLI, tests).
     */
    private static function _renderedView()
    {
        try {
            if (!class_exists('Zend_Controller_Action_HelperBroker')) {
                return null;
            }
            if (!Zend_Controller_Action_HelperBroker::hasHelper('viewRenderer')) {
                return null;
            }
            $vr = Zend_Controller_Action_HelperBroker::getExistingHelper('viewRenderer');
            return ($vr && $vr->view instanceof Zend_View_Interface) ? $vr->view : null;
        } catch (Throwable $e) {
            return null;
        }
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
