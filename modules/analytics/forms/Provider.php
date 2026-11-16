<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Analytics_Form_Provider — the "Choose your analytics provider" step. One field: the reporting
 * provider, which must be a provider registered with Tiger_Analytics ('' = back to automatic).
 *
 * It rides on the SAME CSRF token as the settings form on that screen (a shared salt — two salts on one
 * page would mint two tokens and the page renders one), so the view renders `_csrf` once.
 *
 * @api
 */
class Analytics_Form_Provider extends Tiger_Form
{
    /**
     * Declare the form's elements.
     *
     * @return array the element schema
     */
    protected function elements(): array
    {
        $options = ['' => ''];
        foreach (Tiger_Analytics::names() as $name) {
            $options[$name] = $name;
        }
        return [
            ['select', 'provider', [
                'required'     => false,
                'multiOptions' => $options,
            ]],
        ];
    }

    /**
     * The salt every analytics settings form shares (one rendered token serves the whole screen).
     *
     * @return string
     */
    protected function csrfSalt(): string
    {
        return 'Analytics';
    }
}
