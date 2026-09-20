<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Newsletter_IndexController — the public landing pages the double-opt-in emails link to.
 *
 * Thin: `confirm` redeems the opt-in token (Newsletter_Service_Subscribe::confirm) and `unsubscribe`
 * opts out (::unsubscribe), each then rendering a short themed page. Guest-reachable
 * (modules/newsletter/configs/acl.ini); routed under /newsletter by configs/routes.ini.
 */
class Newsletter_IndexController extends Tiger_Controller_Action
{
    /** GET /newsletter/confirm/token/<token> — record consent, then say so. */
    public function confirmAction()
    {
        $svc = new Newsletter_Service_Subscribe();
        $svc->confirm(['token' => (string) $this->getParam('token', '')]);
        $this->view->title = 'Newsletter';
        $this->view->ok    = (int) $svc->getResponse()->result === 1;
    }

    /** GET /newsletter/unsubscribe/token/<token> — opt out, then say so. */
    public function unsubscribeAction()
    {
        $svc = new Newsletter_Service_Subscribe();
        $svc->unsubscribe(['token' => (string) $this->getParam('token', '')]);
        $this->view->title = 'Newsletter';
        $this->view->ok    = (int) $svc->getResponse()->result === 1;
    }
}
