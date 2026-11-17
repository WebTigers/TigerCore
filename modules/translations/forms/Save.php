<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Translations_Form_Save — save one key's overrides across locales (TIGER-528).
 *
 * `values` is the locale => text map the service walks (only supported locales, each trimmed there).
 */
class Translations_Form_Save extends Translations_Form_Write
{
    /** @return array the element schema */
    protected function elements(): array
    {
        return [$this->_field('key'), $this->_field('values')];
    }
}
