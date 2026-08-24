<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Seo_Service_Social — the /api service behind the Social Cards screen: read and author the Open
 * Graph title, description, and share image for each PUBLIC VIEW PAGE (a shipped `.phtml` marketing
 * page like /agency or /vibe, which has no CMS `page` row to carry `meta.seo`).
 *
 * **Storage is the ordinary config cascade — there is no table.** Each value is one dot-notation
 * config key, `tiger.seo.page.<page_key>.{title,description,image}`, exactly what
 * `Seo_Service_Head::pageDefaults()` reads at render time. The shipped `.ini` supplies the base and
 * this service writes `config` DB rows on top: the live-override tier, effective next request, per
 * install or per org, with no deploy (AGENTS.md "the live-override pattern").
 *
 * **Blank REMOVES the override, it never stores ''.** That distinction is the whole contract: an
 * empty string stored in `config` would win the cascade and mask the `.ini` base *and* the site-wide
 * fallback, leaving the page with a blank card. So `save()` calls `Tiger_Model_Config::forget()` for
 * an emptied field (a soft-delete that drops the row out of the cascade; a later `set()` revives it)
 * and the tier below — the page's own `<title>`, `tiger.site.description`, `tiger.seo.og_image` —
 * applies again.
 *
 * Because it's a real `/api` service, this surface is reachable by the in-platform AI agent and over
 * MCP with no extra work: the agent's tools ARE the role-filtered `/api` catalog, so `seo/social/pages`
 * and `seo/social/save` become tools gated by the same admin ACL rule a human passes.
 *
 * @api
 * @see Seo_Service_Head::pageDefaults  the reader — the render-time other half of these keys
 * @see Seo_Service_Pages               which page keys exist (the write allow-list)
 */
class Seo_Service_Social extends Tiger_Service_Service
{
    /** The three authorable fields, in display order — each one config key under the page's node. */
    const FIELDS = ['title', 'description', 'image'];

    /** The config key prefix every page value hangs off. */
    const KEY_PREFIX = 'tiger.seo.page.';

    /**
     * List every editable public view page with its authored social-card values and the site-wide
     * fallbacks those values override.
     *
     * Each page carries `title`/`description`/`image` (the authored values, '' when unset), the
     * resolved `image_url` for a preview, and `has_*` flags so a grid can show at a glance which
     * pages are still riding the defaults.
     *
     * @param  array $params the /api message (no arguments are read)
     * @return void
     */
    public function pages(array $params): void
    {
        if (!$this->_isAdmin()) {
            $this->_error('core.api.error.not_allowed');
            return;
        }

        try {
            $pages = [];
            foreach (Seo_Service_Pages::discover() as $page) {
                $values = [];
                foreach (self::FIELDS as $field) {
                    $values[$field]           = self::_config(self::KEY_PREFIX . $page['key'] . '.' . $field);
                    $values['has_' . $field]  = $values[$field] !== '';
                }
                $values['image_url'] = self::_imageUrl($values['image']);
                $pages[] = [
                    'key'    => $page['key'],
                    'url'    => $page['url'],
                    'source' => $page['source'],
                ] + $values;
            }

            $this->_success(['pages' => $pages, 'defaults' => self::_defaults()]);
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Save one page's social card. A blank field REMOVES its override so the fallback applies again.
     *
     * The share image accepts either shape `Seo_Service_Head` resolves: `image_media_id` (a Media
     * Library id — preferred, because the media row yields a real absolute URL plus true pixel
     * dimensions) or `image_url` (an absolute http(s) URL). The media id wins when both are given.
     *
     * @param  array $params page_key, title, description, image_url, image_media_id
     * @apiRequest Seo_Form_Page
     * @return void
     */
    public function save(array $params): void
    {
        if (!$this->_isAdmin()) {
            $this->_error('core.api.error.not_allowed');
            return;
        }

        $form = new Seo_Form_Page();
        if (!$form->isValid($params)) {
            $this->_formErrors($form);
            return;
        }
        $v = $form->getValues();

        // The page key becomes a config-key segment, so it is checked against the DISCOVERED pages —
        // never trusted from the request. A caller can only author cards for pages that really exist.
        $pageKey = Seo_Service_Pages::key($v['page_key'] ?? '');
        if (!Seo_Service_Pages::exists($pageKey)) {
            $this->_error('seo.page.error.unknown_page');
            return;
        }

        // One `image` value, two accepted shapes. The media id wins — Seo_Service_Head resolves it
        // through the `media` row to a real absolute URL plus true pixel dimensions, which is what
        // makes the card lay out correctly. `_mediaId()` re-checks the shape the form already
        // validated: this value becomes a stored config value, so it's belt AND braces.
        $image = self::_mediaId($v['image_media_id'] ?? '');
        if ($image === '') {
            $image = trim((string) ($v['image_url'] ?? ''));
        }

        $values = [
            'title'       => trim((string) ($v['title'] ?? '')),
            'description' => trim((string) ($v['description'] ?? '')),
            'image'       => $image,
        ];

        try {
            $this->_transaction(function () use ($pageKey, $values) {
                $cfg = new Tiger_Model_Config();
                [$scope, $scopeId] = $this->_scope();
                foreach (self::FIELDS as $field) {
                    $key = self::KEY_PREFIX . $pageKey . '.' . $field;
                    if ($values[$field] === '') {
                        // Blank = "use the fallback": drop the override instead of masking it with ''.
                        $cfg->forget($scope, $scopeId, $key);
                    } else {
                        $cfg->set($scope, $scopeId, $key, $values[$field]);
                    }
                }
            });
            $this->_success(['page_key' => $pageKey] + $values, 'seo.page.saved');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * The config scope these writes land in. GLOBAL today — a public view page is the single site's
     * page, and anonymous requests only receive GLOBAL config by design. Kept in one place so a
     * future multi-site module can flip the whole screen to per-org scoping in one edit, exactly as
     * Identity_Service_Identity does.
     *
     * @return array [scope, scopeId]
     */
    protected function _scope()
    {
        return [Tiger_Model_Config::SCOPE_GLOBAL, ''];
    }

    // -- internals -----------------------------------------------------------------------------------

    /** The site-wide last-resort tier a blank page field falls through to (Seo_Service_Head::site). */
    private static function _defaults()
    {
        $image = self::_config('tiger.seo.og_image');
        return [
            'site_name'        => self::_config('tiger.site.name'),
            'site_description' => self::_config('tiger.site.description'),
            'og_image'         => $image,
            'og_image_url'     => self::_imageUrl($image),
        ];
    }

    /** Read a dot-notation config value from the live cascade; '' when unset. */
    private static function _config($dotKey)
    {
        if (!Zend_Registry::isRegistered('Zend_Config')) {
            return '';
        }
        $node = Zend_Registry::get('Zend_Config');
        foreach (explode('.', (string) $dotKey) as $seg) {
            if (!($node instanceof Zend_Config)) { return ''; }
            $node = $node->get($seg);
            if ($node === null) { return ''; }
        }
        return is_scalar($node) ? trim((string) $node) : '';
    }

    /**
     * A displayable URL for an image reference — an absolute URL passes through, a media id is
     * resolved through the `media` row. '' when unresolvable (the screen just shows no preview).
     */
    private static function _imageUrl($ref)
    {
        $ref = trim((string) $ref);
        if ($ref === '' || preg_match('#^https?://#i', $ref)) {
            return $ref;
        }
        try {
            if (!class_exists('Tiger_Model_Media')) { return ''; }
            $model = new Tiger_Model_Media();
            $row   = $model->findById($ref);
            return $row ? (string) $model->url($row->toArray()) : '';
        } catch (Throwable $e) {
            return '';                                  // fail-soft — a missing preview is never an error
        }
    }

    /** A value that looks like a media UUID, else '' (so the URL escape hatch is used instead). */
    private static function _mediaId($v)
    {
        $v = trim((string) $v);
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $v) ? $v : '';
    }
}
