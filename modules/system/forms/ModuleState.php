<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * System_Form_ModuleState — change an installed module's state: activate, deactivate, auto-update,
 * delete (System_Service_Modules, TIGER-528).
 *
 * `slug` names the module; `confirm` is the conflict confirm (activate) or the typed `<vendor>/<name>`
 * (delete); `make_default` makes an activated theme the site default; `on` is the auto-update switch.
 * The service keeps the slug/protected/conflict/dependency refusals and their error keys.
 *
 * @api
 */
class System_Form_ModuleState extends System_Form_ModuleWrite
{
    /**
     * The declared schema.
     *
     * @return array<int,array> [type, name, options] triples
     */
    protected function elements(): array
    {
        return [
            $this->_field('slug'),
            $this->_field('confirm'),
            $this->_field('make_default'),
            $this->_field('on'),
        ];
    }
}
