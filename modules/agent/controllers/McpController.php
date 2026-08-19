<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Agent_McpController — the MCP **Connections** screen (admin shell, /agent/mcp; TIGERMCP.md §9). Register
 * external MCP servers whose tools the in-app agent may call. Thin: register/list/remove/test all go through
 * Agent_Service_Mcp over /api.
 */
class Agent_McpController extends Tiger_Controller_Admin_Action
{
    public function init()
    {
        parent::init();
    }

    public function indexAction()
    {
        $this->view->title = 'MCP Connections — Tiger Admin';
    }
}
