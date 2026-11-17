<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Agent_Service_Mcp — the /api behind the MCP **Connections** screen (outbound: the agent consuming external
 * MCP servers; TIGERMCP.md §9). Admin-gated, and scoped to the CALLER's org (TIGER-511: connections hold
 * credentials, so an org lists, tests and edits only its own). Register/list/remove a connection ({label, url, token}) and
 * TEST it (list its tools). The token is stored encrypted by Tiger_Agent_Mcp and never round-trips to the
 * browser. This service's ACL is ALSO the gate on whether the agent may USE connected tools
 * (Tiger_Agent_Mcp::allowedForRole), so tool access can't outrun who may connect.
 *
 * @api
 */
class Agent_Service_Mcp extends Tiger_Service_Service
{
    /**
     * The configured connections (token replaced by a has_token flag).
     *
     * @param  array $params the request payload
     * @apiRequest none
     * @return void
     */
    public function connections(array $params): void
    {
        if (!$this->_isAllowed('connections')) { $this->_error('core.api.error.not_allowed'); return; }
        $this->_success(['connections' => Tiger_Agent_Mcp::forAdmin($this->_org())]);
    }

    /** The caller's org — the only list this service reads or writes ('' = none, so nothing). */
    private function _org(): string
    {
        return (string) ($this->_org_id ?? '');
    }

    /**
     * Create or update a connection (a blank token keeps the existing one).
     *
     * @param  array $params the request payload
     * @apiRequest Agent_Form_Mcp
     * @apiParam string id The connection to update. Empty to create.
     * @apiParam string label required A display name for the connection.
     * @apiParam string url required The remote MCP server URL (http or https).
     * @apiParam string token The Bearer token for the server. Blank keeps the stored one.
     * @apiParam boolean enabled Whether the agent may use this connection's tools.
     * @return void
     */
    public function save(array $params): void
    {
        if (!$this->_isAllowed('save')) { $this->_error('core.api.error.not_allowed'); return; }
        // Connections hold credentials, so they belong to the caller's org (TIGER-511) — no org, no write.
        if ($this->_org() === '') { $this->_error('core.api.error.not_allowed'); return; }

        // The form carries the CSRF token and the label/url rules (their keys: agent.mcp.bad_label/bad_url).
        $form = new Agent_Form_Mcp();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        $url   = (string) $form->getValue('url');
        $label = (string) $form->getValue('label');

        try {
            $id = Tiger_Agent_Mcp::save([
                'id'      => (string) $form->getValue('id'),
                'label'   => $label,
                'url'     => $url,
                'token'   => (string) ($params['token'] ?? ''),
                'enabled' => !empty($params['enabled']) && $params['enabled'] !== '0' && $params['enabled'] !== 'false',
            ], $this->_org());
            Tiger_Agent_Mcp::tools(true, $this->_org());   // re-scan so the agent sees the new/changed connection's tools
            $this->_success(['id' => $id, 'connections' => Tiger_Agent_Mcp::forAdmin($this->_org())], 'agent.mcp.saved');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Remove a connection.
     *
     * @param  array $params the request payload
     * @apiRequest Agent_Form_McpId
     * @apiParam string id The connection to remove.
     * @return void
     */
    public function remove(array $params): void
    {
        if (!$this->_isAllowed('remove')) { $this->_error('core.api.error.not_allowed'); return; }
        $form = new Agent_Form_McpId();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        Tiger_Agent_Mcp::remove((string) $form->getValue('id'), $this->_org());
        $this->_success(['connections' => Tiger_Agent_Mcp::forAdmin($this->_org())], 'agent.mcp.removed');
    }

    /**
     * Test a connection — list its tools live.
     *
     * @param  array $params the request payload
     * @apiRequest Agent_Form_McpId
     * @apiParam string id required The connection to test.
     * @return void
     */
    public function test(array $params): void
    {
        if (!$this->_isAllowed('test')) { $this->_error('core.api.error.not_allowed'); return; }
        // A live outbound call with the stored credential — gated by the token like any write.
        $form = new Agent_Form_McpId();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        $conn = Tiger_Agent_Mcp::connection((string) $form->getValue('id'), $this->_org());
        if ($conn === null) { $this->_error('agent.mcp.not_found'); return; }

        $tools = Tiger_Agent_Mcp_Client::listTools($conn);
        $this->_success([
            'count' => count($tools),
            'tools' => array_map(static fn($t) => ['name' => $t['name'], 'description' => $t['description']], $tools),
        ], null);
    }
}
