<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Seo;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Seo_Service_Head;
use Tiger\Tests\Support\IntegrationTestCase;
use Zend_Config;
use Zend_Controller_Request_Http;
use Zend_Registry;
use Zend_View;

/**
 * Seo_Service_Head::site() — the SITE-level head baseline, the last-resort tier.
 *
 * `forRow()` only ever runs for a CMS page row or a blog article, so the SHIPPED marketing pages
 * (/vibe, /agency, …) — plain controller actions with no `page` row — emitted no `og:*` at all. A
 * crawler with no `og:image` falls back to scraping the DOM, which on a Tiger page meant the language
 * switcher's flag `<img>`. `site()` closes that hole by emitting the configured site defaults.
 *
 * The contract these tests pin: it FILLS BLANKS ONLY. Anything a page already set — by forRow, an
 * article, or a view — always wins, which is what makes it safe to run late (postDispatch) on every
 * request without clobbering a page's own card.
 */
#[CoversClass(Seo_Service_Head::class)]
final class HeadServiceSiteTest extends IntegrationTestCase
{
    private ?Zend_View $view = null;
    private bool $hadConfig = false;
    private $priorConfig = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->view = new Zend_View();
        $this->view->doctype('HTML5');
        Zend_Registry::set('Zend_View', $this->view);

        // Placeholder containers are process-wide — clear so a prior test's tags don't bleed in.
        $this->view->headTitle()->getContainer()->exchangeArray([]);
        $this->view->headMeta()->getContainer()->exchangeArray([]);
        $this->view->headLink()->getContainer()->exchangeArray([]);

        $this->hadConfig   = Zend_Registry::isRegistered('Zend_Config');
        $this->priorConfig = $this->hadConfig ? Zend_Registry::get('Zend_Config') : null;
    }

    protected function tearDown(): void
    {
        $reg = Zend_Registry::getInstance();
        if ($reg->offsetExists('Zend_View')) { $reg->offsetUnset('Zend_View'); }
        if ($this->hadConfig) {
            Zend_Registry::set('Zend_Config', $this->priorConfig);
        } elseif ($reg->offsetExists('Zend_Config')) {
            $reg->offsetUnset('Zend_Config');
        }
        parent::tearDown();
    }

    private function config(array $tiger): void
    {
        Zend_Registry::set('Zend_Config', new Zend_Config(['tiger' => $tiger]));
    }

    private function request(string $uri = '/agency'): Zend_Controller_Request_Http
    {
        $_SERVER['HTTP_HOST'] = 'example.test';
        $_SERVER['HTTPS']     = 'on';
        $r = new Zend_Controller_Request_Http();
        $r->setRequestUri($uri);
        return $r;
    }

    private function meta(): string
    {
        return $this->view->headMeta()->toString();
    }

    /** The site config a real install carries (name + description + a default social image). */
    private function siteConfig(): void
    {
        $this->config([
            'site' => ['name' => 'Tiger', 'description' => 'The AI-native SaaS platform.'],
            'seo'  => ['og_image' => 'https://cdn.example.test/og-default.png'],
        ]);
    }

    // ----- the hole this closes -----------------------------------------------------------------

    #[Test]
    public function a_page_with_no_row_still_gets_a_full_social_card(): void
    {
        $this->siteConfig();
        $this->view->headTitle('For agencies — one install, every client site | Tiger');

        Seo_Service_Head::site($this->request('/agency'));
        $meta = $this->meta();

        // The whole point: an og:image exists, so a crawler never falls back to scraping the DOM.
        $this->assertStringContainsString('og:image', $meta);
        $this->assertStringContainsString('https://cdn.example.test/og-default.png', $meta);
        $this->assertStringContainsString('og:title', $meta);
        $this->assertStringContainsString('For agencies', $meta);
        $this->assertStringContainsString('The AI-native SaaS platform.', $meta);
        $this->assertStringContainsString('og:site_name', $meta);
        $this->assertStringContainsString('website', $meta);
        $this->assertStringContainsString('https://example.test/agency', $meta);
    }

    #[Test]
    public function a_resolved_image_earns_the_large_image_twitter_card(): void
    {
        $this->siteConfig();
        Seo_Service_Head::site($this->request());
        $this->assertStringContainsString('summary_large_image', $this->meta());
    }

    #[Test]
    public function no_configured_image_falls_back_to_the_small_card_and_emits_no_og_image(): void
    {
        $this->config(['site' => ['name' => 'Tiger']]);
        Seo_Service_Head::site($this->request());
        $meta = $this->meta();

        $this->assertStringNotContainsString('og:image', $meta);
        $this->assertStringContainsString('summary', $meta);
        $this->assertStringNotContainsString('summary_large_image', $meta);
    }

    // ----- fills blanks ONLY (why it's safe to run on every request) -----------------------------

    #[Test]
    public function a_pages_own_tags_are_never_clobbered(): void
    {
        $this->siteConfig();

        // What forRow() would already have emitted for a real CMS page.
        $m = $this->view->headMeta();
        $m->setProperty('og:title', 'The Page Title');
        $m->setProperty('og:description', 'The page description.');
        $m->setProperty('og:image', 'https://cdn.example.test/page-specific.png');
        $m->setProperty('og:type', 'article');
        $m->setName('twitter:card', 'summary');

        Seo_Service_Head::site($this->request());
        $meta = $this->meta();

        $this->assertStringContainsString('The Page Title', $meta);
        $this->assertStringContainsString('The page description.', $meta);
        $this->assertStringContainsString('page-specific.png', $meta);
        $this->assertStringContainsString('article', $meta);
        $this->assertStringNotContainsString('og-default.png', $meta, 'the site image must not override a page image');
        $this->assertStringNotContainsString('Tiger</', $meta, 'the site name must not override a page title');
        $this->assertStringNotContainsString('summary_large_image', $meta, 'an explicit twitter:card wins');
    }

    #[Test]
    public function an_existing_meta_description_is_reused_for_og_description(): void
    {
        $this->siteConfig();
        $this->view->headMeta()->setName('description', 'A description the page set itself.');

        Seo_Service_Head::site($this->request());
        $meta = $this->meta();

        $this->assertStringContainsString('A description the page set itself.', $meta);
        $this->assertStringNotContainsString('The AI-native SaaS platform.', $meta);
    }

    #[Test]
    public function running_twice_does_not_duplicate_tags(): void
    {
        $this->siteConfig();
        Seo_Service_Head::site($this->request());
        Seo_Service_Head::site($this->request());

        $this->assertSame(1, substr_count($this->meta(), 'og:image"'), 'og:image emitted once');
        $this->assertSame(1, substr_count($this->meta(), 'og:title"'), 'og:title emitted once');
    }

    // ----- the PAGE tier: authoring OG for a view page that has no `page` row --------------------

    /** A dispatched request (the page key comes from module/controller/action, not the URI). */
    private function dispatched(string $module, string $controller, string $action, string $uri = '/agency'): Zend_Controller_Request_Http
    {
        $r = $this->request($uri);
        $r->setModuleName($module)->setControllerName($controller)->setActionName($action);
        return $r;
    }

    #[Test]
    public function a_shipped_marketing_page_gets_a_key_that_reads_like_its_url(): void
    {
        // default/index/<action> collapses — the whole point is that an author addresses "agency".
        $this->assertSame('agency', Seo_Service_Head::pageKey($this->dispatched('default', 'index', 'agency')));
        $this->assertSame('get-tiger', Seo_Service_Head::pageKey($this->dispatched('default', 'index', 'get-tiger')));
        // anything else keeps the full triple so it can't collide
        $this->assertSame('blog-index-view', Seo_Service_Head::pageKey($this->dispatched('blog', 'index', 'view')));
    }

    #[Test]
    public function page_level_config_beats_the_site_defaults(): void
    {
        $this->config([
            'site' => ['name' => 'Tiger', 'description' => 'Site description.'],
            'seo'  => [
                'og_image' => 'https://cdn.example.test/og-default.png',
                'page'     => ['agency' => [
                    'title'       => 'Agency page title',
                    'description' => 'Agency page description.',
                    'image'       => 'https://cdn.example.test/og-agency.png',
                ]],
            ],
        ]);

        Seo_Service_Head::site($this->dispatched('default', 'index', 'agency'));
        $meta = $this->meta();

        $this->assertStringContainsString('Agency page title', $meta);
        $this->assertStringContainsString('Agency page description.', $meta);
        $this->assertStringContainsString('og-agency.png', $meta);
        $this->assertStringNotContainsString('og-default.png', $meta);
        $this->assertStringNotContainsString('Site description.', $meta);
    }

    #[Test]
    public function a_page_without_its_own_config_still_falls_back_to_the_site(): void
    {
        $this->config([
            'site' => ['name' => 'Tiger', 'description' => 'Site description.'],
            'seo'  => [
                'og_image' => 'https://cdn.example.test/og-default.png',
                'page'     => ['agency' => ['image' => 'https://cdn.example.test/og-agency.png']],
            ],
        ]);

        Seo_Service_Head::site($this->dispatched('default', 'index', 'vibe', '/vibe'));
        $meta = $this->meta();

        $this->assertStringContainsString('og-default.png', $meta, 'an unconfigured page uses the site image');
        $this->assertStringNotContainsString('og-agency.png', $meta);
        $this->assertStringContainsString('Site description.', $meta);
    }

    #[Test]
    public function page_level_config_still_loses_to_the_pages_own_tags(): void
    {
        // A CMS page (forRow) or an article already spoke — the authored view-page tier must not win.
        $this->config(['seo' => ['page' => ['agency' => ['image' => 'https://cdn.example.test/og-agency.png']]]]);
        $this->view->headMeta()->setProperty('og:image', 'https://cdn.example.test/row.png');

        Seo_Service_Head::site($this->dispatched('default', 'index', 'agency'));

        $this->assertStringContainsString('row.png', $this->meta());
        $this->assertStringNotContainsString('og-agency.png', $this->meta());
    }

    // ----- degradation --------------------------------------------------------------------------

    #[Test]
    public function it_is_silent_with_no_config_and_no_request(): void
    {
        $reg = Zend_Registry::getInstance();
        if ($reg->offsetExists('Zend_Config')) { $reg->offsetUnset('Zend_Config'); }

        Seo_Service_Head::site(null);

        // Nothing to say without a site name/description/image — but it must not throw, and must not
        // invent an og:url from a request it wasn't given.
        $this->assertStringNotContainsString('og:url', $this->meta());
    }
}
