<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * PageRedirect — slug-change redirects (see migration 0016).
 *
 * When a published page's slug changes, a row here 301s the old URL to the new one
 * so links and SEO survive. Read by Tiger_Controller_Plugin_PageDispatch on a miss.
 * Tenant-scoped like pages (org cascade: a tenant row wins over global '').
 *
 * @api
 */
class Tiger_Model_PageRedirect extends Tiger_Model_Table
{
    protected $_name    = 'page_redirect';
    protected $_primary = 'page_redirect_id';

    /**
     * The redirect for a retired slug, or null (tenant row wins over global).
     *
     * @param string $fromSlug the retired (old) slug
     * @param string $locale   the locale to match
     * @param string $orgId    tenant scope ('' = global)
     * @return Zend_Db_Table_Row_Abstract|null the redirect row, or null
     */
    public function findFrom($fromSlug, $locale, $orgId = '')
    {
        return $this->fetchRow(
            $this->select()
                ->where('from_slug = ?', (string) $fromSlug)
                ->where('locale = ?', (string) $locale)
                ->where('org_id IN (?)', array_values(array_unique([(string) $orgId, ''])))
                ->order('org_id DESC')
                ->limit(1)
        );
    }

    /**
     * Record a redirect (call on a slug change). Returns the id.
     *
     * @param string $fromSlug the old slug to redirect from
     * @param string $toSlug   the new slug to redirect to
     * @param string $locale   the locale to match
     * @param string $orgId    tenant scope ('' = global)
     * @param int    $code     the HTTP redirect status code
     * @return string the page_redirect_id
     */
    public function add($fromSlug, $toSlug, $locale, $orgId = '', $code = 301)
    {
        return $this->insert([
            'org_id'    => (string) $orgId,
            'from_slug' => (string) $fromSlug,
            'to_slug'   => (string) $toSlug,
            'locale'    => (string) $locale,
            'code'      => (int) $code,
        ]);
    }

    /**
     * Remove any redirect pointing FROM a slug (e.g. a live page reclaims it).
     *
     * @param string $fromSlug the slug to clear redirects from
     * @param string $locale   the locale to match
     * @param string $orgId    tenant scope ('' = global)
     * @return int the number of rows deleted
     */
    public function clearFrom($fromSlug, $locale, $orgId = '')
    {
        return $this->delete([
            'from_slug = ?' => (string) $fromSlug,
            'locale = ?'    => (string) $locale,
            'org_id = ?'    => (string) $orgId,
        ]);
    }
}
