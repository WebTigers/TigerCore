<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Comment_Form_Edit — edit your own comment (or, as a moderator, anyone's): a PARTIAL update.
 *
 * Validated with isValidPartial(): only the fields sent are checked. The service seeds `_csrf` and
 * `comment_id` before calling it so those two are ALWAYS validated — an absent key would otherwise be skipped.
 */
class Comment_Form_Edit extends Comment_Form_Write
{
    /** @return array the element schema */
    protected function elements(): array
    {
        return [
            ['hidden', 'comment_id', ['required' => true, 'validators' => [['StringLength', false, ['min' => 1, 'max' => 36]]]]],
            ['textarea', 'body', ['required' => false, 'validators' => [['StringLength', false, ['max' => 20000]]]]],
            ['text', 'rating', ['required' => false, 'validators' => [['Between', false, ['min' => 1, 'max' => 5]]]]],
        ];
    }
}
