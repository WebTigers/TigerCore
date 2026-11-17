<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Agent_Form_McpId — remove or test one MCP connection by id (TIGER-528).
 *
 * Remove is a write; test makes a live outbound call with the stored credential — both carry the
 * CSRF token so a cross-site page can't drive either from a signed-in admin's browser. An unknown id
 * keeps its existing service behaviour (remove is a no-op, test reports `agent.mcp.not_found`).
 *
 * @api
 */
class Agent_Form_McpId extends Agent_Form_AdminWrite
{
    /**
     * The declared schema.
     *
     * @return array<int,array> [type, name, options] triples
     */
    protected function elements(): array
    {
        return [
            ['hidden', 'id', [
                'required' => false,
                'filters'  => ['StringTrim'],
            ]],
        ];
    }
}
