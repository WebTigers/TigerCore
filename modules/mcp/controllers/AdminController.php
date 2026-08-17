<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Mcp_AdminController — the MCP **Connect** screen (admin shell, /mcp/admin; TIGERMCP.md §6). Turn the
 * server on/off, mint an access token, and copy a ready-to-paste MCP client config. Thin: the enable
 * toggle is Mcp_Service_Settings, tokens are the core Tiger_Service_Token, both over /api. `download`
 * serves the zero-Node stdio bridge so a user can drop it on their machine.
 */
class Mcp_AdminController extends Tiger_Controller_Admin_Action
{
    public function init()
    {
        parent::init();
    }

    /** The Connect screen: current state + the values the config blocks are built from. */
    public function indexAction()
    {
        $this->view->title    = 'MCP Server — Tiger Admin';
        $this->view->enabled  = Tiger_Mcp::isEnabled();
        $this->view->mcpUrl   = $this->_baseUrl() . '/mcp';
        $this->view->bridge   = TIGER_CORE_PATH . '/bin/mcp-bridge.php';
        $this->view->protocol = Tiger_Mcp::PROTOCOL_VERSION;
    }

    /** Stream the zero-Node PHP stdio bridge as a download (the user runs it locally for stdio clients). */
    public function downloadAction()
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        $file = TIGER_CORE_PATH . '/bin/mcp-bridge.php';
        $resp = $this->getResponse();
        if (!is_file($file)) { $resp->setHttpResponseCode(404); return; }

        $resp->setHeader('Content-Type', 'text/x-php; charset=UTF-8', true);
        $resp->setHeader('Content-Disposition', 'attachment; filename="mcp-bridge.php"', true);
        echo file_get_contents($file);
    }

    /** The site's public base URL (scheme + host) for the ready-to-paste config. */
    protected function _baseUrl()
    {
        $req    = $this->getRequest();
        $scheme = ((defined('HTTPS') && HTTPS) || $req->getScheme() === 'https') ? 'https' : 'http';
        return $scheme . '://' . $req->getHttpHost();
    }
}
