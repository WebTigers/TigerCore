<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Comment_Service_Subjects — the resolvers for core's own two subject types.
 *
 * A resolver answers one question: given an id, what is this thing CALLED, where does it live, and
 * does it still exist? The last part is what lets the moderation queue show a row whose page was
 * deleted, instead of rendering a blank — which is exactly when an operator needs to see it.
 *
 * Not a `Tiger_Service_Service`: these are called in-process by the registry, never over `/api`.
 */
class Comment_Service_Subjects
{
    /**
     * Resolve a CMS page.
     *
     * @param  string $id the page id
     * @return array{title:string,url:string,exists:bool}
     */
    public static function page($id)
    {
        try {
            $row = (new Tiger_Model_Page())->findById((string) $id);
            if (!$row) { return ['title' => '', 'url' => '', 'exists' => false]; }

            return [
                'title'   => (string) $row->title,
                'url'     => '/' . ltrim((string) $row->slug, '/'),
                'exists'  => true,
                'visible' => self::_publicOnSite($row),
            ];
        } catch (Throwable $e) {
            return ['title' => '', 'url' => '', 'exists' => false];
        }
    }

    /**
     * Resolve a blog article.
     *
     * @param  string $id the post id
     * @return array{title:string,url:string,exists:bool}
     */
    public static function blogPost($id)
    {
        try {
            if (!class_exists('Blog_Model_Post')) { return ['title' => '', 'url' => '', 'exists' => false]; }

            $row = (new Blog_Model_Post())->findById((string) $id);
            if (!$row) { return ['title' => '', 'url' => '', 'exists' => false]; }

            return [
                'title'   => (string) $row->title,
                'url'     => '/blog/' . ltrim((string) $row->slug, '/'),
                'exists'  => true,
                'visible' => self::_publicOnSite($row),
            ];
        } catch (Throwable $e) {
            return ['title' => '', 'url' => '', 'exists' => false];
        }
    }

    /**
     * Does the CURRENT SITE publicly show this page/article row? (TIGER-511)
     *
     * Mirrors the public read gate (`Tiger_Model_Page::resolveBySlug`): published, its schedule has
     * arrived, and owned by the site's org or the shared `''` tier. Without it a thread could be read or
     * posted on a draft, or on another tenant's page, by anyone who learned its id.
     *
     * @param  Zend_Db_Table_Row_Abstract $row a `page` row
     * @return bool
     */
    private static function _publicOnSite($row)
    {
        if ((string) $row->status !== Tiger_Model_Page::STATUS_PUBLISHED) { return false; }

        $at = (string) ($row->published_at ?? '');
        if ($at !== '' && strtotime($at) > time()) { return false; }

        return in_array((string) $row->org_id, [Tiger_Model_Org::siteOrgId(), ''], true);
    }
}
