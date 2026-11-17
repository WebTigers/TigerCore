<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Git_Form_Policy — set a module's update policy: auto | notify | pinned (TIGER-528).
 *
 * `channel` stays bounded to Tiger_Git::POLICY_* by the service (`git.error.invalid_channel`); the form
 * adds the CSRF token.
 */
class Git_Form_Policy extends Git_Form_Write
{
    /** @return array the element schema */
    protected function elements(): array
    {
        return [$this->_field('module_key'), $this->_field('channel'), $this->_field('pinned_version')];
    }
}
