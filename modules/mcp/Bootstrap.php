<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * MCP module bootstrap — Tiger as an MCP server (TIGERMCP.md).
 *
 * The module exists so its controllers are dispatchable and its configs/ (routes.ini → /mcp, acl.ini) are
 * picked up by the core globs. The `/mcp` endpoint is OFF by default (`tiger.mcp.enabled`, gated in
 * Mcp_ServerController); the admin Connect screen (/mcp/admin) turns it on, mints a token, and hands out the
 * client config + the zero-Node stdio bridge. Scoped/org tokens + metering are increment 4 (TIGERMCP.md §11).
 *
 * Extending Zend_Application_Module_Bootstrap gives the module its resource autoloader; the /mcp route rides
 * the module routes.ini ingester (Tiger_Routing_ModuleRoutes).
 */
class Mcp_Bootstrap extends Zend_Application_Module_Bootstrap
{
    /** List the MCP Connect screen under the admin Settings tree (ACL-gated to Mcp_AdminController = admin+). */
    protected function _initAdminSettings()
    {
        if (!class_exists('Tiger_Admin_Settings')) {
            return;
        }
        Tiger_Admin_Settings::register([
            'key'      => 'mcp',
            'label'    => 'mcp.nav.label',
            'icon'     => 'fa-plug',
            'href'     => '/mcp/admin',
            'resource' => 'Mcp_AdminController',
            'order'    => 47,
        ]);
    }
}
