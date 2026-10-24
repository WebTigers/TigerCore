<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\View;

use FilesystemIterator;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tiger\Tests\Support\UnitTestCase;

/**
 * The house rule from AGENTS.md ("Views: a `.phtml` owns MARKUP"): no `<script>` and no `<style>`
 * tag in a view — not an inline blob, and not a `<script src>`/`<link rel="stylesheet">` include.
 * Behavior is a JS asset the view REGISTERS with `$this->pageScript()`, styling is the skin's job
 * via `$this->pageStyle()`, and the theme LAYOUT is the one place a tag is emitted.
 *
 * This is a guard, not a preference. The rule was written down, the `pageScript()`/`pageStyle()`
 * seam was built and tested — and the tree still accumulated ~5,400 lines of inline JS across 47
 * views (TIGER-384), because nothing failed when someone typed `<script>`. A rule with no test is
 * a comment. `tests/ui/bin/paradigm-scan.php` reports the same violations with richer output for a
 * person; this is the half that fails CI.
 *
 * Matching runs on PHP-STRIPPED source, so `'#<script\b[^>]*>#'` inside a preg pattern, or the word
 * in a comment, is not mistaken for markup (three such false reports came out of cms/page/design).
 *
 * Two structural exemptions, neither a matter of taste:
 *   • an EMAIL template — mail clients drop `<link>`, so inline CSS is the correct answer there;
 *   • a view that IS the document (`<!doctype html>`) — a full-screen surface whose controller
 *     disabled the layout, so there is no layout to register into. It is a layout wearing a view's
 *     filename, and a layout may emit tags.
 */
#[CoversNothing]
final class ViewOwnsMarkupTest extends UnitTestCase
{
    /**
     * A tag a view must not emit: a script, a style block, or a stylesheet link. A JSON-LD (or JSON)
     * `<script>` is exempt — it carries DATA, not behavior, and the spec requires it inline.
     */
    private const TAG = '~<script\b(?![^>]*\btype\s*=\s*["\']application/(?:ld\+json|json)["\'])[^>]*>|<style\b[^>]*>|<link\b[^>]*\brel\s*=\s*["\']stylesheet["\'][^>]*>~i';

    #[Test]
    public function no_view_script_emits_its_own_script_or_style_tag(): void
    {
        $views = $this->views();
        $this->assertGreaterThan(50, count($views), 'found almost no views — the walk is wrong, not the rule');

        $offenders = [];
        foreach ($views as $file) {
            $src = (string) file_get_contents($file);
            if ($this->isExempt($file, $src)) { continue; }

            if (preg_match_all(self::TAG, $this->stripPhp($src), $m, PREG_OFFSET_CAPTURE)) {
                foreach ($m[0] as [$tag, $off]) {
                    $offenders[] = sprintf('%s:%d  %s', $this->rel($file),
                        substr_count(substr($src, 0, $off), "\n") + 1,
                        trim((string) preg_replace('/\s+/', ' ', $tag)));
                }
            }
        }

        $this->assertSame([], $offenders, "a view must not emit a <script>/<style> tag — move the JS to an\n"
            . "asset and register it with \$this->pageScript(), the CSS with \$this->pageStyle()\n"
            . "(AGENTS.md \"Views: a .phtml owns MARKUP\"):\n  " . implode("\n  ", $offenders));
    }

    #[Test]
    public function the_guard_actually_catches_a_planted_tag(): void
    {
        // A validator that only ever passes proves nothing. Prove each rule fires, and that each
        // exemption is the thing letting the exempt files through — not a hole in the matcher.
        $this->assertSame(1, preg_match_all(self::TAG, $this->stripPhp('<p>x</p><script>a()</script>')));
        $this->assertSame(1, preg_match_all(self::TAG, $this->stripPhp('<script src="/a.js"></script>')));
        $this->assertSame(1, preg_match_all(self::TAG, $this->stripPhp('<style>.a{}</style>')));
        $this->assertSame(1, preg_match_all(self::TAG, $this->stripPhp('<link rel="stylesheet" href="/a.css">')));
        $this->assertSame(0, preg_match_all(self::TAG, $this->stripPhp('<link>https://example.com/feed</link>')),
            'an RSS <link> is not a stylesheet');
        $this->assertSame(0, preg_match_all(self::TAG, $this->stripPhp('<script type="application/ld+json">{}</script>')),
            'JSON-LD carries data, not behavior, and the spec requires it inline');

        // …and that a <script> living inside PHP code or a comment is NOT markup.
        $this->assertSame(0, preg_match_all(self::TAG,
            $this->stripPhp('<?php $re = \'#<script\b[^>]*>#is\'; // strips a <script> tag' . "\n" . '?>')));
    }

    /** Every view script in the tree (core, modules, themes) — the same set the paradigm scan reads. */
    private function views(): array
    {
        $out = [];
        foreach (['core', 'modules', 'themes'] as $base) {
            $dir = TIGER_CORE_PATH . '/' . $base;
            if (!is_dir($dir)) { continue; }
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if (substr($f->getPathname(), -6) === '.phtml') { $out[] = $f->getPathname(); }
            }
        }
        sort($out);
        return $out;
    }

    /** An email template, a layout, or a view that is itself the whole document. */
    private function isExempt(string $file, string $src): bool
    {
        return strpos($file, '/views/emails/') !== false
            || strpos($file, '/layouts/') !== false
            || (bool) preg_match('/<!doctype\s+html|<html\b/i', $src);
    }

    /** Blank every PHP region, preserving length + newlines, so what is left is markup. */
    private function stripPhp(string $src): string
    {
        return (string) preg_replace_callback('/<\?(?:php|=)?.*?(?:\?>|$)/s', static function ($m) {
            return preg_replace('/[^\n]/', ' ', $m[0]);
        }, $src);
    }

    private function rel(string $file): string
    {
        return ltrim(str_replace(TIGER_CORE_PATH, '', $file), '/');
    }
}
