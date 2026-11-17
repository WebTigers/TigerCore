<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Media_Form_Storage — the cloud-storage adapter settings: saveStorage + testConnection (TIGER-528).
 *
 * saveStorage writes cloud CREDENTIALS into the config tier, so it is exactly the write a cross-site
 * page must not be able to trigger. The form declares every field of every adapter
 * (Media_Service_Settings::STORAGE_SCHEMA) plus `adapter` and `default_disk`. All optional and
 * filter-free: which fields are required depends on the adapter (STORAGE_REQUIRED, checked by the
 * service with `media.storage.missing_field`), a blank secret means "keep the stored one", and the
 * service sanitizes the adapter name and the CDN host itself.
 *
 * @api
 */
class Media_Form_Storage extends Media_Form_Write
{
    /** @return array the element schema */
    protected function elements(): array
    {
        $out    = [$this->_field('adapter'), $this->_field('default_disk')];
        $fields = [];
        foreach (Media_Service_Settings::STORAGE_SCHEMA as $list) {
            foreach ($list as $f) { $fields[$f] = true; }
        }
        foreach (array_keys($fields) as $f) {
            $out[] = $this->_field($f, in_array($f, Media_Service_Settings::STORAGE_SECRETS, true) ? 'password' : 'text');
        }
        return $out;
    }
}
