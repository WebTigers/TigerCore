<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Cms_Form_PageRestore — restore a page to a prior version (TIGER-528).
 *
 * Posted from the page editor alongside its other fields; the shared Cms_Form_Write salt lets the
 * editor's one token cover it.
 *
 * @api
 */
class Cms_Form_PageRestore extends Cms_Form_PageId
{
    /**
     * The declared schema: the page id + the version number (1 or higher).
     *
     * @return array<int,array> [type, name, options] triples
     */
    protected function elements(): array
    {
        return array_merge(parent::elements(), [
            ['hidden', 'version', [
                'required'   => true,
                'filters'    => ['StringTrim'],
                'validators' => [['Digits'], ['GreaterThan', false, ['min' => 0]]],
            ]],
        ]);
    }
}
