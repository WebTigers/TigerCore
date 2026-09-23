<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * System_Service_Sites — /api service for the multi-site host → org map (datatable / save / delete).
 *
 * One Tiger install can serve many public sites, one per org, resolved by the request Host (the
 * resolver is Tiger_Application_Bootstrap::_initSiteOrg + Tiger_Model_SiteDomain). This is the writer:
 * an admin (or a driver such as TigerPanel/TigerServer over /api) maps hostnames to the org whose site
 * they serve. A host is unique across the whole install (it resolves to exactly one org); a site's
 * `www` and apex are two separate rows. ACL: admin+ (modules/system/configs/acl.ini).
 *
 * @api
 */
class System_Service_Sites extends Tiger_Service_Service
{
    /**
     * DataTables server-side source: host + the org it serves.
     *
     * @param  array $params the DataTables request (draw/start/length/search/order)
     * @return void
     */
    public function datatable(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $dt   = $this->_dtParams($params);
        $data = (new Tiger_Model_SiteDomain())->datatable([
            'search'   => $dt['search'],
            'orderCol' => isset($dt['order'][0]) ? $dt['order'][0]['column'] : -1,
            'orderDir' => isset($dt['order'][0]) ? $dt['order'][0]['dir'] : '',
            'offset'   => $dt['start'],
            'limit'    => $dt['length'],
        ]);

        $canDelete = $this->_isAdmin(static::class, 'delete');

        $rows = [];
        foreach ($data['rows'] as $r) {
            $rows[] = [
                'site_domain_id' => $r['site_domain_id'],
                'domain'         => $r['domain'],
                'org_id'         => $r['org_id'],
                'org_name'       => ($r['org_name'] !== null && $r['org_name'] !== '') ? $r['org_name'] : '',
                'status'         => $r['status'],
                'created'        => substr((string) $r['created_at'], 0, 10),
                'can_delete'     => $canDelete,
            ];
        }

        $this->_dtResponse($dt['draw'], $data['total'], $data['filtered'], $rows);
    }

    /**
     * Map a host to an org (insert when site_domain_id is empty; a host is unique install-wide).
     *
     * @param  array $params the submitted form values + site_domain_id
     * @apiRequest System_Form_Site
     * @return void
     */
    public function save(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $form = new System_Form_Site();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        $v = $form->getValues();

        $id     = !empty($params['site_domain_id']) ? (string) $params['site_domain_id'] : null;
        $domain = Tiger_Model_SiteDomain::normalizeHost($v['domain']);
        $orgId  = (string) $v['org_id'];

        if ($domain === '') { $this->_error('system.sites.error.bad_domain'); return; }

        // The org must exist (a host can't point at a phantom tenant).
        if ((new Tiger_Model_Org())->findById($orgId) === null) { $this->_error('system.sites.error.bad_org'); return; }

        $model = new Tiger_Model_SiteDomain();
        if ($model->hostTaken($domain, $id)) { $this->_error('system.sites.error.host_taken'); return; }

        try {
            $newId = $this->_transaction(function () use ($model, $id, $domain, $orgId) {
                $data = ['domain' => $domain, 'org_id' => $orgId, 'status' => 'active'];
                if ($id) {
                    $model->update($data, ['site_domain_id = ?' => $id]);
                    return $id;
                }
                return $model->insert($data);
            });
            $this->_success(['site_domain_id' => $newId], 'system.sites.saved', '/system/sites');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Remove a host → org mapping (soft-delete); the site falls back to single-site resolution.
     *
     * @param  array $params the request payload carrying site_domain_id
     * @return void
     */
    public function delete(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $id = !empty($params['site_domain_id']) ? (string) $params['site_domain_id'] : '';
        if ($id === '') { $this->_error('core.api.error.general'); return; }

        try {
            $model = new Tiger_Model_SiteDomain();
            $model->softDelete($model->getAdapter()->quoteInto('site_domain_id = ?', $id));
            $this->_success([], 'system.sites.deleted');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }
}
