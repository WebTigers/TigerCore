<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Cms_Service_Page — the /api service for CMS authoring (save / delete / restore).
 *
 * The only write path for the CMS admin: the editor POSTs here (module=cms,
 * service=page, method=save|delete|restore). Each action gates on admin+ via the
 * ACL, validates where a form applies, then defers the actual write to the
 * transactional Tiger_Model_Page — save() snapshots a version and leaves a 301 on
 * a slug change; restoreVersion() writes a prior version back as a new one.
 *
 * ACL resource = this class name (Cms_Service_Page), granted admin+ in
 * modules/cms/configs/acl.ini; the gateway also privilege-checks the method name.
 *
 * @api
 */
class Cms_Service_Page extends Tiger_Service_Service
{
    /**
     * DataTables server-side source for the content list. Reads the DT params
     * (search/sort/paginate), counts total + filtered, and returns one page of rows
     * as STRUCTURED DATA (no HTML). Each row carries the caller's control permissions
     * (`can_edit`/`can_delete`, privilege-checked) so the client renders ACL-correct
     * action buttons — authorization stays server-side. See AGENTS.md (client/server).
     *
     * @param  array $params the DataTables request payload (search/sort/paginate + toolbar filters)
     * @return void
     */
    public function datatable(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $dt = $this->_dtParams($params);

        // The query lives in the model; the service validates the toolbar filters and
        // formats + ACL-gates the rows. Toolbar filters define the working set (both
        // counts); the search box narrows recordsFiltered.
        $data = (new Tiger_Model_Page())->datatable([
            'search'   => $dt['search'],
            'status'   => in_array(($params['status'] ?? ''), [Tiger_Model_Page::STATUS_DRAFT, Tiger_Model_Page::STATUS_PUBLISHED, Tiger_Model_Page::STATUS_ARCHIVED], true) ? (string) $params['status'] : '',
            'type'     => in_array(($params['type']   ?? ''), [Tiger_Model_Page::TYPE_PAGE, Tiger_Model_Page::TYPE_LAYOUT, Tiger_Model_Page::TYPE_PARTIAL, Tiger_Model_Page::TYPE_BLOCK], true) ? (string) $params['type'] : '',
            'orderCol' => isset($dt['order'][0]) ? $dt['order'][0]['column'] : -1,
            'orderDir' => isset($dt['order'][0]) ? $dt['order'][0]['dir'] : '',
            'offset'   => $dt['start'],
            'limit'    => $dt['length'],
        ]);

        // Server-authoritative control permissions (per-caller, privilege-checked so
        // tightening save/delete in the ACL flows straight through to the buttons).
        $canEdit   = $this->_isAdmin(static::class, 'save');
        $canDelete = $this->_isAdmin(static::class, 'delete');

        $rows = [];
        foreach ($data['rows'] as $r) {
            $stamp = (string) ($r['updated_at'] ?: $r['created_at']);
            $rows[] = [
                'page_id'    => $r['page_id'],
                'title'      => ($r['title'] !== null && $r['title'] !== '') ? $r['title'] : '(untitled)',
                'type'       => $r['type'],
                'handle'     => ($r['slug'] !== null && $r['slug'] !== '') ? '/' . $r['slug'] : '#' . $r['page_key'],
                'locale'     => $r['locale'],
                'status'     => $r['status'],
                'scheduled'  => ($r['status'] === 'published' && $r['published_at'] && strtotime((string) $r['published_at']) > time()),
                'updated'    => substr($stamp, 0, 16),
                'can_edit'   => $canEdit,
                'can_delete' => $canDelete,
            ];
        }

        $this->_dtResponse($dt['draw'], $data['total'], $data['filtered'], $rows);
    }

    /**
     * DataTables server-side source for the "Theme Templates" tab — the active theme's forkable
     * pages/layouts/partials (served from files). A large theme (Porto ~830) makes this a real dataset,
     * so it paginates/searches/sorts server-side like the content list. Rows are file-derived (not a SQL
     * query): scan once (Tiger_Theme::forkables), flag each with its customization id, then filter/sort/
     * slice in PHP. Each row carries `can_edit` so the client renders ACL-correct controls.
     *
     * @param  array $params the DataTables request payload
     * @return void
     */
    public function themeTemplates(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $dt = $this->_dtParams($params);

        // Which theme templates are already forked — matched PRECISELY by origin (theme|kind|slug)
        // recorded in the fork's meta, so a same-named template can't cross-flag.
        $forked = $this->_forkedIndex();

        // Only the ACTIVE theme's templates surface — an installed-but-INACTIVE theme has no asset
        // symlink, so its content can't render and must not be forkable (AUTHORING.md §3.3).
        $active = Tiger_Theme::active();
        $rows   = [];
        foreach (Tiger_Theme::forkables() as $t) {
            $isPage = ($t['kind'] === 'page');
            $pageId = $forked[$active['key'] . '|' . $t['kind'] . '|' . $t['slug']] ?? '';
            $rows[] = [
                'kind'       => $t['kind'],
                'theme'      => $active['name'],
                'theme_key'  => $active['key'],
                'title'      => ($t['title'] !== '' ? $t['title'] : $t['slug']),
                'slug'       => $t['slug'],                                   // raw handle for the fork call
                'handle'     => $isPage ? '/' . $t['slug'] : '#' . $this->_slugify($t['slug']),
                'customized' => $pageId !== '',
                'page_id'    => $pageId,
            ];
        }

        $total = count($rows);

        // Search across title/slug/kind/theme.
        $search = strtolower(trim((string) $dt['search']));
        if ($search !== '') {
            $rows = array_values(array_filter($rows, static function ($r) use ($search) {
                return strpos(strtolower($r['title'] . ' ' . $r['slug'] . ' ' . $r['kind'] . ' ' . $r['theme']), $search) !== false;
            }));
        }
        $filtered = count($rows);

        // Sort by the ordered column, title as the tiebreak.
        $cols  = [0 => 'kind', 1 => 'theme', 2 => 'title', 3 => 'handle', 4 => 'customized'];
        $field = $cols[(int) ($dt['order'][0]['column'] ?? 1)] ?? 'theme';
        $dir   = (strtolower((string) ($dt['order'][0]['dir'] ?? 'asc')) === 'desc') ? -1 : 1;
        $val   = static function ($r) use ($field) {
            return $field === 'customized' ? ($r['customized'] ? '1' : '0') : (string) $r[$field];
        };
        usort($rows, static function ($a, $b) use ($val, $dir) {
            $c = strcmp($val($a), $val($b));
            if ($c === 0) { $c = strcasecmp((string) $a['title'], (string) $b['title']); }
            return $c * $dir;
        });

        // Paginate.
        $page = array_slice($rows, (int) $dt['start'], ((int) $dt['length'] > 0) ? (int) $dt['length'] : null);

        $canEdit = $this->_isAdmin(static::class, 'save');
        foreach ($page as &$r) { $r['can_edit'] = $canEdit; }
        unset($r);

        $this->_dtResponse($dt['draw'], $total, $filtered, $page);
    }

    /** [theme_key|kind|slug => page_id] for theme-forked rows, from each fork's origin recorded in meta. */
    protected function _forkedIndex(): array
    {
        $pm  = new Tiger_Model_Page();
        $out = [];
        foreach ($pm->fetchAll($pm->activeSelect()->where('type IN (?)', [
            Tiger_Model_Page::TYPE_PAGE, Tiger_Model_Page::TYPE_LAYOUT, Tiger_Model_Page::TYPE_PARTIAL,
        ])) as $r) {
            if (empty($r->meta)) { continue; }
            $meta = is_array($r->meta) ? $r->meta : json_decode((string) $r->meta, true);
            if (!is_array($meta) || ($meta['source'] ?? '') !== 'theme') { continue; }
            $out[($meta['source_key'] ?? '') . '|' . ($meta['source_kind'] ?? '') . '|' . ($meta['source_slug'] ?? '')] = $r->page_id;
        }
        return $out;
    }

    /**
     * Fork an ACTIVE-theme template (served from a file) into an editable CMS row — a PAGE (by slug),
     * a LAYOUT, or a PARTIAL (by page_key). That row then transparently OVERRIDES the file (the
     * live-override tier); no theme file is ever modified. If a matching row already exists it's
     * returned (already customized). Origin is tagged in `meta` (source/source_key/source_kind) so we
     * can later offer "revert to theme default" + non-destructive theme updates (AUTHORING.md §4).
     *
     * @param  array $params `slug` (the theme content slug/key) + optional `kind` (page|layout|partial)
     * @return void
     */
    public function forkTheme(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $kind = (string) ($params['kind'] ?? 'page');
        if (!in_array($kind, ['page', 'layout', 'partial'], true)) { $kind = 'page'; }
        $key  = trim((string) ($params['slug'] ?? ''), '/');

        // Only the ACTIVE theme is forkable (its assets are symlinked/reachable). Refuse any other — a
        // crafted request could otherwise name an installed-but-inactive theme whose content is dark.
        $active     = Tiger_Theme::active();
        $themeParam = (string) ($params['theme'] ?? '');
        if ($themeParam !== '' && $themeParam !== $active['key']) {
            $this->_error('cms.page.theme_inactive'); return;
        }
        $dir      = $active['dir'];
        $themeKey = $active['key'];

        $tpl = Tiger_Theme::template($kind, $key, $dir ?: null);
        if (!$tpl) { $this->_error('cms.page.template_unavailable'); return; }

        $type = [
            'page'    => Tiger_Model_Page::TYPE_PAGE,
            'layout'  => Tiger_Model_Page::TYPE_LAYOUT,
            'partial' => Tiger_Model_Page::TYPE_PARTIAL,
        ][$kind];
        $isPage = ($kind === 'page');

        // Re-customizing an already-forked template just reopens it — matched by ORIGIN (theme|kind|slug),
        // so the same-named template from two themes forks independently.
        $pm     = new Tiger_Model_Page();
        $forked = $this->_forkedIndex();
        $origin = $themeKey . '|' . $kind . '|' . $key;
        if (isset($forked[$origin])) {
            $id  = $forked[$origin];
            $url = '/cms/page/edit/id/' . $id;
            $this->_success(['page_id' => $id, 'edit_url' => $url, 'kind' => $kind], 'cms.page.exists', $url);
            return;
        }

        // A body with PHP must stay phtml (trusted); pure markup forks as html so it opens cleanly in the
        // visual builder. Only a PAGE carries a layout_key; layouts/partials are chrome themselves. The
        // page_key is uniquified so two themes' same-named templates don't collide on the handle.
        $hasPhp  = (strpos($tpl['body'], '<?php') !== false) || (strpos($tpl['body'], '<?=') !== false);
        $pageKey = $this->_uniqueKey($pm, $this->_slugify($key) ?: $kind);

        // Provenance (source_*) drives "already customized" + a future "revert to theme default". For a
        // PAGE, bake the origin theme's stylesheet links into the head so it SELF-LOADS its theme's CSS
        // (reachable via that theme's own symlink) — so the fork renders correctly under ANY active theme,
        // and goes dark only if that theme is deactivated and its symlink removed.
        $meta = ['source' => 'theme', 'source_key' => $themeKey, 'source_kind' => $kind, 'source_slug' => $key];
        if ($isPage) {
            $links = '';
            foreach (Tiger_Theme::stylesheets($dir ?: null) as $href) {
                $links .= '<link rel="stylesheet" href="' . htmlspecialchars($href, ENT_QUOTES) . '">' . "\n";
            }
            if ($links !== '') { $meta['head_html'] = $links; }
        }

        $data = [
            // A customized theme page IS the public site's page, so it belongs to the SITE org (what
            // PageDispatch resolves against) — never the editing admin's org, which may differ. Locale
            // is left neutral ('') so it serves every language (resolveBySlug treats '' as a fallback).
            'org_id'       => Tiger_Model_Org::siteOrgId(),
            'type'         => $type,
            'page_key'     => $pageKey,
            'slug'         => $isPage ? $key : null,
            'locale'       => '',
            'title'        => $tpl['title'],
            'body'         => $tpl['body'],
            'format'       => $hasPhp ? Tiger_Model_Page::FORMAT_PHTML : Tiger_Model_Page::FORMAT_HTML,
            'layout_key'   => ($isPage && $tpl['layout'] !== '') ? $tpl['layout'] : null,
            'status'       => Tiger_Model_Page::STATUS_PUBLISHED,
            'published_at' => null,
            'meta'         => json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ];
        try {
            $id  = $pm->save($data, null);
            $url = '/cms/page/edit/id/' . $id;
            $this->_success(['page_id' => $id, 'edit_url' => $url, 'kind' => $kind], 'cms.page.forked', $url);
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Create or update a page (insert when page_id is empty).
     *
     * @param  array $params the editor form payload
     * @apiRequest Cms_Form_Page
     * @return void
     */
    public function save(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $form = new Cms_Form_Page();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        $v = $form->getValues();

        $pageId = !empty($params['page_id']) ? (string) $params['page_id'] : null;

        // Pages route by slug; layouts/partials have none — NULL (not '') keeps the
        // UNIQUE(org_id, slug, locale) index happy for many keyless rows. A page_key
        // is always set so every row has a stable handle: from the slug, else a
        // slugified title.
        $isPage = ($v['type'] === 'page');
        $slugIn = trim((string) $v['slug']);
        $slug   = $slugIn !== '' ? $this->_slugify($slugIn) : ($isPage ? $this->_slugify($v['title']) : null);

        $key = trim((string) $v['page_key']);
        if ($key === '') {
            $key = $this->_slugify(($slug !== null && $slug !== '') ? $slug : $v['title']);
        }

        $publishedAt = trim((string) $v['published_at']);

        $data = [
            'type'         => $v['type'],
            'page_key'     => $key,
            'slug'         => $slug,
            'locale'       => $v['locale'],
            'title'        => $v['title'],
            'body'         => (string) $v['body'],
            'format'       => $v['format'],
            'layout_key'   => trim((string) $v['layout_key']) !== '' ? $v['layout_key'] : null,
            'status'       => $v['status'],
            'published_at' => $publishedAt !== '' ? $publishedAt : null,
        ];

        // SEO + head/body injection, merged into `meta` — preserving the visual-builder project blob.
        $meta = [];
        if ($pageId) {
            $existing = (new Tiger_Model_Page())->findById($pageId);
            if ($existing && !empty($existing->meta)) {
                $decoded = is_array($existing->meta) ? $existing->meta : json_decode((string) $existing->meta, true);
                if (is_array($decoded)) { $meta = $decoded; }
            }
        }
        // SEO description lives under the unified `meta.seo` shape (same as blog articles); the flat
        // legacy `meta.description` is dropped (migration 0032 moved existing rows). head_html/body_scripts
        // stay flat — they're the raw admin-authored escape hatch, not SEO metadata.
        if (!isset($meta['seo']) || !is_array($meta['seo'])) { $meta['seo'] = []; }
        $meta['seo']['description'] = trim((string) ($v['meta_description'] ?? ''));
        unset($meta['description']);
        $meta['head_html']    = (string) ($v['head_html'] ?? '');
        $meta['body_scripts'] = (string) ($v['body_scripts'] ?? '');
        // Custom field groups (Tiger_Fields): merge posted, declared-only values into meta.fields.
        if (class_exists('Tiger_Fields') && isset($params['fields']) && is_array($params['fields'])) {
            Tiger_Fields::applyToMeta($meta, (string) $v['type'], $params['fields']);
        }
        $data['meta'] = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        try {
            // Tiger_Model_Page::save() is itself transactional (write + version snapshot).
            $id = (new Tiger_Model_Page())->save($data, $pageId);
            $this->_success(['page_id' => $id], 'cms.page.saved', '/cms/page');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Save a page's visual-builder design. The full-screen GrapesJS builder posts the
     * rendered HTML + CSS plus its lossless project JSON. We store a self-contained
     * `<style>` + markup body (format=builder, `<script>` stripped so it stays a SAFE
     * format) and keep the project JSON in `meta.builder` so reopening the canvas is
     * lossless. Page metadata (title/slug/status) is edited in the normal page editor —
     * this touches the design only, on an existing row.
     *
     * @param  array $params the builder payload (`page_id`, `html`, `css`, `project`)
     * @return void
     */
    public function saveDesign(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $pageId = trim((string) ($params['page_id'] ?? ''));
        if ($pageId === '') { $this->_error('core.api.error.general'); return; }

        $model = new Tiger_Model_Page();
        $page  = $model->findById($pageId);
        if (!$page) { $this->_error('core.api.error.general'); return; }

        // Strip <script> — the builder is a SAFE format (tenant-editable), never code.
        $html = (string) ($params['html'] ?? '');
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);
        $html = preg_replace('#<script\b[^>]*/?>#i', '', (string) $html);
        $css  = trim((string) ($params['css'] ?? ''));

        $body = ($css !== '' ? "<style>\n{$css}\n</style>\n" : '') . $html;

        // Preserve existing meta (SEO/head); replace only the builder project blob.
        $meta = [];
        if (!empty($page->meta)) {
            $decoded = is_array($page->meta) ? $page->meta : json_decode((string) $page->meta, true);
            if (is_array($decoded)) { $meta = $decoded; }
        }
        // HTML is the SOURCE OF TRUTH: we do NOT persist the GrapesJS project blob (a shadow copy that
        // drifts from the body markup — that drift corrupted widget components). Re-opening the builder
        // re-seeds from the body HTML, and each component re-parses its state from it. Drop any legacy blob.
        unset($meta['builder']);

        // A GrapesJS project can carry lone surrogates / invalid UTF-8 (JS strings), which make a strict
        // json_encode return FALSE — an empty string that fails the meta column's json_valid CHECK
        // (SQLSTATE 23000 / err 4025). Encode defensively (substitute bad bytes) so we never write invalid JSON.
        $metaJson = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($metaJson === false) {
            $metaJson = json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        }
        if ($metaJson === false) { $metaJson = '{}'; }

        $save = [
            'body'   => $body,
            'format' => Tiger_Model_Page::FORMAT_BUILDER,
            'meta'   => $metaJson,
        ];
        // Partial editing: the builder's [Layout ▾] picker persists which layout this partial previews
        // against (its layout_key). Only touched when the param is present, so a page save never clears it.
        if (array_key_exists('layout_key', $params)) {
            $lk = trim((string) $params['layout_key']);
            $save['layout_key'] = ($lk !== '') ? $lk : null;
        }

        try {
            $model->save($save, $pageId);
            $this->_success(['page_id' => $pageId], 'cms.page.saved');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Save a builder selection as a reusable BLOCK (a copy-in fragment). The visual builder posts the
     * selected component's HTML (+ its scoped CSS) and a name; we store a type=block page row that the
     * builder then offers in its "My Blocks" palette. Unlike a partial, a block is placed by COPY —
     * dropping it inlines this HTML into a page, detached — so it is a library source only, never
     * resolved at render time. Returns the new block for a live palette add.
     *
     * @param  array $params `name`, `html`, and optional `css`
     * @return void
     */
    public function saveBlock(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $name = trim((string) ($params['name'] ?? ''));
        if ($name === '') { $this->_error('cms.block.name_required'); return; }

        // Strip <script> — a block is a SAFE (tenant-editable) fragment, never code.
        $html = (string) ($params['html'] ?? '');
        $html = preg_replace('#<script\b[^>]*>.*?</script>#is', '', $html);
        $html = preg_replace('#<script\b[^>]*/?>#i', '', (string) $html);
        if (trim((string) $html) === '') { $this->_error('cms.block.empty'); return; }
        $css  = trim((string) ($params['css'] ?? ''));
        $body = ($css !== '' ? "<style>\n{$css}\n</style>\n" : '') . $html;

        // A stable, collision-proof handle (blocks aren't referenced by key, but page_key is UNIQUE-ish
        // per the store's conventions, so uniquify against existing block/partial keys).
        $pm  = new Tiger_Model_Page();
        $key = $this->_uniqueKey($pm, $this->_slugify($name) ?: 'block');

        try {
            $id = $pm->save([
                'type'     => Tiger_Model_Page::TYPE_BLOCK,
                'page_key' => $key,
                'slug'     => null,
                'locale'   => 'en',
                'title'    => $name,
                'body'     => $body,
                'format'   => Tiger_Model_Page::FORMAT_BUILDER,
                'status'   => Tiger_Model_Page::STATUS_PUBLISHED,
            ], null);
            $this->_success(['page_id' => $id, 'page_key' => $key, 'label' => $name, 'html' => $body], 'cms.block.saved');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /** A page_key not already taken by an active row — appends -2, -3, … on collision. */
    protected function _uniqueKey(Tiger_Model_Page $pm, string $base): string
    {
        $key = $base;
        for ($i = 2; $i <= 50; $i++) {
            $hit = $pm->fetchRow($pm->activeSelect()->where('page_key = ?', $key)->limit(1));
            if (!$hit) { return $key; }
            $key = $base . '-' . $i;
        }
        return $base . '-' . substr(bin2hex(random_bytes(3)), 0, 5);
    }

    /**
     * Soft-delete a page (recoverable — the row is flagged, not dropped).
     *
     * @param  array $params the request payload (`page_id`)
     * @return void
     */
    public function delete(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $id = !empty($params['page_id']) ? (string) $params['page_id'] : '';
        if ($id === '') { $this->_error('core.api.error.general'); return; }

        try {
            (new Tiger_Model_Page())->softDelete(['page_id = ?' => $id]);
            $this->_success(['page_id' => $id], 'cms.page.deleted', '/cms/page');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Restore a page to a prior version (current content is snapshotted first).
     *
     * @param  array $params the request payload (`page_id`, `version`)
     * @return void
     */
    public function restore(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $id      = !empty($params['page_id']) ? (string) $params['page_id'] : '';
        $version = isset($params['version']) ? (int) $params['version'] : 0;
        if ($id === '' || $version < 1) { $this->_error('core.api.error.general'); return; }

        try {
            (new Tiger_Model_Page())->restoreVersion($id, $version);
            $this->_success(['page_id' => $id], 'cms.page.restored', '/cms/page/edit/id/' . $id);
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /** lowercase, hyphen-joined, ascii slug (shared by slug + key derivation). */
    protected function _slugify($text): string
    {
        $text = strtolower(trim((string) $text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim((string) $text, '-');
    }
}
