<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * OrgContact — the org ↔ contact link.
 *
 * Joins an org to an owner-agnostic contact channel (Tiger_Model_Contact) and carries
 * the relationship metadata (`label`, `is_primary`) on the link row, so one shared
 * contact can mean different things to different orgs. A user has at most one link per
 * (org, contact) pair.
 *
 * @api
 */
class Tiger_Model_OrgContact extends Tiger_Model_Table
{
    protected $_name    = 'org_contact';
    protected $_primary = 'org_contact_id';

    /**
     * All contact links for an org.
     *
     * @param  string $orgId
     * @return Zend_Db_Table_Rowset_Abstract
     */
    public function findByOrg($orgId)
    {
        return $this->fetchAll($this->activeSelect()->where('org_id = ?', $orgId));
    }

    /**
     * An org's contacts joined to the underlying channel — the render/read shape. Primary first, then
     * oldest. The link PK is aliased to the generic `link_id` so the shared Contacts view/service work
     * for a user or an org unchanged. Each row: link_id, label, is_primary, kind, type, value.
     *
     * @param  string $orgId
     * @return array<int,array<string,mixed>>
     */
    public function withContact($orgId)
    {
        $db = $this->getAdapter();
        return $db->fetchAll(
            $db->select()
               ->from(['oc' => 'org_contact'], ['link_id' => 'org_contact_id', 'label', 'is_primary'])
               ->joinLeft(['c' => 'contact'], 'c.contact_id = oc.contact_id', ['kind', 'type', 'value'])
               ->where('oc.org_id = ?', (string) $orgId)
               ->where('oc.deleted = ?', 0)
               ->order(['oc.is_primary DESC', 'oc.created_at ASC'])
        );
    }
}
