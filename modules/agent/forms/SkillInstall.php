<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Agent_Form_SkillInstall — install a skill from a pasted GitHub URL or a browse entry (TIGER-528).
 *
 * Every field is optional: the service decides between the two shapes (a `url`, else the browse
 * entry's `repo`/`ref`/`path`/`name`/`source`) and refuses a browse entry with no `repo`, as before.
 * The form's job is the CSRF token plus a declared input contract.
 *
 * @api
 */
class Agent_Form_SkillInstall extends Agent_Form_AdminWrite
{
    /**
     * The declared schema.
     *
     * @return array<int,array> [type, name, options] triples
     */
    protected function elements(): array
    {
        $open = ['required' => false, 'filters' => ['StringTrim']];
        return [
            ['text', 'url', $open],
            ['text', 'repo', $open],
            ['text', 'ref', $open],
            ['text', 'path', $open],
            ['text', 'name', $open],
            ['text', 'source', $open],
            ['text', 'sourceLabel', $open],
        ];
    }
}
