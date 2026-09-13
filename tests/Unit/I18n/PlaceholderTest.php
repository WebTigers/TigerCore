<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\I18n;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;

/**
 * Placeholder discipline across every shipped language file (TIGER-121).
 *
 * A string that takes TWO OR MORE arguments must use NUMBERED placeholders — `%1$s`, `%2$d` — because
 * only those let a translator reorder them. Sequential `%s` locks every language into English word
 * order, and that is a defect, not a preference: it surfaces only in the languages nobody on the team
 * reads, and only when a translator needs to say "#2 of 3" where English says "3 steps — #2".
 *
 * Found the hard way. The whole tree was sequential — 389 `%s`, zero numbered — and it was invisible
 * because it is not WRONG until the reorder is needed, which is exactly when nobody is looking.
 *
 * A string with ONE placeholder is left alone: there is nothing to reorder, and `%1$s` there is noise.
 *
 * This test also holds every non-English locale to the same PLACEHOLDER SET as English — by set, not
 * by order, since reordering is the point. Dropping a placeholder, or inventing one, is the bug.
 */
#[CoversNothing]
final class PlaceholderTest extends UnitTestCase
{
    /** Every shipped language file, as [path => strings]. */
    private function languageFiles(): array
    {
        $out = [];
        $globs = [
            TIGER_CORE_PATH . '/core/languages/*/*.php',
            TIGER_CORE_PATH . '/modules/*/languages/*/*.php',
            TIGER_CORE_PATH . '/themes/*/languages/*/*.php',
        ];
        foreach ($globs as $g) {
            foreach (glob($g) ?: [] as $f) {
                $strings = include $f;
                if (is_array($strings)) { $out[$f] = $strings; }
            }
        }
        return $out;
    }

    /** The placeholders in a string, in order — `%%` is a literal percent and does not count. */
    private function placeholders(string $s): array
    {
        preg_match_all('~%%|%(?:\d+\$)?[sd]~', $s, $m);
        return array_values(array_filter($m[0], static fn ($p) => $p !== '%%'));
    }

    #[Test]
    public function every_multi_placeholder_string_is_numbered(): void
    {
        $files = $this->languageFiles();
        $this->assertGreaterThan(50, count($files), 'the glob is wrong, not the rule');

        $offenders = [];
        $checked   = 0;
        foreach ($files as $path => $strings) {
            foreach ($strings as $key => $value) {
                if (!is_string($value)) { continue; }
                $ph = $this->placeholders($value);
                if (count($ph) < 2) { continue; }
                $checked++;
                foreach ($ph as $p) {
                    if (!preg_match('~^%\d+\$~', $p)) {
                        $offenders[] = sprintf('%s: %s => %s', $this->rel($path), $key, $value);
                        break;
                    }
                }
            }
        }
        $this->assertGreaterThan(0, $checked, 'no multi-placeholder strings found — the detector is broken');
        $this->assertSame([], $offenders,
            "Strings with two or more placeholders must be numbered (%1\$s, %2\$s) so a translator can reorder them:\n  "
            . implode("\n  ", $offenders));
    }

    /** Numbering must be 1..N with no gaps or repeats — `%1$s %3$s` silently drops an argument. */
    #[Test]
    public function numbered_placeholders_are_contiguous(): void
    {
        $offenders = [];
        foreach ($this->languageFiles() as $path => $strings) {
            foreach ($strings as $key => $value) {
                if (!is_string($value)) { continue; }
                preg_match_all('~%(\d+)\$[sd]~', $value, $m);
                if (!$m[1]) { continue; }
                $nums = array_map('intval', array_unique($m[1]));
                sort($nums);
                if ($nums !== range(1, count($nums))) {
                    $offenders[] = sprintf('%s: %s uses %s', $this->rel($path), $key, implode(',', $nums));
                }
            }
        }
        $this->assertSame([], $offenders, "Numbered placeholders must run 1..N:\n  " . implode("\n  ", $offenders));
    }

    /**
     * Every locale carries the same placeholder SET as English, per key.
     *
     * By set, not by order — a translation is allowed to say `%2$s of %1$s`. What it may not do is lose
     * one (an argument silently vanishes) or invent one (renders literally).
     */
    #[Test]
    public function every_locale_keeps_the_english_placeholder_set(): void
    {
        $files = $this->languageFiles();
        $byDir = [];
        foreach ($files as $path => $strings) {
            // .../languages/<lang>/<name>.php  → group by the languages/ dir, keyed by lang
            if (!preg_match('~^(.*/languages)/([^/]+)/([^/]+)$~', $path, $m)) { continue; }
            $byDir[$m[1]][$m[2]] = $strings;
        }

        $offenders = [];
        foreach ($byDir as $dir => $langs) {
            if (!isset($langs['en'])) { continue; }
            foreach ($langs['en'] as $key => $en) {
                if (!is_string($en)) { continue; }
                $want = $this->placeholderSet($en);
                foreach ($langs as $lang => $strings) {
                    if ($lang === 'en' || !isset($strings[$key]) || !is_string($strings[$key])) { continue; }
                    $got = $this->placeholderSet($strings[$key]);
                    if ($got !== $want) {
                        $offenders[] = sprintf('%s/%s: %s  en=[%s] %s=[%s]',
                            $this->rel($dir), $lang, $key, implode(' ', $want), $lang, implode(' ', $got));
                    }
                }
            }
        }
        $this->assertSame([], $offenders, "Placeholder sets differ from English:\n  " . implode("\n  ", $offenders));
    }

    /** Placeholders normalised to a sorted set — type-preserving, position-free. */
    private function placeholderSet(string $s): array
    {
        $set = [];
        foreach ($this->placeholders($s) as $p) {
            // %2$s and %s both count as an 's' slot; the SET is what must match, not the numbering.
            $set[] = substr($p, -1);
        }
        sort($set);
        return $set;
    }

    private function rel(string $path): string
    {
        return ltrim(str_replace(TIGER_CORE_PATH, '', $path), '/');
    }
}
