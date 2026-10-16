<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\View;

use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Zend_View;

/**
 * The public layout's `<meta name="generator" content="Tiger">`.
 *
 * Two things are worth proving, and neither is provable by reading the file:
 *
 * 1. The GATE works. The tag is on by default so every install is countable by the technology
 *    surveys (W3Techs/BuiltWith/Wappalyzer) — that visibility is the whole point of shipping it —
 *    and a site hides it with "Hide Tiger meta tag" (Settings > CMS > Site Settings), which sets
 *    `tiger.site.generator_meta` to `0` and makes Tiger_Application_Bootstrap seed `showGenerator = false`.
 *    A view built outside the bootstrap has no such property at all, and must still emit.
 *    So the real block is lifted out of the shipped layout and RENDERED in all three states.
 *
 * 2. It carries NAME ONLY. A version string here would hand a vulnerability scanner the exact build
 *    to match CVEs against, across an install base nobody patches on our schedule. The content must
 *    stay a literal — no `<?=`, no Tiger_Version, no interpolation of any kind.
 */
final class GeneratorMetaTest extends UnitTestCase
{
    private const LAYOUT = TIGER_CORE_PATH . '/themes/puma/layouts/scripts/layout.phtml';

    private string $tmp = '';

    protected function tearDown(): void
    {
        if ($this->tmp !== '' && is_dir($this->tmp)) {
            foreach ((array) glob($this->tmp . '/*') as $f) { @unlink($f); }
            @rmdir($this->tmp);
        }
        parent::tearDown();
    }

    /** The generator block as the layout actually ships it. */
    private function block(): string
    {
        $layout = (string) file_get_contents(self::LAYOUT);
        $ok = preg_match('~^<\?php if \(.*showGenerator.*\n.*\n<\?php endif; \?>$~m', $layout, $m);
        $this->assertSame(1, $ok, 'the layout still carries a showGenerator-gated block');
        return $m[0];
    }

    /** Render that block with $this->showGenerator in a given state ('unset' = never assigned). */
    private function render($state): string
    {
        $this->tmp = $this->tmp ?: (string) tempnam(sys_get_temp_dir(), 'gen');
        if (is_file($this->tmp)) { unlink($this->tmp); mkdir($this->tmp, 0700); }
        file_put_contents($this->tmp . '/frag.phtml', $this->block());

        $view = new Zend_View();
        $view->addScriptPath($this->tmp);
        if ($state !== 'unset') { $view->showGenerator = $state; }

        ob_start();                                  // beStrictAboutOutputDuringTests
        $out = $view->render('frag.phtml');
        ob_end_clean();
        return (string) $out;
    }

    // ----- the gate -----------------------------------------------------------------------------

    #[Test]
    public function it_emits_the_tag_when_the_site_has_not_hidden_it(): void
    {
        $this->assertStringContainsString('<meta name="generator" content="Tiger">', $this->render(true));
    }

    #[Test]
    public function it_emits_nothing_when_the_site_hides_it(): void
    {
        $this->assertSame('', trim($this->render(false)), 'hidden means no tag at all, not an empty one');
    }

    #[Test]
    public function it_defaults_to_emitting_when_the_view_was_built_outside_the_bootstrap(): void
    {
        $this->assertStringContainsString('<meta name="generator" content="Tiger">', $this->render('unset'));
    }

    // ----- the shipped default ------------------------------------------------------------------

    #[Test]
    public function core_ini_ships_the_flag_on_so_the_default_is_declared_not_inferred(): void
    {
        // A feature flag needs a stated default. Leaving it to the absence of a key means nobody can
        // read the shipped behaviour off the config, and "on" would be an accident of the code.
        $ini = parse_ini_file(TIGER_CORE_PATH . '/configs/core.ini', true, INI_SCANNER_RAW);

        $this->assertArrayHasKey('tiger.site.generator_meta', $ini['production'],
            'the default lives in the stock core ini, beside the other site-identity keys');
        $this->assertSame('1', (string) $ini['production']['tiger.site.generator_meta'],
            'shipped ON — an install that emits nothing is counted by nobody');
    }

    // ----- name only, forever -------------------------------------------------------------------

    #[Test]
    public function the_content_is_a_literal_and_never_carries_a_version(): void
    {
        $block = $this->block();
        $this->assertMatchesRegularExpression(
            '~<meta name="generator" content="Tiger">~', $block,
            'content is the bare product name'
        );
        $this->assertDoesNotMatchRegularExpression(
            '~content="Tiger[^"]~', $block,
            'nothing may follow "Tiger" inside content — a version here is a gift to vulnerability scanners'
        );
        foreach (['<?=', 'Tiger_Version', 'VERSION', '$this->version'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $block, "no $forbidden in the generator meta");
        }
    }
}
