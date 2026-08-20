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
 * @api
 */
class Media_Form_Settings extends Tiger_Form
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
