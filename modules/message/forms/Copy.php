<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Message_Form_Copy — a write on MY copy of a message: markRead, markUnread, archive, unarchive,
 * delete (TIGER-528).
 *
 * No prose, but each changes state, so each carries the CSRF token. Ownership of the copy stays the
 * service's check (`getCopy($id, $me)`).
 */
class Message_Form_Copy extends Message_Form_Write
{
    /** @return array the element schema */
    protected function elements(): array
    {
        return [$this->_id('message_id')];
    }
}
