<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Register_VerifyController — the two PUBLIC endpoints of registration:
 *   - domainAction (routed to /.well-known/tiger-verify.txt) auto-serves the domain-control token so the
 *     registry can confirm the site owns its domain with no manual upload (near one-click domain verify);
 *   - emailAction (/register/verify/email/token/<t>) is the admin's email verification magic link.
 * Both guest (a token proves intent; no session to gate).
 */
class Register_VerifyController extends Tiger_Controller_Action
{
    /** Serve the challenge token at /.well-known/tiger-verify.txt (only while a registration is pending). */
    public function domainAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        $token = (string) (new Tiger_Model_Config())->get(Tiger_Model_Config::SCOPE_GLOBAL, '', 'register.verify_token');
        if ($token === '' || !Register_Service_Status::hasStarted() || Register_Service_Status::isDomainVerified()) {
            $this->getResponse()->setHttpResponseCode(404)->setBody('');
            return;
        }
        $this->getResponse()->setHeader('Content-Type', 'text/plain; charset=utf-8', true)->setBody($token);
    }

    /** The email verification magic link — confirm the admin contact email. */
    public function emailAction()
    {
        $this->view->title = 'Verify your site';

        $token  = (string) $this->getRequest()->getParam('token', '');
        $cfg    = new Tiger_Model_Config();
        $stored = (string) $cfg->get(Tiger_Model_Config::SCOPE_GLOBAL, '', 'register.email_token');
        $exp    = (int) $cfg->get(Tiger_Model_Config::SCOPE_GLOBAL, '', 'register.email_expires');

        $ok = $token !== '' && $stored !== '' && $exp >= time() && hash_equals($stored, hash('sha256', $token));
        if ($ok) {
            $cfg->set(Tiger_Model_Config::SCOPE_GLOBAL, '', 'register.email_verified', '1');
            $cfg->set(Tiger_Model_Config::SCOPE_GLOBAL, '', 'register.email_token', '');
        }
        $this->view->ok = $ok;
    }
}
