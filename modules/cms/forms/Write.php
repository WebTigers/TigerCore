<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Cms_Form_Write — the shared base for every CMS content write: pages and menus (TIGER-528).
 *
 * One CSRF salt for the surface. Each CMS screen renders one token and posts several endpoints from it
 * — the page editor saves and restores, the builder saves a design and a block, the menu builder saves,
 * reorders and deletes items — and `Tiger_Form::csrfSalt()` defaults to the form class, so separate salts
 * would fail the second call with "your security token expired" on a page behaving perfectly. The token
 * is timeout-lived, not single-use (Tiger_Form_Element_Hash), so the menu builder's rapid saves are fine.
 *
 * Cms_Form_Settings keeps its own salt — it lives on its own screen.
 *
 * @api
 */
abstract class Cms_Form_Write extends Tiger_Form
{
    /**
     * One token for every CMS content write.
     *
     * @return string
     */
    protected function csrfSalt(): string
    {
        return 'Cms_Form_Write';
    }

    /**
     * An optional, trimmed scalar field with no validators — the service keeps its own rule for it.
     *
     * @param  string $name the element/field name
     * @return array        [type, name, options]
     */
    protected function _optional(string $name): array
    {
        return ['hidden', $name, ['required' => false, 'filters' => ['StringTrim']]];
    }

    /**
     * A required, trimmed scalar field — the service refused an empty one before.
     *
     * @param  string $name the element/field name
     * @return array        [type, name, options]
     */
    protected function _required(string $name): array
    {
        return ['hidden', $name, ['required' => true, 'filters' => ['StringTrim'],
                                  'validators' => [['StringLength', false, [1, 191]]]]];
    }

    /**
     * A raw markup/CSS payload: optional, and deliberately NO filters — it is source, and trimming or
     * stripping it would change what the author saved. The service strips <script> itself.
     *
     * @param  string $name the element/field name
     * @return array        [type, name, options]
     */
    protected function _raw(string $name): array
    {
        return ['textarea', $name, ['required' => false, 'filters' => []]];
    }
}
