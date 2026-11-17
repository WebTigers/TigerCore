<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Backup_Form_Run — create a manual backup now (Backup_Service_Backup::run, TIGER-528).
 *
 * `components` (array or comma list), `disk`, `include_secrets`. The service keeps its refusals —
 * `backup.bad_component`, `backup.bad_disk`, `backup.secrets.not_allowed` — with their keys.
 *
 * @api
 */
class Backup_Form_Run extends Backup_Form_Write
{
    /**
     * The declared schema.
     *
     * @return array<int,array> [type, name, options] triples
     */
    protected function elements(): array
    {
        return [
            $this->_field('components'),
            $this->_field('disk'),
            $this->_field('include_secrets'),
        ];
    }
}
