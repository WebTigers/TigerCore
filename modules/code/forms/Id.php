<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Code_Form_Id — a state change on one snippet by id: activate, deactivate, delete (TIGER-528).
 *
 * No user prose, but a write all the same — so it carries the CSRF token. `code_id` is a local
 * snippet's UUID or `module:<key>` for a module snippet; it is required, exactly as the service
 * refused an empty one before.
 *
 * @api
 */
class Code_Form_Id extends Code_Form_Write
{
    /**
     * The declared schema.
     *
     * @return array<int,array> [type, name, options] triples
     */
    protected function elements(): array
    {
        return [
            ['hidden', 'code_id', [
                'required'   => true,
                'filters'    => ['StringTrim'],
                'validators' => [['StringLength', false, [1, 255]]],
            ]],
        ];
    }
}
