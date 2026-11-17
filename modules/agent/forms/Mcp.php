<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Agent_Form_Mcp — create or update an outbound MCP connection (TIGER-528).
 *
 * Reproduces the service's former hand-checks as field validators, keeping their message keys: a
 * `label` is required (`agent.mcp.bad_label`) and the `url` must be http(s) (`agent.mcp.bad_url`).
 * The `token` is write-only and optional — a blank one keeps the stored secret.
 *
 * @api
 */
class Agent_Form_Mcp extends Agent_Form_AdminWrite
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
            ['text', 'label', [
                'required'   => true,
                'filters'    => ['StringTrim'],
                'validators' => [
                    ['NotEmpty', true, ['messages' => [Zend_Validate_NotEmpty::IS_EMPTY => 'agent.mcp.bad_label']]],
                ],
                'attribs'    => ['class' => 'form-control'],
            ]],
            ['text', 'url', [
                'required'   => true,
                'filters'    => ['StringTrim'],
                'validators' => [
                    ['NotEmpty', true, ['messages' => [Zend_Validate_NotEmpty::IS_EMPTY => 'agent.mcp.bad_url']]],
                    ['Regex', true, ['pattern' => '#^https?://#i', 'messages' => [Zend_Validate_Regex::NOT_MATCH => 'agent.mcp.bad_url']]],
                ],
                'attribs'    => ['class' => 'form-control'],
            ]],
            ['password', 'token', [
                'required' => false,
                'attribs'  => ['class' => 'form-control', 'autocomplete' => 'new-password'],
            ]],
            ['checkbox', 'enabled', [
                'required' => false,
            ]],
        ];
    }
}
