<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Media_Form_Item — a write that names one media item: delete, copyToLibrary (TIGER-528).
 *
 * No prose, but delete destroys stored bytes and copyToLibrary writes a file + a row, so each still
 * carries the CSRF token. Ownership and the static-entry lookup stay in the service. `ref` is the
 * legacy alias copyToLibrary accepts for `media_id`.
 *
 * @api
 */
class Media_Form_Item extends Media_Form_Write
{
    /** @return array the element schema */
    protected function elements(): array
    {
        return [$this->_field('media_id'), $this->_field('ref')];
    }
}
