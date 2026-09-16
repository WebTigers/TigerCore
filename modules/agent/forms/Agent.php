<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Agent_Form_Agent — one row in the agent registry (name + persona + provider/model + BYO key).
 *
 * The API key is never required (a blank field on save keeps the stored secret, the write-only
 * convention the single-agent form used); the provider is validated against the live roster in
 * the service, not here, so adding a provider needs no form change. `agent_id` blank = create.
 *
 * @api
 */
class Agent_Form_Agent extends Tiger_Form
{
    protected function elements(): array
    {
        return [
            ['text', 'agent_id', [
                'required' => false,
                'filters'  => ['StringTrim'],
            ]],
            ['text', 'name', [
                'required'   => true,
                'filters'    => ['StringTrim'],
                'validators' => [['StringLength', false, [1, 191]]],
                'attribs'    => ['class' => 'form-control'],
            ]],
            ['textarea', 'persona', [
                'required' => false,
                'filters'  => ['StringTrim'],
                'attribs'  => ['class' => 'form-control', 'rows' => 3],
            ]],
            ['text', 'provider', [
                'required' => false,
                'filters'  => ['StringTrim'],
            ]],
            ['text', 'model', [
                'required' => false,
                'filters'  => ['StringTrim'],
            ]],
            ['password', 'api_key', [
                'required' => false,
                'filters'  => ['StringTrim'],
                'attribs'  => ['autocomplete' => 'off'],
            ]],
        ];
    }
}
