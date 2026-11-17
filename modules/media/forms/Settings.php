<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Media_Form_Settings — the Media Library settings: filename obfuscation, per file visibility.
 *
 * Two Yes/No selects (public / private). Values are stored in the `config` table (org-scoped) by
 * Media_Service_Settings — the live-override tier, no separate settings table (config-discipline).
 * '1' = obfuscate (random storage key); '0' = readable slugified filename in the key/URL.
 *
 * Shares the module's CSRF salt (Media_Form_Write, TIGER-528), so the token this form renders on the
 * Settings screen also covers saveStorage, testConnection and migrateDisk from the same page.
 *
 * @api
 */
class Media_Form_Settings extends Media_Form_Write
{
    protected function elements(): array
    {
        $select = ['class' => 'form-select'];
        $yesNo  = [
            '0' => $this->_t('media.settings.obfuscate.no'),
            '1' => $this->_t('media.settings.obfuscate.yes'),
        ];

        return [
            ['select', 'obfuscate_public', [
                'multiOptions' => $yesNo,
                'attribs'      => array_merge($select, ['id' => 'media-obf-public']),
            ]],
            ['select', 'obfuscate_private', [
                'multiOptions' => $yesNo,
                'attribs'      => array_merge($select, ['id' => 'media-obf-private']),
            ]],
        ];
    }
}
