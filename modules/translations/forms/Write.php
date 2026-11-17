<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Translations_Form_Write — the shared base for the translation-override writes (TIGER-528).
 *
 * The Translations screen renders one token and drives both save and revert from the same modal, so
 * the two forms share one CSRF salt (`Tiger_Form::csrfSalt()` defaults to the class, which would make
 * the second call fail "your security token expired"; see `Desk_Form_TicketWrite`).
 *
 * Fields are optional and filter-free: the service trims `key` and answers a blank one with its own
 * `translations.error.no_key`, and `values` arrives as a locale => text MAP — `StringTrim` on an array
 * is an "Array to string conversion" warning (AGENTS.md "Forms").
 */
abstract class Translations_Form_Write extends Tiger_Form
{
    /**
     * One token for every translation write on the screen.
     *
     * @return string
     */
    protected function csrfSalt(): string
    {
        return 'Translations_Form_Write';
    }

    /**
     * An optional, filter-free field.
     *
     * @param  string $name the field name
     * @return array  [type, name, options]
     */
    protected function _field(string $name): array
    {
        return ['text', $name, ['required' => false, 'filters' => [], 'validators' => []]];
    }
}
