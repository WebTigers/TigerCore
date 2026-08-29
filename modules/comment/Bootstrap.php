<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Comment_Bootstrap — wires up comments, ratings and reviews (COMMENTS.md).
 *
 * Everything here is behind `Tiger_Comment::isEnabled()`. With the feature off, no shortcode
 * resolves, no subject provider registers and no admin item appears — so an install that never
 * turns it on carries no comment surface at all, which is the point of shipping it off by default.
 */
class Comment_Bootstrap extends Zend_Application_Module_Bootstrap
{
    /**
     * The built-in subject providers.
     *
     * Core ships the two subjects it actually owns — a CMS page and a blog article. Everything else
     * (shop product, marketplace listing, profile) is a module registering its own, because core must
     * not learn what those are.
     */
    protected function _initCommentSubjects()
    {
        if (!class_exists('Tiger_Comment') || !Tiger_Comment::isEnabled()) { return; }

        Tiger_Comment::registerSubject([
            'key'       => 'page',
            'label'     => 'Page',
            'resolve'   => [Comment_Service_Subjects::class, 'page'],
            'resource'  => 'PageController',
            'ratings'   => false,   // a CMS page takes discussion, not stars
            'threading' => 1,
        ]);

        // The blog only registers when the module is actually present — a subject whose module is
        // absent would resolve to a permanent "missing" row in the moderation queue.
        if (class_exists('Blog_Model_Post')) {
            Tiger_Comment::registerSubject([
                'key'       => 'blog.post',
                'label'     => 'Article',
                'resolve'   => [Comment_Service_Subjects::class, 'blogPost'],
                'resource'  => 'Blog_IndexController',
                'ratings'   => false,
                'threading' => 1,
            ]);
        }
    }

    /**
     * The reader-facing shortcodes, so a CMS page or a theme can drop a thread in without code.
     *
     * `[comments]` and `[reviews]` are the same renderer — the only difference is whether the form
     * offers stars, and that is the SUBJECT's decision (`ratings`), not the shortcode's. Two names
     * exist because authors look for both.
     */
    protected function _initCommentShortcodes()
    {
        if (!class_exists('Tiger_Cms_Renderer') || !class_exists('Tiger_Comment') || !Tiger_Comment::isEnabled()) {
            return;
        }

        $thread = static function ($attrs) {
            return (new Comment_Service_Render())->thread(
                (string) ($attrs['subject'] ?? ''),
                ['title' => $attrs['title'] ?? null]
            );
        };

        Tiger_Cms_Renderer::registerShortcode('comments', $thread);
        Tiger_Cms_Renderer::registerShortcode('reviews', $thread);

        Tiger_Cms_Renderer::registerShortcode('stars', static function ($attrs) {
            return (new Comment_Service_Render())->stars((string) ($attrs['subject'] ?? ''));
        });

        Tiger_Cms_Renderer::registerShortcode('rating_summary', static function ($attrs) {
            return (new Comment_Service_Render())->summary((string) ($attrs['subject'] ?? ''));
        });
    }

    /** The moderation queue, under Content in the admin sidebar. */
    protected function _initCommentNav()
    {
        if (!class_exists('Tiger_Admin_Nav') || !class_exists('Tiger_Comment') || !Tiger_Comment::isEnabled()) {
            return;
        }

        Tiger_Admin_Nav::register([
            'key'      => 'comments',
            'label'    => 'comment.nav.label',
            'icon'     => 'fa-comments',
            'href'     => '/comment/admin',
            'resource' => 'Comment_AdminController',
            'order'    => 22,
        ]);
    }
}
