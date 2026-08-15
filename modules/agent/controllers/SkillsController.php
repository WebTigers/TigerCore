<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Agent_SkillsController — the Skills manager screen (admin shell, /agent/skills). Thin: renders the shell;
 * search / install / toggle / remove / view-source all go through Agent_Service_Skills over /api.
 * ACL-gated admin+ (configs/acl.ini). See TIGERSKILLS.md.
 */
class Agent_SkillsController extends Tiger_Controller_Admin_Action
{
    public function init()
    {
        parent::init();
    }

    /** Render the Skills manager (search catalog + installed list). */
    public function indexAction()
    {
        $this->view->title = 'Agent Skills — Tiger Admin';
    }
}
