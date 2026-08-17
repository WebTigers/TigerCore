<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Mcp_Service_Settings — the /api behind the MCP Connect screen: turn the `/mcp` endpoint on/off. Thin +
 * admin-gated; the value lives in the `config` live-override tier (effective next request, no deploy).
 * Token minting reuses the core Tiger_Service_Token (module=tiger, service=token); this service owns only
 * the enable flag.
 *
 * @api
 */
class Mcp_Service_Settings extends Tiger_Service_Service
{
    /**
     * Enable or disable the MCP server (`tiger.mcp.enabled`).
     *
     * @param  array $params {enabled: bool-ish}
     * @return void
     */
    public function save(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $on = !empty($params['enabled']) && $params['enabled'] !== '0' && $params['enabled'] !== 'false';
        try {
            (new Tiger_Model_Config())->set(Tiger_Model_Config::SCOPE_GLOBAL, '', Tiger_Mcp::CONFIG_ENABLED, $on ? '1' : '0');
            $this->_success(['enabled' => $on], $on ? 'mcp.settings.enabled' : 'mcp.settings.disabled');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }
}
