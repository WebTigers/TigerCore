<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Agent_Form_SkillKey — toggle or remove one installed skill by key (TIGER-528).
 *
 * The key must be present (as before); whether it names an INSTALLED skill is the service's check.
 * `active` is only read by toggle.
 *
 * @api
 */
class Agent_Form_SkillKey extends Agent_Form_AdminWrite
{
    /**
     * The declared schema.
     *
     * @return array<int,array> [type, name, options] triples
     */
    protected function elements(): array
    {
        return [
            ['hidden', 'key', [
                'required'   => true,
                'filters'    => ['StringTrim'],
                'validators' => [['StringLength', false, [1, 255]]],
            ]],
            ['checkbox', 'active', [
                'required' => false,
            ]],
        ];
    }
}
