<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Comment_Plugin_Register — registers the comment surface per request, once the config is complete.
 *
 * `tiger.comment.enabled` is normally a `config` row (the live-override tier), and that tier is folded
 * into Zend_Config by the APPLICATION bootstrap — after module bootstraps have already run. A flag read
 * inside `Comment_Bootstrap` therefore saw the ini value only, and an install that switched comments on
 * the usual way got no subjects, no shortcodes and no admin item. A front-controller plugin runs after
 * the whole bootstrap, so the flag it reads is the one the operator actually set.
 *
 * With the feature off nothing registers — the same "off means no comment surface at all" contract
 * the Bootstrap promised.
 *
 * @internal
 */
class Comment_Plugin_Register extends Zend_Controller_Plugin_Abstract
{
    /** @var bool registration is process-wide; do it once */
    private static $done = false;

    /**
     * Register the comment surface before routing, when the feature is on.
     *
     * @param  Zend_Controller_Request_Abstract $request the request (unused)
     * @return void
     */
    public function routeStartup(Zend_Controller_Request_Abstract $request)
    {
        self::registerAll();
    }

    /**
     * Register subjects, the spam checker, the shortcodes and the admin item — if comments are enabled.
     *
     * @return bool true when the surface is (now) registered
     */
    public static function registerAll(): bool
    {
        if (self::$done) { return true; }
        if (!class_exists('Tiger_Comment') || !Tiger_Comment::isEnabled()) { return false; }
        self::$done = true;

        self::_subjects();
        self::_spam();
        self::_shortcodes();
        self::_nav();
        return true;
    }

    /**
     * Forget that registration ran — a test seam, alongside Tiger_Comment::reset().
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$done = false;
    }

    /**
     * The built-in subject providers.
     *
     * Core ships the two subjects it actually owns — a CMS page and a blog article. Everything else
     * (shop product, marketplace listing, profile) is a module registering its own, because core must
     * not learn what those are.
     */
    private static function _subjects(): void
    {
        Tiger_Comment::registerSubject([
            'key'       => 'page',
            'label'     => 'Page',
            'resolve'   => [Comment_Service_Subjects::class, 'page'],
            'resource'  => 'PageController',
            'ratings'   => false,   // a CMS page takes discussion, not stars
            'threading' => Tiger_Comment::defaultThreading(),
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
                'threading' => Tiger_Comment::defaultThreading(),
            ]);
        }
    }

    /**
     * The AI spam checker. The checker itself decides at call time whether an agent is connected and
     * switched on, and returns `unknown` (fail-open) plus a log line otherwise.
     */
    private static function _spam(): void
    {
        if (class_exists('Tiger_Comment_Spam')) {
            Tiger_Comment_Spam::register([Tiger_Comment_Spam::class, 'agentCheck']);
        }
    }

    /**
     * The reader-facing shortcodes, so a CMS page or a theme can drop a thread in without code.
     *
     * `[comments]` and `[reviews]` are the same renderer — the only difference is whether the form
     * offers stars, and that is the SUBJECT's decision (`ratings`), not the shortcode's.
     */
    private static function _shortcodes(): void
    {
        if (!class_exists('Tiger_Cms_Renderer')) { return; }

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
    private static function _nav(): void
    {
        if (!class_exists('Tiger_Admin_Nav')) { return; }

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
