<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\View;

use Tiger\Tests\Support\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger_View_Helper_LegalLinks;
use Zend_Config;
use Zend_Registry;

/**
 * Tiger_View_Helper_LegalLinks — the footer's privacy/terms links (TIGER-119).
 *
 * The bug this exists for: the links rendered ONLY from config, so webtigers.com ran seven weeks with
 * a published privacy policy that nothing linked to. The pages and the config were separate steps and
 * one silently didn't happen — an unset config looks exactly like "this site shows no legal links".
 *
 * So the rule is: explicit config wins; otherwise a PUBLISHED page at the slug provides the link;
 * otherwise nothing. These tests drive the config and stub the page lookup, because what is under
 * test is that precedence — the "is this page live" question belongs to Tiger_Model_Page and is
 * tested there.
 */
#[CoversClass(Tiger_View_Helper_LegalLinks::class)]
final class LegalLinksTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Tiger_View_Helper_LegalLinks::reset();
        ProbeLegalLinks::$published = [];
        ProbeLegalLinks::$lookups   = 0;
    }

    protected function tearDown(): void
    {
        Tiger_View_Helper_LegalLinks::reset();
        parent::tearDown();
    }

    private function config(array $footer): void
    {
        Zend_Registry::set('Zend_Config', new Zend_Config(['tiger' => ['footer' => $footer]]));
    }

    /** The whole point: publish the page, get the link, with nothing configured. */
    #[Test]
    public function a_published_page_provides_the_link_with_no_config_at_all(): void
    {
        $this->config([]);
        ProbeLegalLinks::$published = ['privacy', 'terms'];

        $links = (new ProbeLegalLinks())->legalLinks();

        $this->assertSame('/privacy', $links['privacy']);
        $this->assertSame('/terms', $links['terms']);
    }

    /** No page, no link — a customer install with no legal pages must not get a dead link. */
    #[Test]
    public function nothing_published_means_no_links(): void
    {
        $this->config([]);

        $links = (new ProbeLegalLinks())->legalLinks();

        $this->assertNull($links['privacy']);
        $this->assertNull($links['terms']);
    }

    /** Explicit config wins, for a site whose legal pages live elsewhere. */
    #[Test]
    public function explicit_config_overrides_a_published_page(): void
    {
        $this->config(['privacy_url' => 'https://legal.example.com/privacy']);
        ProbeLegalLinks::$published = ['privacy', 'terms'];

        $links = (new ProbeLegalLinks())->legalLinks();

        $this->assertSame('https://legal.example.com/privacy', $links['privacy'],
            'the override must win, or a site cannot point somewhere else');
        $this->assertSame('/terms', $links['terms'], 'and the other link still falls back');
    }

    /** The two links are independent — one configured, one derived, one absent. */
    #[Test]
    public function each_link_resolves_on_its_own(): void
    {
        $this->config(['terms_url' => '/legal/terms']);
        ProbeLegalLinks::$published = [];

        $links = (new ProbeLegalLinks())->legalLinks();

        $this->assertNull($links['privacy'], 'unpublished and unconfigured is still nothing');
        $this->assertSame('/legal/terms', $links['terms']);
    }

    /** A blank config value is not a URL — it must fall through, not render an empty href. */
    #[Test]
    public function a_blank_config_value_falls_through_to_the_page(): void
    {
        $this->config(['privacy_url' => '   ']);
        ProbeLegalLinks::$published = ['privacy'];

        $this->assertSame('/privacy', (new ProbeLegalLinks())->legalLinks()['privacy'],
            'whitespace is an unset value, not a link to nowhere');
    }

    /** The footer renders on every public page, so the lookup happens once per request. */
    #[Test]
    public function the_lookup_is_memoized(): void
    {
        $this->config([]);
        ProbeLegalLinks::$published = ['privacy', 'terms'];

        $h = new ProbeLegalLinks();
        $h->legalLinks();
        $h->legalLinks();
        $h->legalLinks();

        $this->assertSame(2, ProbeLegalLinks::$lookups,
            'one lookup per link for the whole request, however often the footer asks');
    }

    /** A configured site never touches the database at all. */
    #[Test]
    public function an_explicitly_configured_site_does_no_lookup(): void
    {
        $this->config(['privacy_url' => '/privacy', 'terms_url' => '/terms']);

        (new ProbeLegalLinks())->legalLinks();

        $this->assertSame(0, ProbeLegalLinks::$lookups, 'config short-circuits the page query');
    }

    /**
     * A footer must never be the reason a page 500s.
     *
     * Exercises the REAL _published() with no database adapter — a half-built install, or any request
     * that renders a footer before the DB is up. Stubbing the lookup would only test the stub, so
     * this drives the actual guard.
     */
    #[Test]
    public function no_database_is_no_link_rather_than_an_exception(): void
    {
        $this->config([]);
        $previous = \Zend_Db_Table_Abstract::getDefaultAdapter();
        \Zend_Db_Table_Abstract::setDefaultAdapter(null);

        try {
            $links = (new Tiger_View_Helper_LegalLinks())->legalLinks();
            $this->assertNull($links['privacy'], 'no adapter must mean no link, not a fatal');
            $this->assertNull($links['terms']);
        } finally {
            \Zend_Db_Table_Abstract::setDefaultAdapter($previous);
        }
    }

    /** With no config registered at all, the helper still answers. */
    #[Test]
    public function no_config_in_the_registry_is_survivable(): void
    {
        if (Zend_Registry::isRegistered('Zend_Config')) { Zend_Registry::set('Zend_Config', null); }

        $links = (new ProbeLegalLinks())->legalLinks();

        $this->assertNull($links['privacy']);
        $this->assertNull($links['terms']);
    }
}

/** Stubs the page lookup so the PRECEDENCE is what gets tested, not the page model. */
final class ProbeLegalLinks extends Tiger_View_Helper_LegalLinks
{
    /** @var string[] slugs that have a live page */
    public static array $published = [];
    public static int $lookups = 0;

    protected function _published($slug)
    {
        self::$lookups++;
        return in_array($slug, self::$published, true) ? '/' . $slug : null;
    }
}
