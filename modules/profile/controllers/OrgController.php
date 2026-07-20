<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Profile_OrgController — the admin's own-organization profile screen (/profile/org).
 *
 * The org-side twin of Profile_IndexController: same extensible tab shell (Tiger_Profile_Tabs,
 * CONTEXT_ORG), but gated to role `admin` and scoped to the CURRENT org ($identity->org_id) — an
 * admin edits the org they're signed into, never an arbitrary one (that's Access_OrgController's job,
 * which manages every tenant). Thin per ADMIN.md: it renders the shell pre-filled from the org row;
 * every save is an /api call to a Profile_Service_Org*.
 */
class Profile_OrgController extends Tiger_Controller_Admin_Action
{
    /**
     * Admin shell (layout) comes from the base; keep the explicit init cascade.
     *
     * @return void
     */
    public function init()
    {
        parent::init();
    }

    /**
     * Render the org profile tabs pre-filled from the signed-in identity's current org.
     *
     * @return void
     */
    public function indexAction()
    {
        $identity = Zend_Auth::getInstance()->getIdentity();
        $orgId    = is_object($identity) ? (string) ($identity->org_id ?? '') : '';
        $org      = $orgId !== '' ? (new Tiger_Model_Org())->findById($orgId) : null;

        // Current logo (option tier → media_id → URL); blank when none set.
        $logoUrl = '';
        if ($orgId !== '') {
            $mediaId = (new Tiger_Model_Option())->get(Tiger_Model_Option::SCOPE_ORG, $orgId, Profile_Service_OrgLogo::OPTION_KEY);
            if ($mediaId) {
                $mrow = (new Tiger_Model_Media())->findById($mediaId);
                if ($mrow) { $logoUrl = (new Tiger_Model_Media())->url($mrow->toArray()); }
            }
        }

        $cfg          = Zend_Registry::get('Zend_Config');
        $phoneDefault = ($cfg->tiger && $cfg->tiger->profile && $cfg->tiger->profile->phone)
            ? (string) $cfg->tiger->profile->phone->get('default_country') : 'US';

        // Contacts + Addresses reuse the shared collection views (index/_contacts, index/_addresses);
        // they're context-neutral (generic `link_id`), driven by the *Svc names below to hit the
        // org-scoped services. Same layout flags as the user profile so intl-tel + address autocomplete
        // load here too.
        $this->view->useIntlTel = true;
        $this->view->useAddress = true;
        $this->view->title = Zend_Registry::get('Zend_Translate')->translate('profile.org.title') . ' — Tiger';
        $this->view->tabs  = Tiger_Profile_Tabs::all(Tiger_Profile_Tabs::CONTEXT_ORG);
        $this->view->model = [
            'form'         => new Profile_Form_OrgProfile(),
            'orgName'      => $org && isset($org->name) ? (string) $org->name : '',
            'orgSlug'      => $org && isset($org->slug) ? (string) $org->slug : '',
            'logoUrl'      => $logoUrl,

            'contactForm'  => new Profile_Form_Contact(),
            'contacts'     => $orgId !== '' ? (new Tiger_Model_OrgContact())->withContact($orgId) : [],
            'contactTypes' => Tiger_Profile_Types::contact(),
            'contactSvc'   => 'OrgContact',   // exact case: /api builds {Module}_Service_{ucfirst(service)}
            'phoneDefault' => $phoneDefault ?: 'US',

            'addressForm'  => new Profile_Form_Address(),
            'addresses'    => $orgId !== '' ? (new Tiger_Model_OrgAddress())->withAddress($orgId) : [],
            'addressTypes' => Tiger_Profile_Types::address(),
            'addressSvc'   => 'OrgAddress',
            'countries'    => Tiger_I18n_Country::grouped(),
        ];
    }
}
