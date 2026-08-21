<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Agent module bootstrap — TigerAgent, the built-in AI agent (TIGERAGENT.md).
 *
 * A first-party module: the aside UI + its /api services (send/approve/history), the settings
 * screen, and the ACL that expresses the capability tiers. The heavy lifting — the turn loop,
 * the response contract, the Forge, the provider adapters — lives in Tiger_Agent_* (library),
 * because it's platform substrate the module merely surfaces.
 *
 * The aside itself is injected app-shell-wide by the PUMA admin layout (gated on
 * Tiger_Agent::isAvailable()), so it persists across navigation and is permission-managed —
 * not something each screen opts into.
 *
 * Extending Zend_Application_Module_Bootstrap gives the module its resource autoloader, so
 * Agent_Service_* / Agent_Form_* load by convention; configs/acl.ini + languages/ are picked
 * up by the core globs.
 */
class Agent_Bootstrap extends Zend_Application_Module_Bootstrap
{
    /** List TigerAgent under the admin Settings tree (ACL-gated to Agent_AdminController = admin+). */
    protected function _initAdminSettings()
    {
        if (!class_exists('Tiger_Admin_Settings')) {
            return;
        }
        Tiger_Admin_Settings::register([
            'key'      => 'agent',
            'label'    => 'agent.nav.label',
            'icon'     => 'fa-robot',
            'href'     => '/agent/admin',
            'resource' => 'Agent_AdminController',
            'order'    => 45,
        ]);
        // The Skills manager (browse/install/toggle/remove agent skills) — TIGERSKILLS.md.
        Tiger_Admin_Settings::register([
            'key'      => 'agent-skills',
            'label'    => 'agent.nav.skills',
            'icon'     => 'fa-wand-magic-sparkles',
            'href'     => '/admin/settings/agent/skills',   // pretty alias (routes.ini); /agent/skills still works
            'resource' => 'Agent_SkillsController',
            'order'    => 46,
        ]);
        // Outbound MCP: external servers whose tools the agent may call (TIGERMCP.md §9).
        Tiger_Admin_Settings::register([
            'key'      => 'agent-mcp',
            'label'    => 'agent.nav.mcp',
            'icon'     => 'fa-diagram-project',
            'href'     => '/agent/mcp',
            'resource' => 'Agent_McpController',
            'order'    => 48,
        ]);
    }
}
