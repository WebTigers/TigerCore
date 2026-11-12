<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Comment_Form_Moderate — set a comment's moderation status. Carries the CSRF token + the comment id; the
 * status itself is checked by the service against Tiger_Model_Comment::STATUSES (its bad_status message).
 */
class Comment_Form_Moderate extends Comment_Form_Write
{
    /** @return array the element schema */
    protected function elements(): array
    {
        return [
            ['hidden', 'comment_id', ['required' => true, 'validators' => [['StringLength', false, ['min' => 1, 'max' => 36]]]]],
        ];
    }
}
