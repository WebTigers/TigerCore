<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Blog_Service_Post — the /api service for article authoring (datatable / save /
 * delete / restore). Thin + admin-gated; the write defers to Blog_Model_Post (which
 * extends the transactional Tiger_Model_Page: save() snapshots a version and 301s a
 * changed slug), then syncs the post's taxonomy links.
 *
 * ACL resource = this class (Blog_Service_Post), granted admin+ in configs/acl.ini;
 * the gateway also privilege-checks the method name.
 *
 * @api
 */
class Blog_Service_Post extends Tiger_Service_Service
{
    /**
     * DataTables source for the article list (type=article), rows as structured data.
     *
     * @param  array $params the DataTables request (draw/start/length/search/order) + status filter
     * @apiRequest datatable
     * @apiParam string(draft|published|archived) status Only articles in this status.
     * @return void
     */
    public function datatable(array $params): void
    {
        if (!$this->_isAllowed('datatable')) { $this->_error('core.api.error.not_allowed'); return; }

        $dt    = $this->_dtParams($params);
        $model = new Blog_Model_Post();
        $data  = $model->articleDatatable([
            'search'   => $dt['search'],
            'status'   => in_array(($params['status'] ?? ''), [Blog_Model_Post::STATUS_DRAFT, Blog_Model_Post::STATUS_PUBLISHED, Blog_Model_Post::STATUS_ARCHIVED], true) ? (string) $params['status'] : '',
            'orderCol' => isset($dt['order'][0]) ? $dt['order'][0]['column'] : -1,
            'orderDir' => isset($dt['order'][0]) ? $dt['order'][0]['dir'] : '',
            'offset'   => $dt['start'],
            'limit'    => $dt['length'],
        ]);

        $canEdit   = $this->_isAllowed('save');
        $canDelete = $this->_isAllowed('delete');

        $rows = [];
        foreach ($data['rows'] as $r) {
            $meta  = $model->unpackMeta($r['meta']);
            $stamp = (string) ($r['updated_at'] ?: $r['created_at']);
            $rows[] = [
                'page_id'    => $r['page_id'],
                'title'      => ($r['title'] !== null && $r['title'] !== '') ? $r['title'] : '(untitled)',
                'handle'     => ($r['slug'] !== null && $r['slug'] !== '') ? '/blog/' . $r['slug'] : '',
                'kicker'     => (string) $meta['kicker'],
                'locale'     => $r['locale'],
                'status'     => $r['status'],
                'scheduled'  => ($r['status'] === 'published' && $r['published_at'] && strtotime((string) $r['published_at']) > time()),
                'reading'    => (int) $meta['reading_time'],
                'updated'    => substr($stamp, 0, 16),
                'can_edit'   => $canEdit,
                'can_delete' => $canDelete,
            ];
        }

        $this->_dtResponse($dt['draw'], $data['total'], $data['filtered'], $rows);
    }

    /**
     * Create or update an article (insert when post_id is empty).
     *
     * @param  array $params the submitted form values + post_id
     * @apiRequest Blog_Form_Post
     * @return void
     */
    public function save(array $params): void
    {
        if (!$this->_isAllowed('save')) { $this->_error('core.api.error.not_allowed'); return; }

        $form   = new Blog_Form_Post();
        $postId = !empty($params['post_id']) ? (string) $params['post_id'] : null;
        $params = $this->_seedOmitted($params, $form, $postId);
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        $v = $form->getValues();

        $locale = (string) $v['locale'];
        $orgId  = $this->_orgId();

        // Slug + stable key from the slug, else the title.
        $slugIn = trim((string) $v['slug']);
        $slug   = $this->_slugify($slugIn !== '' ? $slugIn : (string) $v['title']);
        if ($slug === '') { $this->_error('blog.error.slug'); return; }
        // These paths are routed to the admin/archives/feed under /blog, so an article can't
        // own them (it would be unreachable). Reject with a clear message.
        if (in_array($slug, ['post', 'category', 'tag', 'feed', 'index'], true)) {
            $this->_error('blog.error.slug_reserved'); return;
        }

        $fields = [
            'page_key'         => $slug,
            'slug'             => $slug,
            'locale'           => $locale,
            'title'            => $v['title'],
            'body'             => (string) $v['body'],
            'status'           => $v['status'],
            'published_at'     => trim((string) $v['published_at']),
            'kicker'           => $v['kicker'],
            'subtitle'         => $v['subtitle'],
            'preamble'         => $v['preamble'],
            'excerpt'          => $v['excerpt'],
            'feature_media_id' => $v['feature_media_id'],
            'author_id'        => trim((string) $v['author_id']) !== '' ? $v['author_id'] : $this->_userId(),
            'allow_comments'   => !empty($v['allow_comments']),
            'seo_title'        => $v['seo_title'],
            'seo_description'  => $v['seo_description'],
            'og_image_id'      => $v['og_image_id'],
            'canonical'        => $v['canonical'],
        ];

        try {
            $model = new Blog_Model_Post();
            $id    = $model->saveArticle($fields, $postId);

            // Taxonomy: resolve comma-typed category/tag names to term ids (creating any
            // new ones), then rewrite the post's links. A follow-on write to the page
            // save — the join has no version history.
            $tax    = new Blog_Model_Taxonomy();
            $termIds = array_merge(
                $this->_resolveTerms($tax, Blog_Model_Taxonomy::VOCAB_CATEGORY, $v['categories'], $locale, $orgId),
                $this->_resolveTerms($tax, Blog_Model_Taxonomy::VOCAB_TAG, $v['tags'], $locale, $orgId)
            );
            $tax->syncPage($id, $termIds);

            $this->_success(['page_id' => $id], 'blog.post.saved', '/blog/post');
        } catch (Throwable $e) {
            // Production shows the caller a generic message, so the log is the only place the cause survives.
            Tiger_Log::error('blog.post.save_failed', ['post_id' => $postId, 'error' => $e->getMessage()]);
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /** The fields an /api caller may leave out: the editor always posts them, so they carry a default. */
    const SEEDED_FIELDS = ['status', 'locale'];

    /**
     * Fill in the fields an /api or MCP caller left out, before the form sees them.
     *
     * ZF1's `isValid()` hands a missing element `null`, overriding the form's declared default, so an
     * agent that omitted `locale` saved an article with an empty locale — a row that no locale-scoped
     * read finds. A NEW article takes the form's declared default; an UPDATE keeps the row's current
     * value, so leaving out `status` can never quietly unpublish an article. Only absent keys are
     * filled: a value the caller sent, blank included, is validated as sent.
     *
     * @param  array          $params the request payload
     * @param  Blog_Form_Post $form   the form whose declared defaults seed a new article
     * @param  string|null    $postId the article being updated, or null for a new one
     * @return array                  the payload with each omitted field filled
     */
    protected function _seedOmitted(array $params, $form, $postId): array
    {
        $existing = $postId ? (new Tiger_Model_Page())->findById($postId) : null;
        foreach (self::SEEDED_FIELDS as $field) {
            if (array_key_exists($field, $params)) { continue; }
            $params[$field] = $existing ? $existing->{$field} : $form->getElement($field)->getValue();
        }
        return $params;
    }

    /**
     * Soft-delete an article (recoverable).
     *
     * @param  array $params the request payload carrying post_id
     * @apiRequest Blog_Form_PostDelete
     * @apiParam string post_id required The article to delete.
     * @return void
     */
    public function delete(array $params): void
    {
        if (!$this->_isAllowed('delete')) { $this->_error('core.api.error.not_allowed'); return; }
        // The shared article token (TIGER-528) — a delete-by-id is still a write.
        $form = new Blog_Form_PostDelete();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        $id = !empty($params['post_id']) ? (string) $params['post_id'] : '';
        if ($id === '') { $this->_error('core.api.error.general'); return; }

        try {
            (new Blog_Model_Post())->softDelete(['page_id = ?' => $id]);
            $this->_success(['page_id' => $id], 'blog.post.deleted', '/blog/post');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Restore an article to a prior version (current content snapshotted first).
     *
     * @param  array $params the request payload carrying post_id + version
     * @apiRequest Blog_Form_PostRestore
     * @apiParam string post_id required The article to restore.
     * @apiParam integer version required The version number to restore (1 or higher).
     * @return void
     */
    public function restore(array $params): void
    {
        if (!$this->_isAllowed('restore')) { $this->_error('core.api.error.not_allowed'); return; }
        // The editor's token — it posts its whole form here (TIGER-528).
        $form = new Blog_Form_PostRestore();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        $id      = !empty($params['post_id']) ? (string) $params['post_id'] : '';
        $version = isset($params['version']) ? (int) $params['version'] : 0;
        if ($id === '' || $version < 1) { $this->_error('core.api.error.general'); return; }

        try {
            (new Blog_Model_Post())->restoreVersion($id, $version);
            $this->_success(['page_id' => $id], 'blog.post.restored', '/blog/post/edit/id/' . $id);
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /** Split a comma-separated term string and resolve each to a taxonomy_id. */
    protected function _resolveTerms(Blog_Model_Taxonomy $tax, $vocabulary, $csv, $locale, $orgId): array
    {
        $ids = [];
        foreach (explode(',', (string) $csv) as $name) {
            $tid = $tax->findOrCreate($vocabulary, $name, $locale, $orgId);
            if ($tid) { $ids[] = $tid; }
        }
        return $ids;
    }

    protected function _orgId(): string
    {
        $idn = Zend_Auth::getInstance()->getIdentity();
        return ($idn && !empty($idn->org_id)) ? (string) $idn->org_id : '';
    }

    protected function _userId(): string
    {
        $idn = Zend_Auth::getInstance()->getIdentity();
        return ($idn && !empty($idn->user_id)) ? (string) $idn->user_id : '';
    }

    protected function _slugify($text): string
    {
        $text = strtolower(trim((string) $text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        return trim((string) $text, '-');
    }
}
