<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Sms_Form_Test — send a test SMS through the configured provider (TIGER-528).
 *
 * Not a stored write, but it SENDS a message on the operator's paid account to a number of the
 * caller's choosing, so a cross-site page must not be able to trigger it.
 *
 * @api
 */
class Sms_Form_Test extends Sms_Form_Write
{
    /** @return array the element schema */
    protected function elements(): array
    {
        return [$this->_field('to'), $this->_field('message', 'textarea')];
    }
}
