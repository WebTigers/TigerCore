<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Backup_Form_Archive — act on one backup archive: delete it (Backup_Service_Backup::remove), restore
 * from it (Backup_Service_Backup::restore), or restore from an uploaded one
 * (Backup_IndexController::uploadAction) (TIGER-528).
 *
 * `backup_id` names the cataloged archive; `confirm` is the typed `RESTORE`; `components` is an
 * optional restore subset. The service (and the upload action) keep their refusals —
 * `backup.not_found`, `backup.restore.confirm` — with their keys. A restore replaces the install's
 * data, so it is exactly the write a cross-site page must never be able to trigger.
 *
 * @api
 */
class Backup_Form_Archive extends Backup_Form_Write
{
    /**
     * The declared schema.
     *
     * @return array<int,array> [type, name, options] triples
     */
    protected function elements(): array
    {
        return [
            $this->_field('backup_id'),
            $this->_field('confirm'),
            $this->_field('components'),
        ];
    }
}
