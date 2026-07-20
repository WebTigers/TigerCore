<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Profile_Service_OrgContact — the /api service behind the ORG profile Contacts tab.
 *
 * The org twin of Profile_Service_Contact: identical shape, but admin-gated and scoped to the CURRENT
 * org ($this->_org_id) — never an org_id from the payload. Reuses Profile_Form_Contact and the shared
 * Contacts view (which speaks the generic `link_id`), so the only differences are the owner scope +
 * the admin gate. `is_primary` is single-per-collection; every response rotates the CSRF token.
 *
 * @api
 */
class Profile_Service_OrgContact extends Profile_Service_Base
{
    /**
     * Create or update one of the current org's contacts (edit when link_id is present).
     *
     * @param  array $params type, value, is_primary, phone_country, link_id (blank = create)
     * @return void
     */
    public function save(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $orgId = (string) $this->_org_id;
        if ($orgId === '') { $this->_error('core.api.error.not_allowed'); return; }

        $form = new Profile_Form_Contact();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }

        $types = Tiger_Profile_Types::contact();
        $type  = strtolower(trim((string) $form->getValue('type')));
        if (!array_key_exists($type, $types)) { $this->_error('profile.contact.bad_type', ['field' => 'type']); return; }
        $value   = trim((string) $form->getValue('value'));
        $primary = (bool) $form->getValue('is_primary');
        $linkId  = trim((string) $form->getValue('link_id'));

        $contactType = null;
        if ($type === 'phone') {
            if (!preg_match('/^\+[1-9]\d{6,14}$/', $value)) {
                $this->_error('profile.contact.bad_phone', ['field' => 'value']); return;
            }
            $contactType = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $form->getValue('phone_country'))) ?: null;
        }

        try {
            $this->_transaction(function () use ($orgId, $type, $contactType, $value, $primary, $linkId) {
                $link = new Tiger_Model_OrgContact();
                if ($linkId !== '') {
                    $row = $link->findById($linkId);
                    if (!$row || (string) $row->org_id !== $orgId) {
                        throw new RuntimeException('Not your org contact.');
                    }
                    (new Tiger_Model_Contact())->update(
                        ['kind' => $type, 'type' => $contactType, 'value' => $value],
                        $link->getAdapter()->quoteInto('contact_id = ?', (string) $row->contact_id)
                    );
                    $link->update(['is_primary' => $primary ? 1 : 0], $link->getAdapter()->quoteInto('org_contact_id = ?', $linkId));
                    $keepId = $linkId;
                } else {
                    $contactId = (new Tiger_Model_Contact())->insert(['kind' => $type, 'type' => $contactType, 'value' => $value]);
                    $keepId    = $link->insert(['org_id' => $orgId, 'contact_id' => $contactId, 'is_primary' => $primary ? 1 : 0]);
                }
                if ($primary) {
                    $this->_soloPrimary($link, 'org_contact_id', 'org_id', $orgId, (string) $keepId);
                }
            });
            $this->_ok($orgId, 'profile.contact.saved');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Delete one of the current org's contacts (unlink + soft-delete the channel).
     *
     * @param  array $params link_id
     * @return void
     */
    public function delete(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $orgId = (string) $this->_org_id;
        if ($orgId === '') { $this->_error('core.api.error.not_allowed'); return; }
        if (!$this->_validCsrf(Profile_Form_Contact::class, $params)) { $this->_error('core.api.error.csrf'); return; }
        $linkId = trim((string) ($params['link_id'] ?? ''));

        try {
            $this->_transaction(function () use ($orgId, $linkId) {
                $link = new Tiger_Model_OrgContact();
                $row  = $linkId !== '' ? $link->findById($linkId) : null;
                if (!$row || (string) $row->org_id !== $orgId) {
                    throw new RuntimeException('Not your org contact.');
                }
                $link->softDelete($link->getAdapter()->quoteInto('org_contact_id = ?', $linkId));
                $c = new Tiger_Model_Contact();
                $c->softDelete($c->getAdapter()->quoteInto('contact_id = ?', (string) $row->contact_id));
            });
            $this->_ok($orgId, 'profile.contact.deleted');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Success envelope: the refreshed list (generic `contacts` key, shared with the user tab) + a
     * rotated CSRF token.
     *
     * @param  string $orgId
     * @param  string $messageKey
     * @return void
     */
    protected function _ok($orgId, $messageKey)
    {
        $this->_success(
            [
                'contacts' => (new Tiger_Model_OrgContact())->withContact($orgId),
                '_csrf'    => $this->_freshToken(Profile_Form_Contact::class),
            ],
            $messageKey
        );
    }
}
