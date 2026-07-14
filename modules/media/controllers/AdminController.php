<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Media_AdminController — the Media Library admin settings screen (rendered in the admin shell).
 *
 * Thin: the settings action prefills Media_Form_Settings from the resolved config (org → global →
 * default) and renders; the save is an /api call to Media_Service_Settings. Built per ADMIN.md.
 */
class Media_AdminController extends Tiger_Controller_Admin_Action
{
    /** Admin layout comes from the base; keep the explicit init cascade. */
    public function init()
    {
        parent::init();
    }

    /** Media settings: filename obfuscation per file visibility. */
    public function settingsAction()
    {
        $orgId = $this->_orgId();
        $form  = new Media_Form_Settings();
        $form->populate([
            'obfuscate_public'  => Tiger_Model_Media::obfuscateEnabled(Tiger_Model_Media::VISIBILITY_PUBLIC,  $orgId) ? '1' : '0',
            'obfuscate_private' => Tiger_Model_Media::obfuscateEnabled(Tiger_Model_Media::VISIBILITY_PRIVATE, $orgId) ? '1' : '0',
        ]);

        $this->view->title = 'Media Settings — Tiger Admin';
        $this->view->form  = $form;
    }

    /** The acting org id ('' when org-less / global). */
    protected function _orgId()
    {
        $idn = Zend_Auth::getInstance()->getIdentity();
        return ($idn && !empty($idn->org_id)) ? (string) $idn->org_id : '';
    }
}
