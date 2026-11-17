<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Message_Form_Member — block or unblock a member (TIGER-528).
 *
 * The membership + admin-class checks stay in the service (they need the caller's org); the form adds
 * the CSRF token.
 */
class Message_Form_Member extends Message_Form_Write
{
    /** @return array the element schema */
    protected function elements(): array
    {
        return [$this->_id('user_id')];
    }
}
