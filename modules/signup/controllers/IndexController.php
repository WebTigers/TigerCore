<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Signup_IndexController — the public signup screen + the email-verify link handler.
 *
 * Thin: `index` renders the form (saving is the /api call Signup_Service_Signup::create);
 * `verify` consumes the emailed link (Signup_Service_Signup::verifyEmail) and activates the
 * account. Renders in the public 'auth' layout. Guest-reachable (modules/signup/configs/acl.ini).
 */
class Signup_IndexController extends Tiger_Controller_Action
{
    /**
     * Initialize the controller and switch to the public 'auth' layout.
     *
     * @return void
     */
    public function init()
    {
        parent::init();
        $this->_helper->layout()->setLayout('auth');
    }

    /**
     * Render the signup form (redirect an already-authenticated user to /admin).
     *
     * @return void
     */
    public function indexAction()
    {
        // Public signup can be turned off (Settings → System → Signup). When it is, the form simply
        // doesn't exist — a clean themed 404 (ErrorController catches the 404-coded action exception).
        if (Signup_Service_Signup::isPublicDisabled()) {
            throw new Zend_Controller_Action_Exception('Not Found', 404);
        }
        if (Zend_Auth::getInstance()->hasIdentity()) {
            $this->_helper->redirector->gotoUrl('/admin');
            return;
        }
        $this->view->title    = 'Create your account';
        $this->view->authWide = true;   // multi-column form needs the wide card
        $this->view->form     = new Signup_Form_Signup();   // core /signup: a new user gets a new org
        $this->view->form->bind();                          // pin that mode server-side for the submit
    }

    /**
     * GET /signup/verify/cid/<id>/code/<token> — activate the account, then send to sign-in.
     *
     * @return void
     */
    public function verifyAction()
    {
        $result = (new Signup_Service_Signup())->verifyEmail(
            (string) $this->getParam('cid', ''),
            (string) $this->getParam('code', '')
        );
        $this->view->title = 'Email verification';
        $this->view->ok    = !empty($result['ok']);
    }
}
