<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Model_Page;
use Tiger_View_Helper_LegalLinks;
use Zend_Config;
use Zend_Registry;

/**
 * Tiger_View_Helper_LegalLinks against a REAL page table (TIGER-119).
 *
 * The unit tests stub the page lookup to pin the config-vs-page precedence. That leaves the lookup
 * itself — the part that decides whether a link appears at all — untested, and mutation testing
 * proved it: removing the "is there a page?" check and dropping the published-only restriction both
 * survived the unit suite untouched. A footer that links a draft, or links a page that does not
 * exist, is exactly the failure this helper was written to prevent.
 */
#[CoversClass(Tiger_View_Helper_LegalLinks::class)]
final class LegalLinksResolveTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Tiger_View_Helper_LegalLinks::reset();
        // No overrides: the page lookup is what is under test here.
        Zend_Registry::set('Zend_Config', new Zend_Config(['tiger' => ['footer' => []]]));
    }

    protected function tearDown(): void
    {
        Tiger_View_Helper_LegalLinks::reset();
        parent::tearDown();
    }

    private function page(string $slug, string $status): void
    {
        (new Tiger_Model_Page())->insert([
            'page_key' => 'legal-' . $slug, 'slug' => $slug,
            'title'    => ucfirst($slug), 'type' => Tiger_Model_Page::TYPE_PAGE,
            'status'   => $status, 'format' => Tiger_Model_Page::FORMAT_HTML,
            'body'     => '<p>legal</p>', 'locale' => 'en',
        ]);
    }

    /** With nothing published, the footer shows nothing — a customer install with no legal pages. */
    #[Test]
    public function no_page_means_no_link(): void
    {
        $links = (new Tiger_View_Helper_LegalLinks())->legalLinks();

        $this->assertNull($links['privacy'], 'a link to a page that does not exist is worse than none');
        $this->assertNull($links['terms']);
    }

    /** Publish it and the link appears, with nothing configured. That is the whole feature. */
    #[Test]
    public function a_published_page_produces_the_link(): void
    {
        $this->page('privacy', Tiger_Model_Page::STATUS_PUBLISHED);

        $this->assertSame('/privacy', (new Tiger_View_Helper_LegalLinks())->legalLinks()['privacy']);
    }

    /** A DRAFT must not be linked — the footer would publish it to the world ahead of its author. */
    #[Test]
    public function a_draft_is_not_linked(): void
    {
        $this->page('privacy', Tiger_Model_Page::STATUS_DRAFT);

        $this->assertNull((new Tiger_View_Helper_LegalLinks())->legalLinks()['privacy'],
            'an unpublished policy is not a policy anyone should be sent to');
    }

    /** Nor is an archived one — it was deliberately taken down. */
    #[Test]
    public function an_archived_page_is_not_linked(): void
    {
        $this->page('terms', Tiger_Model_Page::STATUS_ARCHIVED);

        $this->assertNull((new Tiger_View_Helper_LegalLinks())->legalLinks()['terms']);
    }

    /** The two resolve independently: one published, one not. */
    #[Test]
    public function each_slug_is_resolved_separately(): void
    {
        $this->page('privacy', Tiger_Model_Page::STATUS_PUBLISHED);
        $this->page('terms', Tiger_Model_Page::STATUS_DRAFT);

        $links = (new Tiger_View_Helper_LegalLinks())->legalLinks();

        $this->assertSame('/privacy', $links['privacy']);
        $this->assertNull($links['terms']);
    }

    /**
     * Only a real PAGE counts — not a layout, partial or block that happens to share the slug.
     *
     * The slug space is shared across CMS types, so a published partial named `privacy` would
     * otherwise become the thing the footer sends people to. Mutation testing found this: dropping
     * the type restriction changed nothing the other tests could see.
     */
    #[Test]
    public function a_partial_sharing_the_slug_is_not_linked(): void
    {
        (new Tiger_Model_Page())->insert([
            'page_key' => 'legal-privacy-partial', 'slug' => 'privacy',
            'title'    => 'Privacy', 'type' => Tiger_Model_Page::TYPE_PARTIAL,
            'status'   => Tiger_Model_Page::STATUS_PUBLISHED, 'format' => Tiger_Model_Page::FORMAT_HTML,
            'body'     => '<p>fragment</p>', 'locale' => 'en',
        ]);

        $this->assertNull((new Tiger_View_Helper_LegalLinks())->legalLinks()['privacy'],
            'a partial is not a page a visitor can be sent to');
    }

    /** An explicit override still wins over a real published page. */
    #[Test]
    public function the_config_override_beats_a_real_page(): void
    {
        $this->page('privacy', Tiger_Model_Page::STATUS_PUBLISHED);
        Zend_Registry::set('Zend_Config', new Zend_Config([
            'tiger' => ['footer' => ['privacy_url' => 'https://legal.example.com/privacy']],
        ]));

        $this->assertSame('https://legal.example.com/privacy',
            (new Tiger_View_Helper_LegalLinks())->legalLinks()['privacy']);
    }
}
