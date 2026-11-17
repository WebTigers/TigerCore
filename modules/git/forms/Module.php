<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Git_Form_Module — a write that names only a module: recheck, disconnect, rollback (TIGER-528).
 *
 * No user prose, but each is a state change, so it still carries the CSRF token — that is the point.
 * The slug itself is re-checked by the service's slug guard (`git.error.invalid_module`).
 */
class Git_Form_Module extends Git_Form_Write
{
    /** @return array the element schema */
    protected function elements(): array
    {
        return [$this->_field('module_key')];
    }
}
