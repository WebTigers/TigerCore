<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/** Comment_Form_Delete — delete your own comment (or, as a moderator, anyone's). */
class Comment_Form_Delete extends Comment_Form_Write
{
    /** @return array the element schema */
    protected function elements(): array
    {
        return [
            ['hidden', 'comment_id', ['required' => true, 'validators' => [['StringLength', false, ['min' => 1, 'max' => 36]]]]],
        ];
    }
}
