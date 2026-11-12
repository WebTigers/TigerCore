<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Comment_Bootstrap — wires up comments, ratings and reviews (COMMENTS.md).
 *
 * Everything is behind `Tiger_Comment::isEnabled()`. With the feature off, no shortcode resolves, no
 * subject provider registers and no admin item appears — so an install that never turns it on carries
 * no comment surface at all, which is the point of shipping it off by default.
 *
 * The flag is NOT read here: a module bootstrap runs before the `config` table is folded into the
 * config, so it would only ever see the ini value. `Comment_Plugin_Register` reads it per request,
 * after the whole bootstrap, and registers the surface then.
 */
class Comment_Bootstrap extends Zend_Application_Module_Bootstrap
{
    /** Register the comment surface per request, once the full config (incl. the `config` table) is in. */
    protected function _initCommentRegistration()
    {
        Zend_Controller_Front::getInstance()->registerPlugin(new Comment_Plugin_Register());
    }
}
