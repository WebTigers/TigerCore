<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Schedule_Form_Schedule — reschedule / enable / disable one job (TIGER-528).
 *
 * A sparse write: the service touches only what was sent (`array_key_exists` for dow / dom / enabled),
 * so every field is optional and plain isValid() passes a partial payload while keeping `_csrf` (which
 * the hash element marks required) mandatory.
 *
 * @api
 */
class Schedule_Form_Schedule extends Schedule_Form_Write
{
    /** @return array the element schema */
    protected function elements(): array
    {
        return [
            $this->_field('key'),
            $this->_field('every'),
            $this->_field('at'),
            $this->_field('dow'),
            $this->_field('dom'),
            $this->_field('enabled'),
        ];
    }
}
