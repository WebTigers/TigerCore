<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Blog_Form_PostAction — the shared base for the article id-only writes: delete and restore (TIGER-528).
 *
 * Both share the editor's CSRF salt (`Blog_Form_Post`, the salt that form's token has always carried):
 * the editor posts its whole form — token included — to `restore`, and the Articles list renders the
 * same-salt token for `delete`. One token per salt per session (Tiger_Form_Element_Hash), so either
 * screen's rendered token satisfies either write.
 */
abstract class Blog_Form_PostAction extends Tiger_Form
{
    /**
     * The editor's salt, shared by every article write.
     *
     * @return string
     */
    protected function csrfSalt(): string
    {
        return 'Blog_Form_Post';
    }
}
