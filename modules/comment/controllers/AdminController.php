<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Comment_AdminController — the moderation queue.
 *
 * Thin, per ADMIN.md: it renders the screen and the grid loads from `/api`
 * (`Comment_Service_Comment::datatable`). Every moderation action is an `/api` call too.
 */
class Comment_AdminController extends Tiger_Controller_Admin_Action
{
    /** Admin shell comes from the base; keep the explicit cascade hook. */
    public function init()
    {
        parent::init();
    }

    /** The queue. A disabled feature 404s rather than showing an empty screen that implies it works. */
    public function indexAction()
    {
        if (!class_exists('Tiger_Comment') || !Tiger_Comment::isEnabled()) {
            throw new Zend_Controller_Action_Exception('Comments are disabled', 404);
        }

        $this->view->title    = 'Comments — Tiger Admin';
        $this->view->statuses = Tiger_Model_Comment::STATUSES;

        // The AI spam control only APPEARS when there is a live agent to run it — an always-visible
        // toggle that silently does nothing is worse than no toggle.
        $this->view->agentAvailable = Tiger_Comment_Spam::agentAvailable();
        $this->view->spamAgent      = Tiger_Comment_Spam::agentEnabled();
    }
}
