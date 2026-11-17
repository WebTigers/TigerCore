<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Agent_Form_Settings — the TigerAgent settings form (provider + model + BYO key).
 *
 * The API key is never required on save (a blank field keeps the stored secret), and the
 * provider is validated against the live roster in the service, not here, so adding a
 * provider needs no form change.
 *
 * @api
 */
class Agent_Form_Settings extends Agent_Form_AdminWrite
{
    protected function elements(): array
    {
        return [
            ['text', 'provider', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'attribs'    => ['class' => 'form-select'],
            ]],
            ['text', 'model', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'attribs'    => ['class' => 'form-control', 'placeholder' => $this->_t('agent.settings.model.ph')],
            ]],
            ['password', 'api_key', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'attribs'    => ['class' => 'form-control', 'autocomplete' => 'off',
                                 'placeholder' => $this->_t('agent.settings.key.ph')],
            ]],
            ['text', 'mode_max', [
                'required' => false,
                'filters'  => ['StringTrim'],
                'attribs'  => ['class' => 'form-select'],
            ]],
        ];
    }
}
