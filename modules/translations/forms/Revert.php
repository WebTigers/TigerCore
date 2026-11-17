<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Translations_Form_Revert — drop a key's override for one locale, or all (TIGER-528).
 *
 * An unsupported `locale` keeps its existing meaning (revert every locale), so it is not refused here.
 */
class Translations_Form_Revert extends Translations_Form_Write
{
    /** @return array the element schema */
    protected function elements(): array
    {
        return [$this->_field('key'), $this->_field('locale')];
    }
}
