<?php
/**
 * Page — the CMS content store (see migration 0014).
 *
 * One model for all three rendering primitives (`type`): page (routed by slug),
 * layout (chrome), partial (fragment). Resolution honors the tenant cascade
 * (org row wins over global '') and, for pages, the publish + schedule gate.
 *
 * Rendering itself (turning `body` into HTML via the format + a view context) is
 * the CMS service's job, on top of the non-file Zend_View enhancement — this model
 * is the data gateway.
 *
 * @api
 */
class Tiger_Model_Page extends Tiger_Model_Table
{
    protected $_name    = 'page';
    protected $_primary = 'page_id';

    const TYPE_PAGE    = 'page';
    const TYPE_LAYOUT  = 'layout';
    const TYPE_PARTIAL = 'partial';

    const STATUS_DRAFT     = 'draft';
    const STATUS_PUBLISHED = 'published';
    const STATUS_ARCHIVED  = 'archived';

    const FORMAT_HTML     = 'html';      // safe
    const FORMAT_MARKDOWN = 'markdown';  // safe
    const FORMAT_PHTML    = 'phtml';     // code — trusted authors only

    /**
     * Resolve a live page by slug for an org. Walks org_id IN (current, '') and the
     * TENANT row wins over global; only published rows whose schedule has arrived.
     * Returns the row or null.
     */
    public function resolveBySlug($slug, $locale, $orgId = '')
    {
        return $this->fetchRow(
            $this->activeSelect()
                ->where('slug = ?', (string) $slug)
                ->where('locale = ?', (string) $locale)
                ->where('org_id IN (?)', $this->_orgScope($orgId))
                ->where('status = ?', self::STATUS_PUBLISHED)
                ->where('published_at IS NULL OR published_at <= NOW()')
                ->order('org_id DESC')   // non-empty (tenant) sorts before '' (global)
                ->limit(1)
        );
    }

    /**
     * Fetch by stable handle (layouts, partials, or a page by key). Not publish-
     * gated — layouts/partials are infrastructure, fetched regardless of status.
     * Tenant row wins over global.
     */
    public function fetchByKey($key, $locale, $orgId = '', $type = null)
    {
        $select = $this->activeSelect()
            ->where('page_key = ?', (string) $key)
            ->where('locale = ?', (string) $locale)
            ->where('org_id IN (?)', $this->_orgScope($orgId))
            ->order('org_id DESC')
            ->limit(1);
        if ($type !== null) {
            $select->where('type = ?', (string) $type);
        }
        return $this->fetchRow($select);
    }

    /** All published pages under a parent, ordered — for nav/menus. */
    public function children($parentId, $locale, $orgId = '')
    {
        return $this->fetchAll(
            $this->activeSelect()
                ->where('parent_id = ?', (string) $parentId)
                ->where('locale = ?', (string) $locale)
                ->where('org_id IN (?)', $this->_orgScope($orgId))
                ->where('type = ?', self::TYPE_PAGE)
                ->where('status = ?', self::STATUS_PUBLISHED)
                ->order(['sort_order ASC', 'title ASC'])
        );
    }

    /** The org scope for a cascade lookup: [<org>, ''] (deduped; '' = global). */
    protected function _orgScope($orgId)
    {
        return array_values(array_unique([(string) $orgId, '']));
    }
}
