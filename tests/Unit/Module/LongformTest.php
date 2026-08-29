<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Module;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Module_Longform;

/**
 * Tiger_Module_Longform — the one resolver + renderer for a listing's long-form "plugin page" copy,
 * shared by the Module Manager's "View more" and a marketplace's own listing page.
 *
 * The load-bearing tests are the SAFETY ones. This copy is written by whoever published the module,
 * so raw HTML must be escaped at parse time (not stripped afterwards), dangerous URL schemes must be
 * filtered, and a body offered over plain http must never be fetched at all.
 */
#[CoversClass(Tiger_Module_Longform::class)]
final class LongformTest extends UnitTestCase
{
    protected function tearDown(): void
    {
        Tiger_Module_Longform::setTransport(null);
        parent::tearDown();
    }

    #[Test]
    public function readmeIsTheCanonicalInlineField(): void
    {
        $this->assertSame('# hello', Tiger_Module_Longform::markdown(['readme' => '# hello']));
    }

    /** `body` predates the shared name; a listing authored against it must keep rendering. */
    #[Test]
    public function bodyIsAcceptedAsAnAlias(): void
    {
        $this->assertSame('# hello', Tiger_Module_Longform::markdown(['body' => '# hello']));
    }

    #[Test]
    public function readmeWinsOverBody(): void
    {
        $this->assertSame('# a', Tiger_Module_Longform::markdown(['readme' => '# a', 'body' => '# b']));
    }

    /** A private (paid) module carries its copy inline; the URL is only for public repos. */
    #[Test]
    public function inlineCopyWinsOverAFetchedUrl(): void
    {
        Tiger_Module_Longform::setTransport(static fn ($url) => '# fetched');

        $this->assertSame('# inline', Tiger_Module_Longform::markdown([
            'readme'   => '# inline',
            'tiger_md' => 'https://example.test/TIGER.md',
        ]));
    }

    #[Test]
    public function fetchesTigerMdWhenThereIsNoInlineCopy(): void
    {
        $seen = null;
        Tiger_Module_Longform::setTransport(function ($url) use (&$seen) {
            $seen = $url;
            return "# Hello\n\nA paragraph.";
        });

        $md = Tiger_Module_Longform::markdown(['tiger_md' => 'https://example.test/a/TIGER.md']);

        $this->assertSame('https://example.test/a/TIGER.md', $seen);
        $this->assertStringContainsString('# Hello', $md);
    }

    #[Test]
    public function aListingWithNeitherYieldsNothing(): void
    {
        $this->assertSame('', Tiger_Module_Longform::markdown([]));
        $this->assertSame('', Tiger_Module_Longform::html([]));
    }

    /** A body served over plain http can be rewritten in transit — refuse it before fetching. */
    #[Test]
    public function refusesANonHttpsUrl(): void
    {
        $called = false;
        Tiger_Module_Longform::setTransport(function () use (&$called) { $called = true; return '# nope'; });

        $this->assertSame('', Tiger_Module_Longform::markdown(['tiger_md' => 'http://example.test/TIGER.md']));
        $this->assertFalse($called, 'an http:// body must never even be fetched');
    }

    #[Test]
    public function rendersMarkdownToHtml(): void
    {
        $html = Tiger_Module_Longform::render("# Title\n\nSome **bold** copy.");

        $this->assertStringContainsString('<h1>Title</h1>', $html);
        $this->assertStringContainsString('<strong>bold</strong>', $html);
    }

    /** Safe mode: a third party's markdown may not inject markup into the admin screen. */
    #[Test]
    public function escapesRawHtmlInUntrustedMarkdown(): void
    {
        $html = Tiger_Module_Longform::render('Hi <script>alert(1)</script> there');

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /**
     * The whole tag is escaped to text, so the handler never becomes an attribute. Asserting the
     * substring `onerror=` is absent would be wrong — it is still THERE, as inert escaped text.
     * What matters is that no live element was emitted.
     */
    #[Test]
    public function escapesAnEventHandlerAttribute(): void
    {
        $html = Tiger_Module_Longform::render('<img src=x onerror="alert(1)">');

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;img', $html);
        $this->assertStringContainsString('&quot;', $html);
    }

    #[Test]
    public function filtersJavascriptLinks(): void
    {
        $this->assertStringNotContainsString('javascript:alert', Tiger_Module_Longform::render('[click](javascript:alert(1))'));
    }

    #[Test]
    public function anEmptyBodyRendersNothing(): void
    {
        $this->assertSame('', Tiger_Module_Longform::render(''));
    }

    #[Test]
    public function anOversizedBodyIsRefused(): void
    {
        Tiger_Module_Longform::setTransport(
            static fn ($url) => str_repeat('x', Tiger_Module_Longform::MAX_BYTES + 1)
        );

        $this->assertSame('', Tiger_Module_Longform::markdown(['tiger_md' => 'https://example.test/big.md']));
    }

    #[Test]
    public function aFailedFetchIsSilent(): void
    {
        Tiger_Module_Longform::setTransport(static fn ($url) => null);

        $this->assertSame('', Tiger_Module_Longform::markdown(['tiger_md' => 'https://example.test/gone.md']));
    }

    /** html() is the composed path both surfaces actually call. */
    #[Test]
    public function htmlResolvesThenRenders(): void
    {
        $html = Tiger_Module_Longform::html(['readme' => "# Pay\n\nTake money."]);

        $this->assertStringContainsString('<h1>Pay</h1>', $html);
        $this->assertStringContainsString('<p>Take money.</p>', $html);
    }
}
