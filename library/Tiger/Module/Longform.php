<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 K. Beau Beauchamp / WebTigers.
/**
 * Tiger_Module_Longform — resolves a module listing's LONG-FORM copy and renders it safely.
 *
 * This is the "plugin page" body: the seller's full pitch, as opposed to the one-line `description`
 * on a card. It reaches an install two ways, and this class is the single place that knows both:
 *
 *   - **inline `readme`** — carried on the listing itself. A paid/PASS module's repo is PRIVATE, so
 *     there is no public file to fetch; its marketplace serves the review copy in the feed and the
 *     Module Manager renders that (see `System_Service_Modules::_inspectMarketplace`). `body` is
 *     accepted as an alias for listings authored before the name settled.
 *   - **by URL `tiger_md`** — the raw URL of a public repo's `TIGER.md` (the registry schema's
 *     field). Fetched, https-only, size-capped and cached on disk.
 *
 * **Why this exists as one class.** Two surfaces show the same copy — the Module Manager's "View
 * more" and a marketplace's own listing page — and before this they rendered it two different ways
 * with two different safety policies. One renderer means a listing can never look safe in one
 * surface and unsafe in the other.
 *
 * **Security — the copy is UNTRUSTED.** It is a file written by whoever published the module. It is
 * rendered through a **safe-mode Parsedown**, which escapes inline HTML at parse time and filters
 * dangerous URL schemes. It deliberately does NOT go through `Tiger_Cms_Renderer::renderBody()`:
 * that renders markdown through the shared `Parsedown::instance()` singleton with markup ALLOWED and
 * then runs the `[shortcode]` pass — correct for a trusted CMS author, wrong for a third party,
 * since it would both emit their markup and let them invoke this install's shortcodes. Escaping at
 * parse time is also strictly stronger than stripping tags from rendered HTML afterwards, which is
 * the classic thing to get subtly wrong.
 *
 * Fail-soft throughout: an unreachable URL, an oversized file or a parse failure yields `''`, never
 * an exception into the screen that asked. `setTransport()` is a test seam so the flow is coverable
 * with no network.
 *
 * @api
 * @since 1.4.0
 */
class Tiger_Module_Longform
{
    /** Cached fetches are re-checked hourly — vendor copy changes on their schedule, not ours. */
    const CACHE_TTL = 3600;

    /** Hard ceiling on a fetched body. Marketing copy is a few KB; past this it is not copy. */
    const MAX_BYTES = 262144;

    /** Seconds to wait on a vendor's host before giving up and showing the listing without a body. */
    const TIMEOUT = 6;

    /** The record fields carrying inline copy, in precedence order. */
    const INLINE_FIELDS = ['readme', 'body'];

    /** @var callable|null test seam: fn(string $url): ?string */
    protected static $_transport = null;

    /**
     * Override the HTTP fetch (tests). Pass null to restore the real one.
     *
     * @param  callable|null $transport fn(string $url): ?string — the raw body, or null on failure
     * @return void
     */
    public static function setTransport($transport = null)
    {
        self::$_transport = $transport;
    }

    /**
     * A listing's long-form copy as safe HTML.
     *
     * @param  array $listing the listing/record as the registry or a marketplace yields it
     * @return string         rendered HTML ('' when the listing carries no long-form copy)
     */
    public static function html(array $listing)
    {
        $markdown = self::markdown($listing);
        return $markdown === '' ? '' : self::render($markdown);
    }

    /**
     * A listing's long-form copy as raw markdown — inline first, then by URL.
     *
     * @param  array $listing the listing/record
     * @return string         the markdown ('' when there is none)
     */
    public static function markdown(array $listing)
    {
        foreach (self::INLINE_FIELDS as $field) {
            $inline = trim((string) (isset($listing[$field]) ? $listing[$field] : ''));
            if ($inline !== '') { return $inline; }
        }

        $url = trim((string) (isset($listing['tiger_md']) ? $listing['tiger_md'] : ''));
        return $url === '' ? '' : self::fetch($url);
    }

    /**
     * Render untrusted markdown to safe HTML.
     *
     * Uses its OWN Parsedown instance, never the `Parsedown::instance()` singleton the CMS renderer
     * shares — flipping safe mode on that would silently change how every CMS page renders.
     *
     * @param  string $markdown the raw markdown
     * @return string           the rendered HTML ('' when Parsedown is unavailable or parsing fails)
     */
    public static function render($markdown)
    {
        $markdown = (string) $markdown;
        if ($markdown === '') { return ''; }

        if (!class_exists('Parsedown', false)) {
            $file = __DIR__ . '/../Cms/vendor/Parsedown.php';
            if (!is_file($file)) { return ''; }
            require_once $file;
        }

        try {
            $parser = new Parsedown();
            $parser->setSafeMode(true);        // filters javascript:/data: URLs and unsafe attributes
            $parser->setMarkupEscaped(true);   // raw HTML in the source is SHOWN, never executed
            $parser->setBreaksEnabled(false);
            return (string) $parser->text($markdown);
        } catch (Throwable $e) {
            return '';
        }
    }

    /**
     * Fetch a `tiger_md` URL, cached on disk.
     *
     * HTTPS only: a body served over plain http can be rewritten in transit into whatever an attacker
     * wants an admin to read about a module they are deciding whether to install.
     *
     * @param  string $url the raw markdown URL
     * @return string      the markdown ('' on any failure)
     */
    public static function fetch($url)
    {
        $url = (string) $url;
        if (stripos($url, 'https://') !== 0) { return ''; }

        // An injected transport replaces the WHOLE fetch path, disk cache included — consulting the
        // cache first would make a test depend on whether an earlier run had warmed that URL's file.
        if (self::$_transport !== null) {
            $body = call_user_func(self::$_transport, $url);
            return self::_acceptable($body) ? $body : '';
        }

        $cached = self::_cacheGet($url);
        if ($cached !== null) { return $cached; }

        $context = stream_context_create(['http' => [
            'timeout'         => self::TIMEOUT,
            // An outbound request with no User-Agent is 403'd by some WAFs (file_get_contents sends
            // none by default), which would read as "the vendor is down" for every listing behind one.
            'header'          => "User-Agent: Tiger/" . Tiger_Version::VERSION . "\r\n",
            'follow_location' => 1,
            'max_redirects'   => 3,
        ]]);
        $body = @file_get_contents($url, false, $context, 0, self::MAX_BYTES + 1);

        if (!self::_acceptable($body)) { return ''; }

        self::_cachePut($url, $body);
        return $body;
    }

    /** A fetched body is usable when it is a non-empty string within the size ceiling. */
    protected static function _acceptable($body)
    {
        return is_string($body) && $body !== '' && strlen($body) <= self::MAX_BYTES;
    }

    /** The cached markdown for a URL while it is still fresh, else null. */
    protected static function _cacheGet($url)
    {
        $file = self::_cacheFile($url);
        if ($file && is_file($file) && (time() - filemtime($file)) < self::CACHE_TTL) {
            $body = @file_get_contents($file);
            if (is_string($body)) { return $body; }
        }
        return null;
    }

    /** Store a fetched body; a failed write just means the next request refetches. */
    protected static function _cachePut($url, $body)
    {
        $file = self::_cacheFile($url);
        if ($file) { @file_put_contents($file, $body); }
    }

    /** The on-disk cache path for a URL (hashed — a URL is not a safe filename). */
    protected static function _cacheFile($url)
    {
        $base = defined('APPLICATION_ROOT') ? rtrim(APPLICATION_ROOT, '/') : rtrim(getcwd(), '/');
        $dir  = $base . '/var/cache/longform';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) { return null; }
        return $dir . '/' . sha1($url) . '.md';
    }
}
