<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * OrgAddress — the org ↔ address link.
 *
 * Joins an org to an owner-agnostic address (Tiger_Model_Address) and carries the
 * relationship metadata (`label`, `is_primary`) on the link row, so one shared address
 * can mean different things to different orgs. A user has at most one link per
 * (org, address) pair.
 *
 * @api
 */
class Tiger_Model_OrgAddress extends Tiger_Model_Table
{
    protected $_name    = 'org_address';
    protected $_primary = 'org_address_id';

    /**
     * All address links for an org.
     *
     * @param  string $orgId
     * @return Zend_Db_Table_Rowset_Abstract
     */
    public function findByOrg($orgId)
    {
        return $this->fetchAll($this->activeSelect()->where('org_id = ?', $orgId));
    }

    /**
     * An org's addresses joined to the underlying location — the render/read shape. Primary first,
     * then oldest. The link PK is aliased to the generic `link_id` so the shared Addresses view/service
     * work for a user or an org unchanged. Each row: link_id, label, is_primary, and
     * line1/line2/city/region/postal/country/latitude/longitude.
     *
     * @param  string $orgId
     * @return array<int,array<string,mixed>>
     */
    public function withAddress($orgId)
    {
        $db = $this->getAdapter();
        return $db->fetchAll(
            $db->select()
               ->from(['oa' => 'org_address'], ['link_id' => 'org_address_id', 'label', 'is_primary'])
               ->joinLeft(['a' => 'address'], 'a.address_id = oa.address_id',
                   ['line1', 'line2', 'city', 'region', 'postal', 'country', 'latitude', 'longitude'])
               ->where('oa.org_id = ?', (string) $orgId)
               ->where('oa.deleted = ?', 0)
               ->order(['oa.is_primary DESC', 'oa.created_at ASC'])
        );
    }
}
