<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * System_Form_ModuleSource — connect, change or remove a marketplace source (System_Service_Modules,
 * TIGER-528).
 *
 * connectSource reads `label`, `url`, `kind`, `priority`; updateSource reads `id` plus the optional
 * `enabled` / `priority`; removeSource reads `id`. The service keeps its refusals
 * (`system.source.err_label`, `err_url`, `err_unknown`, `err_not_removable`).
 *
 * @api
 */
class System_Form_ModuleSource extends System_Form_ModuleWrite
{
    /**
     * The declared schema.
     *
     * @return array<int,array> [type, name, options] triples
     */
    protected function elements(): array
    {
        return [
            $this->_field('id'),
            $this->_field('label'),
            $this->_field('url'),
            $this->_field('kind'),
            $this->_field('priority'),
            $this->_field('enabled'),
        ];
    }
}
