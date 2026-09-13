<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Message_IndexController — the inbox, on the /account surface (TIGER-114).
 *
 * Shells only. Every read and every mutation is a /api call from the page's JavaScript to
 * Message_Service_Message, so this screen and an agent drive the same surface and the same rules.
 * The controller's whole job is to hand the view the two facts it cannot get from the browser:
 * whether this person may compose, and which view the URL asked for.
 *
 * Routes (configs/routes.ini): /message (inbox) · /message/view/archived · /message/view/sent ·
 *         /message/compose · /message/compose/reply/<id> · /message/blocked
 */
class Message_IndexController extends Tiger_Controller_Account_Action
{
    /** The inbox, archive or sent list — one screen, three filters. */
    public function indexAction()
    {
        $view = (string) $this->getParam('view', 'inbox');
        $this->view->listView = in_array($view, ['archived', 'sent'], true) ? $view : 'inbox';
        $this->view->maySend  = $this->_maySend();
        $this->view->headTitle($this->view->t('message.nav.label'));
    }

    /** Compose. Admins always; everyone else only when the install has enabled it. */
    public function composeAction()
    {
        if (!$this->_maySend()) {
            $this->_helper->redirector->gotoUrl('/message');
            return;
        }
        $this->view->replyTo = trim((string) $this->getParam('reply', ''));
        $this->view->headTitle($this->view->t('message.compose.title'));
    }

    /** People I've blocked. */
    public function blockedAction()
    {
        $this->view->headTitle($this->view->t('message.blocked.title'));
    }

    /** Same rule the service applies, so the Compose button never offers what the API will refuse. */
    protected function _maySend()
    {
        $identity = Zend_Auth::getInstance()->getIdentity();
        $role     = (string) ($identity->role ?? 'guest');
        return Tiger_Message::isAtLeastAdmin($role) || Tiger_Message::isUserToUserEnabled();
    }
}
