<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Comment — the subject registry and policy gate for comments, ratings and reviews.
 *
 * A **subject** is whatever a thread hangs off: a CMS page, a blog article, a shop product, a
 * marketplace listing, a profile. Core must never learn what any of those *are*, yet it still has to
 * render "Reviews of X" with a working link, decide who may read the thread, know whether stars even
 * apply, and notice when the thing was deleted. So a module REGISTERS a provider — the same seam
 * shape as `Tiger_Search` and `Tiger_Audience`.
 *
 * A provider declares:
 *
 *   key        string    the stored `subject_type` (e.g. 'page', 'blog.post', 'shop.product')
 *   label      string    human name, for the moderation queue
 *   resolve    callable  fn(string $id): array{title:string,url:string,exists:bool}
 *   resource   ?string   ACL resource gating who may READ the thread (the SUBJECT's own resource, so
 *                        a thread never becomes a side channel to content the reader can't see)
 *   privilege  string    the privilege checked on that resource (default 'index')
 *   ratings    bool      may a comment here carry a star rating? (a blog post: no; a product: yes)
 *   threading  int       max reply depth (0 = flat, 1 = one level of replies)
 *   may_review ?callable fn(string $id, ?string $userId): bool — the entitlement gate (COMMENTS.md §7)
 *   owns       ?callable fn(string $id, ?string $userId): bool — is this user the subject's OWNER?
 *                        Used to refuse self-review.
 *
 * @api
 * @since 1.5.0
 */
class Tiger_Comment
{
    /** The whole feature is off unless an admin turns it on. */
    const CONFIG_ENABLED = 'tiger.comment.enabled';

    /** Moderation posture: hold new comments, or publish them immediately. */
    const CONFIG_MODERATION = 'tiger.comment.moderation';

    /** May a signed-out visitor comment at all? Off by default — an open endpoint is a spam magnet. */
    const CONFIG_ALLOW_GUESTS = 'tiger.comment.allow_guests';

    /** Seconds an author may keep editing their own comment. */
    const CONFIG_EDIT_WINDOW = 'tiger.comment.edit_window';

    const MODERATION_HOLD    = 'hold';      // new comments land `pending` (default)
    const MODERATION_PUBLISH = 'publish';   // new comments land `approved`

    const DEFAULT_EDIT_WINDOW = 900;        // 15 minutes

    /** @var array<string,array> key => provider */
    protected static $_subjects = [];

    /**
     * Register a subject provider. A later registration of the same key replaces the earlier one, so
     * an app can override a first-party provider.
     *
     * @param  array $provider the provider declaration (see the class docblock)
     * @return void
     */
    public static function registerSubject(array $provider)
    {
        $key = isset($provider['key']) ? preg_replace('/[^a-zA-Z0-9._-]/', '', (string) $provider['key']) : '';
        if ($key === '' || empty($provider['label'])) { return; }

        self::$_subjects[$key] = [
            'key'        => $key,
            'label'      => (string) $provider['label'],
            'resolve'    => $provider['resolve']    ?? null,
            'resource'   => $provider['resource']   ?? null,
            'privilege'  => (string) ($provider['privilege'] ?? 'index'),
            'ratings'    => (bool) ($provider['ratings'] ?? false),
            'threading'  => max(0, (int) ($provider['threading'] ?? 1)),
            'may_review' => $provider['may_review'] ?? null,
            'owns'       => $provider['owns']       ?? null,
        ];
    }

    /** Every registered provider, keyed by subject type. @return array<string,array> */
    public static function subjects()
    {
        return self::$_subjects;
    }

    /**
     * One provider, or null when nothing registered that type.
     *
     * @param  string $type the subject type
     * @return array|null   the provider
     */
    public static function subject($type)
    {
        $type = (string) $type;
        return self::$_subjects[$type] ?? null;
    }

    /** Drop every registration (tests). @return void */
    public static function reset()
    {
        self::$_subjects = [];
    }

    /**
     * Is the comment feature switched on for this install?
     *
     * Off by default. An open comment endpoint is the most-attacked surface a CMS has, and it carries
     * a standing moderation duty — a brochure site must not inherit either by accident.
     *
     * @return bool
     */
    public static function isEnabled()
    {
        return (bool) self::_cfg(self::CONFIG_ENABLED, false);
    }

    /**
     * Does a subject type accept star ratings?
     *
     * @param  string $type the subject type
     * @return bool         false for an unregistered type — never guess
     */
    public static function acceptsRatings($type)
    {
        $s = self::subject($type);
        return $s ? (bool) $s['ratings'] : false;
    }

    /**
     * Resolve a subject to `{title, url, exists}` for display and orphan detection.
     *
     * Fail-soft: an unregistered type, a missing resolver or a throwing one yields a non-existent
     * subject rather than an exception — the moderation queue must still render a row whose subject
     * has gone away, which is exactly when an operator most needs to see it.
     *
     * @param  string $type the subject type
     * @param  string $id   the subject id
     * @return array{title:string,url:string,exists:bool}
     */
    public static function resolve($type, $id)
    {
        $miss = ['title' => '', 'url' => '', 'exists' => false];

        $s = self::subject($type);
        if (!$s || !$s['resolve'] || !is_callable($s['resolve'])) { return $miss; }

        try {
            $out = call_user_func($s['resolve'], (string) $id);
            if (!is_array($out)) { return $miss; }
            return [
                'title'  => (string) ($out['title'] ?? ''),
                'url'    => (string) ($out['url'] ?? ''),
                'exists' => (bool) ($out['exists'] ?? false),
            ];
        } catch (Throwable $e) {
            return $miss;
        }
    }

    /**
     * The status a NEW comment should land in, honoring the install's moderation posture.
     *
     * @return string `pending` (hold, the default) or `approved` (publish immediately)
     */
    public static function initialStatus()
    {
        $mode = (string) self::_cfg(self::CONFIG_MODERATION, self::MODERATION_HOLD);
        return $mode === self::MODERATION_PUBLISH
            ? Tiger_Model_Comment::STATUS_APPROVED
            : Tiger_Model_Comment::STATUS_PENDING;
    }

    /** May a signed-out visitor post? @return bool */
    public static function allowsGuests()
    {
        return (bool) self::_cfg(self::CONFIG_ALLOW_GUESTS, false);
    }

    /** How long an author may keep editing their own comment, in seconds. @return int */
    public static function editWindow()
    {
        $v = (int) self::_cfg(self::CONFIG_EDIT_WINDOW, self::DEFAULT_EDIT_WINDOW);
        return $v > 0 ? $v : self::DEFAULT_EDIT_WINDOW;
    }

    /**
     * Would a review by this user on this subject count as VERIFIED — i.e. did they actually buy,
     * own, or hold a grant for the thing they're reviewing? (COMMENTS.md §7.)
     *
     * A provider with no `may_review` simply has no entitlement to check (a blog post doesn't), so
     * the answer is false and nothing is badged — never an error.
     *
     * @param  string      $type   the subject type
     * @param  string      $id     the subject id
     * @param  string|null $userId the acting user, or null for a guest
     * @return bool
     */
    public static function isVerifiedReviewer($type, $id, $userId)
    {
        if ($userId === null || $userId === '') { return false; }

        $s = self::subject($type);
        if (!$s || !$s['may_review'] || !is_callable($s['may_review'])) { return false; }

        try {
            return (bool) call_user_func($s['may_review'], (string) $id, (string) $userId);
        } catch (Throwable $e) {
            return false;   // an entitlement check that errors must never mint a verified badge
        }
    }

    /**
     * Does this user OWN the subject? Used to refuse a self-review — a vendor rating their own
     * listing is the most obvious way to poison a rating system.
     *
     * @param  string      $type   the subject type
     * @param  string      $id     the subject id
     * @param  string|null $userId the acting user
     * @return bool
     */
    public static function ownsSubject($type, $id, $userId)
    {
        if ($userId === null || $userId === '') { return false; }

        $s = self::subject($type);
        if (!$s || !$s['owns'] || !is_callable($s['owns'])) { return false; }

        try {
            return (bool) call_user_func($s['owns'], (string) $id, (string) $userId);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Round an average to the nearest half star — the display contract (COMMENTS.md §4).
     *
     * Halves come from AVERAGING, not from a half-star picker: input is whole stars 1-5, and 4.3
     * renders as four full stars plus a half.
     *
     * @param  float $avg the raw average
     * @return float      the average snapped to a .0 or .5 increment, clamped to 0..5
     */
    public static function halfStar($avg)
    {
        $avg = (float) $avg;
        if ($avg < 0) { $avg = 0.0; }
        if ($avg > 5) { $avg = 5.0; }
        return round($avg * 2) / 2;
    }

    /**
     * A `tiger.comment.*` config value.
     *
     * @param  string $key     the dot-notation key
     * @param  mixed  $default returned when unset
     * @return mixed
     */
    protected static function _cfg($key, $default = null)
    {
        if (!Zend_Registry::isRegistered('Zend_Config')) { return $default; }

        $node = Zend_Registry::get('Zend_Config');
        foreach (explode('.', $key) as $part) {
            if (!$node instanceof Zend_Config) { return $default; }
            $node = $node->get($part);
            if ($node === null) { return $default; }
        }
        return $node instanceof Zend_Config ? $default : $node;
    }
}
