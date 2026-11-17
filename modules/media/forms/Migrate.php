<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Media_Form_Migrate — relocate existing media to another storage disk (TIGER-528).
 *
 * `move=1` deletes each original after a verified copy, so this carries the CSRF token like any other
 * destructive write. The target disk is sanitized and verified by the service (`media.migrate.*`).
 *
 * @api
 */
class Media_Form_Migrate extends Media_Form_Write
{
    /** @return array the element schema */
    protected function elements(): array
    {
        return [$this->_field('to'), $this->_field('move')];
    }
}
