<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * MCP module bootstrap — Tiger as an MCP server (TIGERMCP.md).
 *
 * Increment 1 ships just the server: the module exists so its controller is dispatchable and its
 * configs/ (routes.ini → /mcp, acl.ini → public controller) are picked up by the core globs. The endpoint
 * itself is OFF by default (`tiger.mcp.enabled`, gated in Mcp_ServerController). The admin Connect screen +
 * the enable toggle + the zero-Node stdio bridge come in increment 3; scoped tokens + metering in
 * increment 4 (TIGERMCP.md §11).
 *
 * Extending Zend_Application_Module_Bootstrap gives the module its resource autoloader; the /mcp route rides
 * the module routes.ini ingester (Tiger_Routing_ModuleRoutes).
 */
class Mcp_Bootstrap extends Zend_Application_Module_Bootstrap
{
}
