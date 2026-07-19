<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Agent_Form_Approve — validates a human approval of proposed write actions (run + CSRF).
 *
 * @api
 */
class Agent_Form_Approve extends Tiger_Form
{
    protected function elements(): array
    {
        return [
            ['text', 'run_id', [
                'required'   => true,
                'filters'    => ['StringTrim'],
                'validators' => [['StringLength', false, [1, 36]]],
                'attribs'    => ['class' => 'form-control'],
            ]],
        ];
    }
}
