<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * System_Form_UpdateApply — apply pending updates (System_Service_Updates::apply, TIGER-528).
 *
 * `items` is the list of pending-update slugs (an array, or a comma list). Declared without a filter —
 * `StringTrim` would cast an array with a warning — and without a validator: the service normalizes
 * the list and keeps its `system.update.none_selected` refusal. The point of the form is the token:
 * applying an update downloads and installs code, which a cross-site page must never trigger.
 *
 * @api
 */
class System_Form_UpdateApply extends Tiger_Form
{
    /**
     * The declared schema.
     *
     * @return array<int,array> [type, name, options] triples
     */
    protected function elements(): array
    {
        return [
            ['text', 'items', ['required' => false, 'filters' => [], 'validators' => []]],
        ];
    }
}
