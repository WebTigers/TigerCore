<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Code_Form_Restore — restore a snippet to a prior version (TIGER-528).
 *
 * Posted from the snippet editor, which renders Code_Form_Code's token — the shared salt on
 * Code_Form_Write lets that one token cover this endpoint too.
 *
 * @api
 */
class Code_Form_Restore extends Code_Form_Id
{
    /**
     * The declared schema: the snippet id + the version number (1 or higher).
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
