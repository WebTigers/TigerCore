<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Agent_Form_AgentDelete — delete one agent from the org's registry (TIGER-528).
 *
 * An id-only write; the form exists to carry the CSRF token. An unknown id is the service's
 * `agent.agents.error.not_found`, so the field is only declared, not required here.
 *
 * @api
 */
class Agent_Form_AgentDelete extends Agent_Form_AdminWrite
{
    /**
     * The declared schema.
     *
     * @return array<int,array> [type, name, options] triples
     */
    protected function elements(): array
    {
        return [
            ['hidden', 'agent_id', [
                'required' => false,
                'filters'  => ['StringTrim'],
            ]],
        ];
    }
}
