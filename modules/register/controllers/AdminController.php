<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Register_AdminController — Settings → Registration: a full-page view of the same optional registration the
 * dashboard widget offers. Thin; every action is an /api call to Register_Service_Registration.
 */
class Register_AdminController extends Tiger_Controller_Action
{
    public function init() { parent::init(); }

    /** The Registration settings screen. */
    public function registrationAction()
    {
        $this->view->title = 'Registration — Tiger Admin';
        $this->view->state = Register_Service_Status::state();
        $form = new Register_Form_Register();
        if (!empty($this->view->state['email'])) { $form->populate(['email' => $this->view->state['email']]); }
        $this->view->form = $form;
    }
}
