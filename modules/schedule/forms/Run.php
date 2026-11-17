<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Schedule_Form_Run — run one job now, ignoring its schedule (TIGER-528).
 *
 * Running a job (a backup, a sweep, a send) is a state change a cross-site page must not trigger, so it
 * carries the CSRF token like any other write.
 *
 * @api
 */
class Schedule_Form_Run extends Schedule_Form_Write
{
    /** @return array the element schema */
    protected function elements(): array
    {
        return [$this->_field('key')];
    }
}
