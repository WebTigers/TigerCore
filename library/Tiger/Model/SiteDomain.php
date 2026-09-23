<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * SiteDomain — the host → org map that makes one install serve many sites (see migration 0052).
 *
 * A row binds a request hostname to the org (tenant) whose public site that host serves. The bootstrap
 * (Tiger_Application_Bootstrap::_initSiteOrg) reads the request Host, resolves it here, and — on a match
 * — pins the site org (Tiger_Model_Org::setSiteOrgId) so CMS pages, redirects and the org-scoped config
 * tier (theme / skin / home page / settings) all resolve to that tenant. No match leaves the default
 * single-site behavior untouched.
 *
 * A hostname resolves to exactly one org (domain is globally unique); a site's `www` and apex are two
 * rows. Lookups match the host EXACTLY (lowercased, port stripped) — the caller/driver is responsible
 * for creating both the apex and `www` rows, the same way it adds the vhost ServerAlias + AutoSSL names.
 *
 * @api
 */
class Tiger_Model_SiteDomain extends Tiger_Model_Table
{
    protected $_name    = 'site_domain';
    protected $_primary = 'site_domain_id';

    /**
     * Normalize a request host to the stored form: lowercased, port stripped, trimmed.
     *
     * @param  string $host a raw Host header value (may carry a :port)
     * @return string       the canonical host, or '' if empty
     */
    public static function normalizeHost($host)
    {
        $host = strtolower(trim((string) $host));
        if (($colon = strpos($host, ':')) !== false) {
            $host = substr($host, 0, $colon);   // strip :port
        }
        return $host;
    }

    /**
     * The org id whose public site the given host serves, or null when no active mapping matches.
     *
     * @param  string $host the request host (raw; normalized internally)
     * @return string|null   the org id, or null (unmapped → single-site default)
     */
    public function orgForHost($host)
    {
        $host = self::normalizeHost($host);
        if ($host === '') {
            return null;
        }
        $row = $this->fetchRow(
            $this->activeSelect()
                ->where('domain = ?', $host)
                ->where('status = ?', 'active')
                ->limit(1)
        );
        return $row ? (string) $row->org_id : null;
    }

    /**
     * A single mapping row by host (active, not deleted), or null.
     *
     * @param  string $host the request host (raw; normalized internally)
     * @return Zend_Db_Table_Row_Abstract|null
     */
    public function findByHost($host)
    {
        $host = self::normalizeHost($host);
        if ($host === '') {
            return null;
        }
        return $this->fetchRow($this->activeSelect()->where('domain = ?', $host)->limit(1)) ?: null;
    }

    /**
     * Every host mapped to an org (active, not deleted), newest first.
     *
     * @param  string $orgId the tenant
     * @return Zend_Db_Table_Rowset_Abstract
     */
    public function allForOrg($orgId)
    {
        return $this->fetchAll(
            $this->activeSelect()->where('org_id = ?', (string) $orgId)->order('created_at DESC')
        );
    }
}
