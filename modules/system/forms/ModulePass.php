<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * System_Form_ModulePass — the TigerPASS tab's writes: activate / remove the key, snooze or switch off
 * the promo banner (System_Service_Modules, TIGER-528).
 *
 * `key` is the TigerPASS key (activatePass — the service normalizes + shape-checks it as a UUID and
 * answers `system.pass.invalid_format`); `disabled` is the banner switch (setPassNag). deactivatePass
 * and snoozePassNag take no field — they validate the token alone, which is the point: a state change
 * with no input is still a write a cross-site page must not be able to trigger.
 *
 * @api
 */
class System_Form_ModulePass extends System_Form_ModuleWrite
{
    /**
     * The declared schema.
     *
     * @return array<int,array> [type, name, options] triples
     */
    protected function elements(): array
    {
        return [
            $this->_field('key'),
            $this->_field('disabled'),
        ];
    }
}
