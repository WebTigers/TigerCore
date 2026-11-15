<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Email_AdminController — the transactional-email authoring screens (Admin → Settings → Email Templates).
 *
 * Thin per the controller rule: it reads the registry + the stored override and renders; every mutation
 * (save/reset) and the live preview go through the /api service Email_Service_Template. ACL-gated admin+
 * (modules/email/configs/acl.ini).
 */
class Email_AdminController extends Tiger_Controller_Admin_Action
{
    /**
     * Set up the admin shell — the layout comes from the base; keep the explicit init cascade.
     *
     * @return void
     */
    public function init()
    {
        parent::init();
    }

    /**
     * The list of every registered email event — the DataTables shell; rows load from the /api service.
     *
     * @return void
     */
    public function indexAction()
    {
        $this->view->title         = 'Email Templates — Tiger Admin';
        $this->view->useDataTables = true;   // the layout loads jQuery + DataTables only when this is set
    }

    /**
     * The editor for one template key — seeds the view with the registry def + the stored override.
     *
     * @return void
     */
    public function editAction()
    {
        $key = (string) $this->getParam('key', '');
        $defs = Tiger_Mail::registeredTemplates();
        if ($key === '' || !isset($defs[$key])) {
            $this->_helper->redirector->gotoUrl('/email/admin');
            return;
        }

        $def = $defs[$key];
        // The caller's org override (TIGER-511) — the tier Email_Service_Template saves. With none of
        // its own, the editor starts from the override the org inherits (the install-wide one).
        $scope = Tiger_Model_Config::settingScope()[1];
        $model = new Tiger_Model_EmailTemplate();
        $own   = $model->byKey($key, $scope);
        $row   = $own ?: $model->resolve($key, $scope);

        $form = new Email_Form_Template();
        $form->populate([
            'template_key' => $key,
            'subject'      => $row ? (string) $row->subject : '',
            'body'         => $row ? (string) $row->body : '',
            'scope'        => '',   // informational only — the service always saves at the caller's org
        ]);

        $this->view->title        = 'Edit Template — Tiger Admin';
        $this->view->form         = $form;
        $this->view->def          = $def;
        $this->view->templateKey  = $key;
        $this->view->customized   = (bool) $own;
    }
}
