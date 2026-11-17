<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Media_Form_Update — edit a media item's editorial fields (TIGER-528).
 *
 * A PARTIAL update: the service writes only the fields the caller sent (`array_key_exists`), so it
 * validates with isValidPartial() after seeding `_csrf` (an absent key would otherwise skip the token).
 * Every field is optional and filter-free — the service trims them, and `visibility` keeps its existing
 * coercion (anything but `private` is `public`), so no input that used to be accepted is refused.
 *
 * @api
 */
class Media_Form_Update extends Media_Form_Write
{
    /** @return array the element schema */
    protected function elements(): array
    {
        return [
            $this->_field('media_id'),
            $this->_field('title'),
            $this->_field('caption'),
            $this->_field('alt_text'),
            $this->_field('description', 'textarea'),
            $this->_field('visibility'),
        ];
    }
}
