<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Git_Form_Decide — the QA reviewer approves or rejects a submitted release (TIGER-528).
 *
 * `decision` stays bounded to approve|reject by the service (`git.error.invalid_decision`); the release
 * lookup + org check stay there too. The form adds the CSRF token a QA verdict was missing.
 */
class Git_Form_Decide extends Git_Form_Write
{
    /** @return array the element schema */
    protected function elements(): array
    {
        return [$this->_field('module_release_id'), $this->_field('decision')];
    }
}
