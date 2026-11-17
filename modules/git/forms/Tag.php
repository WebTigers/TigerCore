<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Git_Form_Tag — create a semver tag on the connected repo (TIGER-528).
 *
 * The version stays gated by Tiger_Git::isSemver in the service (`git.error.invalid_version`), the
 * module by the slug guard; the form adds the CSRF token a tag-cutting write was missing.
 */
class Git_Form_Tag extends Git_Form_Write
{
    /** @return array the element schema */
    protected function elements(): array
    {
        return [$this->_field('module_key'), $this->_field('version')];
    }
}
