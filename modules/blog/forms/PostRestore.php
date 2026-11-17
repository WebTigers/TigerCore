<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Blog_Form_PostRestore — restore an article to a prior version (Blog_Service_Post::restore, TIGER-528).
 *
 * `post_id` + `version`, validated with the editor's token (the editor posts its whole form to this
 * method). Both stay optional with no validator so the service keeps its own refusal
 * (`core.api.error.general` for a missing id or a version below 1).
 *
 * @api
 */
class Blog_Form_PostRestore extends Blog_Form_PostAction
{
    /**
     * The declared schema.
     *
     * @return array<int,array> [type, name, options] triples
     */
    protected function elements(): array
    {
        return [
            ['hidden', 'post_id', ['required' => false, 'filters' => [], 'validators' => []]],
            ['hidden', 'version', ['required' => false, 'filters' => [], 'validators' => []]],
        ];
    }
}
