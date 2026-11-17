<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Blog_Form_PostDelete — soft-delete an article (Blog_Service_Post::delete, TIGER-528).
 *
 * The article id plus the shared token. `post_id` stays optional so the service keeps its own refusal
 * for a missing id (`core.api.error.general`).
 *
 * @api
 */
class Blog_Form_PostDelete extends Blog_Form_PostAction
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
        ];
    }
}
