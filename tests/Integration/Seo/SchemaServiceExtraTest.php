<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Seo;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use Seo_Service_Schema;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Model_Media;
use Tiger_Model_Menu;
use Zend_Config;
use Zend_Controller_Request_Http;
use Zend_Registry;
use Zend_View;

/**
 * Seo_Service_Schema — the branches Wave 4/5/6's SchemaServiceTest left uncovered: the media-id image
 * resolution (Organization logo + Article image resolved through a real `media` row for an absolute URL
 * + true dimensions), the no-absolute-base short-circuit (emitSite/emitArticle), the empty-config nav
 * fallbacks (blank `nav_menu` key, a headings-only menu), and the view-less placeholder fallback.
 *
 * Sibling to SchemaServiceTest (which covers the happy paths) — this file targets ONLY the still-open
 * arms. Same harness: a process-wide Zend_View whose `tigerJsonLd` placeholder is read back + decoded,
 * with the emit-once latch reset per test.
 */
#[CoversClass(Seo_Service_Schema::class)]
final class SchemaServiceExtraTest extends IntegrationTestCase
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
        $this->view->placeholder('tigerJsonLd')->exchangeArray([]);

        $this->hadConfig   = Zend_Registry::isRegistered('Zend_Config');
        $this->priorConfig = $this->hadConfig ? Zend_Registry::get('Zend_Config') : null;

        $this->resetLatch();
    }

    protected function tearDown(): void
    {
        $this->resetLatch();
        $reg = Zend_Registry::getInstance();
        if ($reg->offsetExists('Zend_View')) { $reg->offsetUnset('Zend_View'); }
        if ($this->hadConfig) {
            Zend_Registry::set('Zend_Config', $this->priorConfig);
        } elseif ($reg->offsetExists('Zend_Config')) {
            $reg->offsetUnset('Zend_Config');
        }
        parent::tearDown();
    }

    private function resetLatch(): void
    {
        $p = new ReflectionProperty(Seo_Service_Schema::class, '_emitted');
        $p->setAccessible(true);
        $p->setValue(null, false);
    }

    private function config(array $tiger): void
    {
        Zend_Registry::set('Zend_Config', new Zend_Config(['tiger' => $tiger]));
    }

    private function request(string $uri = '/'): Zend_Controller_Request_Http
    {
        $_SERVER['HTTP_HOST'] = 'example.test';
        $_SERVER['HTTPS']     = 'on';
        $r = new Zend_Controller_Request_Http();
        $r->setRequestUri($uri);
        $r->setPathInfo($uri);
        return $r;
    }

    /** Insert an image `media` row and return its id — the target of a media-id image reference. */
    private function mediaRow(int $w = 800, int $h = 600): string
    {
        return (new Tiger_Model_Media())->insert([
            'org_id'      => '',
            'disk'        => 'local',
            'storage_key' => 'images/schema-shot.png',
            'visibility'  => Tiger_Model_Media::VISIBILITY_PUBLIC,
            'kind'        => Tiger_Model_Media::KIND_IMAGE,
            'mime_type'   => 'image/png',
            'extension'   => 'png',
            'filename'    => 'schema-shot.png',
            'width'       => $w,
            'height'      => $h,
        ]);
    }

    private function nodes(): array
    {
        $raw = (string) $this->view->placeholder('tigerJsonLd');
        $out = [];
        if (preg_match_all('#<script[^>]*>(.*?)</script>#s', $raw, $m)) {
            foreach ($m[1] as $json) {
                $data = json_decode($json, true);
                if (isset($data['@graph'])) {
                    foreach ($data['@graph'] as $n) { $out[] = $n; }
                }
            }
        }
        return $out;
    }

    private function nodeOfType(string $type): ?array
    {
        foreach ($this->nodes() as $n) {
            if (($n['@type'] ?? '') === $type) { return $n; }
        }
        return null;
    }

    // ----- media-id image resolution (the media-row lookup, absolute URL + real dimensions) ------

    #[Test]
    public function organization_logo_from_a_media_id_resolves_dimensions_and_an_absolute_url(): void
    {
        $id = $this->mediaRow(1024, 768);
        // A bare media id (not an http URL) exercises the media-row lookup + relative→absolute prefixing.
        $this->config(['site' => ['name' => 'Acme', 'logo' => $id]]);
        Seo_Service_Schema::emitSite($this->request('/'));

        $org = $this->nodeOfType('Organization');
        $this->assertArrayHasKey('logo', $org, 'the logo was resolved through the media row');
        $this->assertSame('ImageObject', $org['logo']['@type']);
        $this->assertEquals(1024, $org['logo']['width'], 'the media row supplies real pixel dimensions');
        $this->assertEquals(768, $org['logo']['height']);
        // No storage disk is configured, so url() falls to the stream route — prefixed to an absolute URL.
        $this->assertStringStartsWith('https://example.test/', $org['logo']['url']);
        $this->assertStringContainsString($id, $org['logo']['url']);
    }

    #[Test]
    public function article_feature_image_from_a_media_id_resolves_through_the_media_row(): void
    {
        $id = $this->mediaRow(640, 480);
        $this->config(['site' => ['name' => 'Acme']]);
        Seo_Service_Schema::emitArticle(
            (object) ['updated_at' => '2026-06-01 00:00:00'],
            [
                'title'        => 'With Media',
                'slug'         => 'with-media',
                'excerpt'      => 'Has a real media image.',
                'published_at' => '2026-05-20 00:00:00',
                'feature'      => ['id' => $id],
            ],
            $this->request('/blog/with-media')
        );

        $post = $this->nodeOfType('BlogPosting');
        $this->assertArrayHasKey('image', $post);
        $this->assertEquals(640, $post['image']['width']);
        $this->assertEquals(480, $post['image']['height']);
        $this->assertStringContainsString($id, $post['image']['url']);
    }

    #[Test]
    public function an_unresolvable_media_id_simply_omits_the_logo(): void
    {
        // A ref that is neither an http URL nor a real media row → _image returns null → no logo key.
        $this->config(['site' => ['name' => 'Acme', 'logo' => 'not-a-real-media-id']]);
        Seo_Service_Schema::emitSite($this->request('/'));
        $this->assertArrayNotHasKey('logo', $this->nodeOfType('Organization'));
    }

    // ----- no-absolute-base short-circuits ------------------------------------------------------

    #[Test]
    public function emit_site_skips_entirely_when_there_is_no_absolute_base(): void
    {
        // No request AND no tiger.site.url → _base() == '' → emitSite bails before emitting any node.
        $this->config(['site' => ['name' => 'Acme']]);
        Seo_Service_Schema::emitSite(null);
        $this->assertNull($this->nodeOfType('Organization'), 'nothing anchored on a relative base is emitted');
    }

    #[Test]
    public function emit_article_skips_entirely_when_there_is_no_absolute_base(): void
    {
        $this->config(['site' => ['name' => 'Acme']]);
        Seo_Service_Schema::emitArticle(
            (object) ['updated_at' => ''],
            ['title' => 'Homeless', 'slug' => 'homeless', 'published_at' => '2026-01-01 00:00:00'],
            null
        );
        $this->assertNull($this->nodeOfType('BlogPosting'), 'no base → no article node');
    }

    // ----- nav fallbacks ------------------------------------------------------------------------

    #[Test]
    public function a_blank_nav_menu_config_falls_back_to_the_primary_menu(): void
    {
        $menu = new Tiger_Model_Menu();
        $menu->insert(['org_id' => '', 'menu_key' => 'primary', 'parent_id' => null, 'sort_order' => 0, 'label' => 'Store', 'url' => '/store', 'status' => Tiger_Model_Menu::STATUS_PUBLISHED]);

        // An explicitly blank nav_menu key must not blank the nav — it defaults back to 'primary'.
        $this->config(['site' => ['name' => 'Acme'], 'seo' => ['schema' => ['nav_menu' => '']]]);
        Seo_Service_Schema::emitSite($this->request('/'));

        $nav = $this->nodeOfType('SiteNavigationElement');
        $this->assertNotNull($nav, 'a blank nav_menu falls back to the primary menu');
        $this->assertContains('Store', $nav['name']);
    }

    #[Test]
    public function a_menu_of_only_headings_emits_no_nav_element(): void
    {
        $menu = new Tiger_Model_Menu();
        // Every item is a heading (no url) or a dead placeholder (#) → no real links → no nav node.
        $menu->insert(['org_id' => '', 'menu_key' => 'primary', 'parent_id' => null, 'sort_order' => 0, 'label' => 'Section', 'url' => '', 'status' => Tiger_Model_Menu::STATUS_PUBLISHED]);
        $menu->insert(['org_id' => '', 'menu_key' => 'primary', 'parent_id' => null, 'sort_order' => 1, 'label' => 'Dead',    'url' => '#', 'status' => Tiger_Model_Menu::STATUS_PUBLISHED]);

        $this->config(['site' => ['name' => 'Acme']]);
        Seo_Service_Schema::emitSite($this->request('/'));
        $this->assertNull($this->nodeOfType('SiteNavigationElement'), 'a headings-only menu yields no nav element');
    }

    // ----- view-less placeholder fallback -------------------------------------------------------

    #[Test]
    public function emit_still_works_when_no_view_is_registered(): void
    {
        // Unregister the shared view → _view() constructs a bare Zend_View. Placeholder containers are a
        // process-wide singleton registry, so the nodes still land in the container this test reads back.
        $reg = Zend_Registry::getInstance();
        if ($reg->offsetExists('Zend_View')) { $reg->offsetUnset('Zend_View'); }

        $this->config(['site' => ['name' => 'Acme']]);
        Seo_Service_Schema::emitSite($this->request('/'));

        // Re-register a view to read the shared placeholder back.
        Zend_Registry::set('Zend_View', $this->view);
        $this->assertNotNull($this->nodeOfType('Organization'), 'the org node still emitted via the fallback view');
    }
}
