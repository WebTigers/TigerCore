<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Agent_Form_Mode — the org's auto-mode ceiling (governance), saved alone (TIGER-528).
 *
 * No InArray validator on purpose: the service has always saved an unrecognised value as `auto`
 * rather than refusing it, and this conversion keeps that behaviour.
 *
 * @api
 */
class Agent_Form_Mode extends Agent_Form_AdminWrite
{
    /**
     * The declared schema.
     *
     * @return array<int,array> [type, name, options] triples
     */
    protected function elements(): array
    {
        return [
            ['text', 'mode_max', [
                'required' => false,
                'filters'  => ['StringTrim'],
                'attribs'  => ['class' => 'form-select'],
            ]],
        ];
    }
}
