<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/** Comment_Form_Settings — the module's admin settings (the AI spam toggle). */
class Comment_Form_Settings extends Comment_Form_Write
{
    /** @return array the element schema */
    protected function elements(): array
    {
        return [
            ['hidden', 'spam_agent', ['required' => false, 'validators' => [['InArray', false, ['haystack' => ['0', '1', ''], 'strict' => false]]]]],
        ];
    }
}
