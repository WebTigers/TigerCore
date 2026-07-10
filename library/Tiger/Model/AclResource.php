<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * AclResource — runtime resource rows (DB layer). Read by Tiger_Acl_Acl on top of
 * the code-shipped ini resources. See migration 0007.
 *
 * @api
 */
class Tiger_Model_AclResource extends Tiger_Model_Table
{
    protected $_name    = 'acl_resource';
    protected $_primary = 'acl_resource_id';

    /**
     * Fetch all active resources for the ACL loader.
     *
     * @return Zend_Db_Table_Rowset_Abstract the active resource rows
     */
    public function getResourceList()
    {
        return $this->fetchAll($this->activeSelect());
    }
}
