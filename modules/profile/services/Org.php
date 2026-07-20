<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Profile_Service_Org — the /api service behind the org profile Basic tab.
 *
 * Admin-gated and STRICTLY scoped to the CURRENT org ($this->_org_id) — it never takes an org_id
 * from the payload, so an admin can only edit the org they're signed into (arbitrary-tenant edits
 * are Access_Service_Org's job). Saves the org's identity (name + unique slug); everything richer
 * (logo, contacts, addresses) lives in sibling tabs/services.
 *
 * @api
 */
class Profile_Service_Org extends Tiger_Service_Service
{
    /**
     * Update the current org's name + slug.
     *
     * @param  array $params name, slug
     * @return void
     */
    public function save(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $orgId = (string) $this->_org_id;
        if ($orgId === '') { $this->_error('core.api.error.not_allowed'); return; }

        $form = new Profile_Form_OrgProfile();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }

        $name   = trim((string) $form->getValue('name'));
        $slugIn = trim((string) $form->getValue('slug'));
        $slug   = $this->_slugify($slugIn !== '' ? $slugIn : $name);
        if ($slug === '') { $this->_error('profile.org.slug_required', ['field' => 'slug']); return; }

        $org = new Tiger_Model_Org();
        if ($org->slugTaken($slug, $orgId)) { $this->_error('profile.org.slug_taken', ['field' => 'slug']); return; }

        try {
            $this->_transaction(function () use ($org, $name, $slug, $orgId) {
                $org->update(['name' => $name, 'slug' => $slug], $org->getAdapter()->quoteInto('org_id = ?', $orgId));
            });
            $this->_success(['name' => $name, 'slug' => $slug], 'profile.org.saved');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * lowercase, hyphen-joined, ASCII slug (mirrors Access_Service_Org's rule).
     *
     * @param  string $text
     * @return string
     */
    private function _slugify($text): string
    {
        $s = strtolower(trim((string) $text));
        $s = preg_replace('/[^a-z0-9]+/', '-', $s);
        return trim((string) $s, '-');
    }
}
