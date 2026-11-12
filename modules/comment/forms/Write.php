<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Comment_Form_Write — the shared base for every comment write form (post, edit, delete, moderate, settings).
 *
 * Its only job is ONE CSRF salt for the set: a page that posts a comment and deletes one, or moderates and flips
 * the spam setting, renders a single token, and every action validates against it. Per-class salts would mint
 * one token per form in separate session slots, and the second action would fail as "token expired".
 */
abstract class Comment_Form_Write extends Tiger_Form
{
    /** One salt for every comment write form. */
    protected function csrfSalt(): string
    {
        return 'comment';
    }
}
