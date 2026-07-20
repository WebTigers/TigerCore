<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Profile_Service_OrgAddress — the /api service behind the ORG profile Addresses tab.
 *
 * The org twin of Profile_Service_Address: identical shape, admin-gated and scoped to the CURRENT org
 * ($this->_org_id). Reuses Profile_Form_Address and the shared Addresses view (which speaks the
 * generic `link_id`). `type` → the link label; the location (incl. cached geocode) → the shared
 * `address` row; `is_primary` single-per-collection; CSRF rotates every response.
 *
 * @api
 */
class Profile_Service_OrgAddress extends Profile_Service_Base
{
    /**
     * Create or update one of the current org's addresses (edit when link_id is present).
     *
     * @param  array $params type, country, line1, line2, city, region, postal, latitude, longitude, is_primary, link_id
     * @return void
     */
    public function save(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $orgId = (string) $this->_org_id;
        if ($orgId === '') { $this->_error('core.api.error.not_allowed'); return; }

        $form = new Profile_Form_Address();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }

        $types = Tiger_Profile_Types::address();
        $type  = strtolower(trim((string) $form->getValue('type')));
        if (!array_key_exists($type, $types)) { $this->_error('profile.address.bad_type', ['field' => 'type']); return; }

        $country = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $form->getValue('country')));
        if (!in_array($country, Tiger_I18n_Country::codes(), true)) { $this->_error('profile.address.bad_country', ['field' => 'country']); return; }

        $loc = [
            'line1'     => trim((string) $form->getValue('line1')),
            'line2'     => trim((string) $form->getValue('line2')) ?: null,
            'city'      => trim((string) $form->getValue('city')),
            'region'    => trim((string) $form->getValue('region')) ?: null,
            'postal'    => trim((string) $form->getValue('postal')) ?: null,
            'country'   => $country,
            'latitude'  => $this->_coord($form->getValue('latitude'), 90.0),
            'longitude' => $this->_coord($form->getValue('longitude'), 180.0),
        ];
        $primary = (bool) $form->getValue('is_primary');
        $linkId  = trim((string) $form->getValue('link_id'));

        try {
            $this->_transaction(function () use ($orgId, $type, $loc, $primary, $linkId) {
                $link = new Tiger_Model_OrgAddress();
                if ($linkId !== '') {
                    $row = $link->findById($linkId);
                    if (!$row || (string) $row->org_id !== $orgId) {
                        throw new RuntimeException('Not your org address.');
                    }
                    (new Tiger_Model_Address())->update($loc, $link->getAdapter()->quoteInto('address_id = ?', (string) $row->address_id));
                    $link->update(['label' => $type, 'is_primary' => $primary ? 1 : 0], $link->getAdapter()->quoteInto('org_address_id = ?', $linkId));
                    $keepId = $linkId;
                } else {
                    $addressId = (new Tiger_Model_Address())->create($loc);
                    $keepId    = $link->insert(['org_id' => $orgId, 'address_id' => $addressId, 'label' => $type, 'is_primary' => $primary ? 1 : 0]);
                }
                if ($primary) {
                    $this->_soloPrimary($link, 'org_address_id', 'org_id', $orgId, (string) $keepId);
                }
            });
            $this->_ok($orgId, 'profile.address.saved');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Delete one of the current org's addresses (unlink + soft-delete the location).
     *
     * @param  array $params link_id
     * @return void
     */
    public function delete(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $orgId = (string) $this->_org_id;
        if ($orgId === '') { $this->_error('core.api.error.not_allowed'); return; }
        if (!$this->_validCsrf(Profile_Form_Address::class, $params)) { $this->_error('core.api.error.csrf'); return; }
        $linkId = trim((string) ($params['link_id'] ?? ''));

        try {
            $this->_transaction(function () use ($orgId, $linkId) {
                $link = new Tiger_Model_OrgAddress();
                $row  = $linkId !== '' ? $link->findById($linkId) : null;
                if (!$row || (string) $row->org_id !== $orgId) {
                    throw new RuntimeException('Not your org address.');
                }
                $link->softDelete($link->getAdapter()->quoteInto('org_address_id = ?', $linkId));
                $a = new Tiger_Model_Address();
                $a->softDelete($a->getAdapter()->quoteInto('address_id = ?', (string) $row->address_id));
            });
            $this->_ok($orgId, 'profile.address.deleted');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Coordinate normalize: numeric within ±$max → float, else null (mirrors Profile_Service_Address).
     *
     * @param  mixed $v
     * @param  float $max
     * @return float|null
     */
    private function _coord($v, float $max): ?float
    {
        if ($v === null || $v === '' || !is_numeric($v)) { return null; }
        $f = (float) $v;
        return ($f < -$max || $f > $max) ? null : $f;
    }

    /**
     * Success envelope: the refreshed list (generic `addresses` key) + a rotated CSRF token.
     *
     * @param  string $orgId
     * @param  string $messageKey
     * @return void
     */
    protected function _ok($orgId, $messageKey)
    {
        $this->_success(
            [
                'addresses' => (new Tiger_Model_OrgAddress())->withAddress($orgId),
                '_csrf'     => $this->_freshToken(Profile_Form_Address::class),
            ],
            $messageKey
        );
    }
}
