<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * System_Form_ThemeContent — "Update now" on the Theme content screen (System_Service_Themecontent::fetch, TIGER-455).
 *
 * The point of the form is the token: a fetch rewrites the theme files every public page renders, which a cross-site
 * page must never trigger. `force` (optional, 0/1) re-downloads every file even when its hash already matches.
 *
 * @api
 */
class System_Form_ThemeContent extends Tiger_Form
{
    /**
     * The declared schema.
     *
     * @return array<int,array> [type, name, options] triples
     */
    protected function elements(): array
    {
        return [
            ['hidden', 'force', [
                'required'   => false,
                'validators' => [['InArray', false, ['haystack' => ['', '0', '1']]]],
            ]],
        ];
    }
}
