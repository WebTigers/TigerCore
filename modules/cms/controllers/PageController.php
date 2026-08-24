<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Cms_PageController — the CMS authoring surface, rendered in the PUMA admin shell.
 *
 * Two thin screens: a DataTables list of all content (index) and the page editor
 * (edit). Per the thin-controller rule this class only READS and RENDERS — every
 * mutation goes through the /api service Cms_Service_Page (save/delete/restore).
 * ACL-gated to admin+ (modules/cms/configs/acl.ini) by the unbypassable
 * Authorization plugin; a guest is bounced to login, a non-admin gets a themed 403.
 */
class Cms_PageController extends Tiger_Controller_Admin_Action
{
    /** @var Tiger_Model_Page */
    protected $_pages;

    /**
     * Set up the controller — every action renders inside the admin layout.
     *
     * @return void
     */
    public function init()
    {
        parent::init();
        $this->_pages = new Tiger_Model_Page();
    }

    /**
     * The content list — just the shell. The rows load over AJAX from the /api
     * service (Cms_Service_Page::datatable), per the client/server paradigm: the
     * server renders the page, the browser fetches the data as a Tiger Webservice.
     *
     * @return void
     */
    public function indexAction()
    {
        $this->view->title         = 'Content — Tiger Admin';
        $this->view->useDataTables = true;   // the layout loads jQuery + DataTables when set

        // The ACTIVE theme's forkable material — pages/layouts/partials it serves from files
        // (AUTHORING.md §3.3), surfaced in the "Theme Templates" tab so an author can CUSTOMIZE one (fork
        // it into an editable row that overrides the file). An installed-but-inactive theme has no asset
        // symlink, so its content can't render and never surfaces. The tab is a server-side DataTable
        // (Cms_Service_Page::themeTemplates); here we only need the count for the tab badge (cheap —
        // Tiger_Theme::forkables is fingerprint-cached).
        $this->view->themeCount = count(Tiger_Theme::forkables());
    }

    /**
     * Create (no id) or edit (id) — renders the form; saving is an /api call, not a post-back.
     *
     * @return void
     */
    public function editAction()
    {
        $id   = (string) $this->getParam('id', '');
        $page = $id !== '' ? $this->_pages->findById($id) : null;

        $form = new Cms_Form_Page();
        if ($page) {
            $form->populate($this->_editValues($page));
        }

        $this->view->title    = ($page ? 'Edit' : 'New') . ' Page — Tiger Admin';
        $this->view->form     = $form;
        $this->view->page     = $page;
        $this->view->versions = $page
            ? (new Tiger_Model_PageVersion())->recentForPage($id)
            : [];
    }

    /**
     * Full-screen GrapesJS visual builder for an existing page. Renders its OWN minimal
     * document (admin shell disabled) so the builder owns the viewport; saving goes
     * through the /api service (Cms_Service_Page::saveDesign). The canvas restores
     * losslessly from meta.builder when present, else seeds from the page's current body.
     *
     * @return void
     */
    public function designAction()
    {
        $id   = (string) $this->getParam('id', '');
        $page = $id !== '' ? $this->_pages->findById($id) : null;
        if (!$page) {
            $this->_helper->redirector->gotoUrl('/cms/page');
            return;
        }

        $meta = [];
        if (!empty($page->meta)) {
            $decoded = is_array($page->meta) ? $page->meta : json_decode((string) $page->meta, true);
            if (is_array($decoded)) { $meta = $decoded; }
        }

        // Pre-render each menu so the builder's Menu component shows a LIVE preview in the canvas
        // (it still exports the [menu] shortcode, staying dynamic + auth-filtered at view time).
        $menus = [];
        foreach ((new Tiger_Model_Menu())->keys() as $key) {
            $menus[$key] = Tiger_Menu::getHTML($key);
        }

        // Named partials for the builder's Partial widget — a {name: {label, html}} map: `label` for
        // the picker, `html` a live-rendered preview. The widget exports [partial name="x"] (dynamic),
        // never the preview markup, so editing the partial updates everywhere it's dropped.
        $partials = [];
        try {
            $pm = new Tiger_Model_Page();
            foreach ($pm->fetchAll(
                $pm->activeSelect()
                    ->where('type = ?', Tiger_Model_Page::TYPE_PARTIAL)
                    ->where('status = ?', Tiger_Model_Page::STATUS_PUBLISHED)
                    ->order('page_key ASC')
            ) as $r) {
                if (!$r->page_key) { continue; }
                $partials[$r->page_key] = [
                    'label' => (string) (($r->title !== null && $r->title !== '') ? $r->title : $r->page_key),
                    'html'  => (new Tiger_Cms_Renderer())->renderBody((string) $r->body, (string) $r->format, []),
                ];
            }
        } catch (\Throwable $e) { $partials = []; }

        // The ACTIVE theme's builder components (its components/*.phtml) + the CSS to load into
        // the GrapesJS canvas so those blocks preview in the theme's own style (THEMES.md Tier 2).
        $manifest = Tiger_Theme::manifest();

        // User-authored BLOCKS — reusable fragments placed by COPY. Passed to the builder's "My Blocks"
        // palette; dropping one inlines its editable HTML into the page (detached from the source), the
        // twin of the reference-placed Partial widget. Body is inserted raw so the author edits real
        // markup (GrapesJS absorbs any <style>); shortcodes it contains carry through.
        $userBlocks = [];
        try {
            foreach ($this->_pages->fetchAll(
                $this->_pages->activeSelect()
                    ->where('type = ?', Tiger_Model_Page::TYPE_BLOCK)
                    ->where('status = ?', Tiger_Model_Page::STATUS_PUBLISHED)
                    ->order('title ASC')
            ) as $b) {
                $userBlocks[] = [
                    'id'      => (string) $b->page_id,
                    'label'   => (string) (($b->title !== null && $b->title !== '') ? $b->title : $b->page_key),
                    'content' => (string) $b->body,
                ];
            }
        } catch (\Throwable $e) { $userBlocks = []; }

        // --- Fragment editing (partial OR block): a fragment can't be edited in a vacuum — it only reads
        // correctly INSIDE a layout's chrome (+ the theme CSS). This is the page builder inverted: the
        // layout becomes the locked context and the fragment is the one editable region. A PARTIAL
        // resolves its associated layout (layout_key) and splits it at the partial's slot into chrome-
        // before / chrome-after; a fragment with no CMS layout falls back to the theme's real header/
        // footer (the view), editing in the content area. `layoutOptions` feeds the [Layout ▾] picker.
        $isPartial    = ($page->type === Tiger_Model_Page::TYPE_PARTIAL);
        $isBlock      = ($page->type === Tiger_Model_Page::TYPE_BLOCK);
        $isFragment   = ($isPartial || $isBlock);
        $chromeBefore = null;
        $chromeAfter  = null;
        $layoutOpts   = [];
        if ($isFragment) {
            $layoutOpts = $this->_layoutOptions();
            if (!empty($page->layout_key)) {
                $layout = $this->_findLayout((string) $page->layout_key, $page);
                if ($layout) {
                    [$chromeBefore, $chromeAfter] = $this->_splitLayoutForPartial($layout, $page);
                }
            }
        }

        $this->_helper->layout()->disableLayout();   // full-screen — the view is a complete document
        $this->view->title           = $page->title;
        $this->view->page            = $page;
        // HTML is the SOURCE OF TRUTH — always seed the builder from the page's current body markup, never
        // a stored GrapesJS project blob (which drifts from the HTML). Components re-parse their state on load.
        $this->view->projectData     = null;
        $this->view->menus           = $menus;
        $this->view->partials        = $partials;
        $this->view->userBlocks      = $userBlocks;
        $this->view->themeBlocks     = Tiger_Theme::components();
        $this->view->canvasCss       = isset($manifest['canvasCss']) ? (array) $manifest['canvasCss'] : [];
        // A theme may ship a JS file (theme.json "builderJs") that registers its OWN editable GrapesJS
        // component types (sliders, marquees, …). Loaded before the builder seeds the canvas; cache-bust
        // from the file mtime (the asset() helper only cache-busts theme-base URLs; this is absolute).
        $builderJs = isset($manifest['builderJs']) ? (string) $manifest['builderJs'] : '';
        if ($builderJs !== '' && class_exists('Tiger_Theme')) {
            $base = (string) Tiger_Theme::assetBase();
            $rel  = ($base !== '' && strpos($builderJs, $base) === 0) ? ltrim(substr($builderJs, strlen($base)), '/') : '';
            $file = $rel !== '' ? rtrim((string) Tiger_Theme::dir(), '/') . '/assets/' . $rel : '';
            if ($file !== '' && is_file($file)) {
                $builderJs .= (strpos($builderJs, '?') === false ? '?v=' : '&v=') . filemtime($file);
            }
        }
        $this->view->builderJs = $builderJs;
        $this->view->partialMode     = $isFragment;
        $this->view->fragmentLabel   = $isBlock ? 'Block' : ($isPartial ? 'Partial' : '');
        $this->view->chromeBefore    = $chromeBefore;   // null (page, or fragment w/o CMS layout) -> view uses theme header/footer
        $this->view->chromeAfter     = $chromeAfter;
        $this->view->layoutOptions   = $layoutOpts;
        $this->view->partialLayoutKey = $isFragment ? (string) ($page->layout_key ?? '') : '';
    }

    /**
     * Every CMS layout row as [page_key => label] — the choices for the partial editor's
     * "preview against [layout ▾]" picker (empty selection = the theme's default header/footer).
     *
     * @return array<string,string>
     */
    protected function _layoutOptions(): array
    {
        $out = [];
        foreach ($this->_pages->fetchAll(
            $this->_pages->activeSelect()
                ->where('type = ?', Tiger_Model_Page::TYPE_LAYOUT)
                ->order('page_key ASC')
        ) as $r) {
            if (!$r->page_key) { continue; }
            $out[(string) $r->page_key] = (string) (($r->title !== null && $r->title !== '') ? $r->title : $r->page_key);
        }
        return $out;
    }

    /**
     * Resolve a layout row by its key — locale-tolerant (the partial's own locale first, then the
     * shared '' rows), tenant row winning over global. Null if no such layout.
     *
     * @param  string $key  the layout's page_key
     * @param  object $page the partial being edited (for its locale)
     * @return Zend_Db_Table_Row_Abstract|null
     */
    protected function _findLayout(string $key, $page)
    {
        foreach ([(string) ($page->locale ?? ''), ''] as $loc) {
            $row = $this->_pages->fetchRow(
                $this->_pages->activeSelect()
                    ->where('page_key = ?', $key)
                    ->where('type = ?', Tiger_Model_Page::TYPE_LAYOUT)
                    ->where('locale = ?', $loc)
                    ->order('org_id DESC')
                    ->limit(1)
            );
            if ($row) { return $row; }
        }
        return null;
    }

    /**
     * Split a layout around the partial's slot for in-context editing: render the layout with THIS
     * partial replaced by a marker (all OTHER partials, [menu]s, and the [content] placeholder expand
     * as locked context), then cut the rendered HTML on the marker into [before, after]. Routing is
     * automatic — a partial the layout NAMES ([partial name="key"]) edits in its own slot (a header or
     * footer); any other partial edits in the [content] area (a hero / CTA / section). <style>/<script>
     * are stripped (static, non-editable preview, never baked into the saved partial).
     *
     * @param  object $layout  the associated layout row
     * @param  object $partial the partial being edited
     * @return array{0:string,1:string} chrome-before and chrome-after HTML
     */
    protected function _splitLayoutForPartial($layout, $partial): array
    {
        $MARK = '@@TIGER_PARTIAL_SLOT@@';
        $body = (string) $layout->body;
        $key  = (string) $partial->page_key;

        $refPat = '/\[partial\s+[^\]]*name="' . preg_quote($key, '/') . '"[^\]]*\]/i';
        if ($key !== '' && preg_match($refPat, $body)) {
            $body = preg_replace($refPat, $MARK, $body, 1);              // chrome partial — its own slot
        } elseif (strpos($body, '[content]') !== false) {
            $body = str_replace('[content]', $MARK, $body);             // content partial — the body slot
        } else {
            $body .= $MARK;                                             // no content slot — after the layout
        }

        $hint = '<div class="text-center text-body-secondary small py-5 my-3"'
              . ' style="border:1px dashed var(--bs-border-color);border-radius:.5rem;">— page content —</div>';
        try {
            $rendered = (new Tiger_Cms_Renderer())->renderBody($body, (string) $layout->format, ['content' => $hint]);
        } catch (\Throwable $e) {
            $rendered = $MARK;
        }

        $parts  = explode($MARK, $rendered, 2);
        $strip  = ['#<style\b[^>]*>.*?</style>#is', '#<script\b[^>]*>.*?</script>#is'];
        $before = trim((string) preg_replace($strip, '', $parts[0] ?? ''));
        $after  = trim((string) preg_replace($strip, '', $parts[1] ?? ''));
        return [$before, $after];
    }

    /** Map a page row to editor form values. */
    protected function _editValues($page)
    {
        $meta = [];
        if (!empty($page->meta)) {
            $decoded = is_array($page->meta) ? $page->meta : json_decode((string) $page->meta, true);
            if (is_array($decoded)) { $meta = $decoded; }
        }
        return [
            'page_id'          => $page->page_id,
            'title'            => $page->title,
            'slug'             => $page->slug,
            'page_key'         => $page->page_key,
            'type'             => $page->type,
            'format'           => $page->format,
            'status'           => $page->status,
            'locale'           => $page->locale,
            'layout_key'       => $page->layout_key,
            'published_at'     => $page->published_at,
            'body'             => $page->body,
            'seo_title'        => $meta['seo']['title'] ?? '',
            'meta_description' => $meta['seo']['description'] ?? ($meta['description'] ?? ''),
            'og_image_id'      => $meta['seo']['og_image_id'] ?? '',
            'head_html'        => $meta['head_html']    ?? '',
            'body_scripts'     => $meta['body_scripts'] ?? '',
        ];
    }
}
