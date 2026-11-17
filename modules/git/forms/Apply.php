<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Git_Form_Apply — install an approved release over the running module (TIGER-528).
 *
 * `version` is optional (blank = the newest approved release); the service resolves it against the
 * approved releases and refuses anything else with its own keys.
 */
class Git_Form_Apply extends Git_Form_Write
{
    /** @return array the element schema */
    protected function elements(): array
    {
        return [$this->_field('module_key'), $this->_field('version')];
    }
}
