<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Code_Form_Write — the shared base for every Code Area write (TIGER-528).
 *
 * One CSRF salt for the whole surface. The snippet editor renders Code_Form_Code's token and also
 * posts `restore` from the same page; the list screen renders one token and drives activate /
 * deactivate / delete. `Tiger_Form::csrfSalt()` defaults to the form class, so separate salts would
 * make the second endpoint fail with "your security token expired" on a page behaving perfectly.
 *
 * @api
 */
abstract class Code_Form_Write extends Tiger_Form
{
    /**
     * One token for every Code Area write.
     *
     * @return string
     */
    protected function csrfSalt(): string
    {
        return 'Code_Form_Write';
    }
}
