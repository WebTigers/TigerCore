<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Module;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Module_Registry;

/**
 * Tiger_Module_Registry — the client for the open Vendor Registry. Driven with NO network by PRE-SEEDING
 * the fresh file cache the client reads before it ever calls GitHub: a primed `registry-index.json` makes
 * index()/search()/taxonomy() resolve offline. Covered: search filtering, the neutral orderings (the
 * directory has NO paid placement — promotion is on-platform), repo-relative media resolution (logo /
 * screenshots / YouTube+Vimeo+mp4 video), the taxonomy, and the config-overridable index URL. The genuine
 * HTTP fetch + the offline-null fallback are live territory, left to integration.
 */
#[CoversClass(Tiger_Module_Registry::class)]
final class RegistryTest extends UnitTestCase
{
    private string $cacheDir = '';
    /** Cache files written by a test (removed in tearDown). */
    private array $wrote = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheDir = rtrim(APPLICATION_ROOT, '/') . '/storage/cache';
        @mkdir($this->cacheDir, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->wrote as $f) { @unlink($f); }
        parent::tearDown();
    }

    private function primeIndex(array $index): void
    {
        $file = $this->cacheDir . '/registry-index.json';
        file_put_contents($file, json_encode($index));
        $this->wrote[] = $file;
    }

    /** A minimal two-module index (Acme/Widget, Beta/Gadget). */
    private function primeTwoModuleIndex(): void
    {
        $this->primeIndex(['modules' => [
            [
                'module'      => 'Widget',
                'slug'        => 'widget',
                'description' => 'A handy widget',
                'keywords'    => ['ui', 'tool'],
                'vendor'      => 'Acme',
                'type'        => 'plugin',
                'repository'  => 'https://github.com/Acme/Widget',
                'review'      => ['reviewed_at' => '2026-01-01'],
            ],
            [
                'module'      => 'Gadget',
                'slug'        => 'gadget',
                'description' => 'A shiny gadget',
                'keywords'    => ['theme'],
                'vendor'      => 'Beta',
                'type'        => 'theme',
                'repository'  => 'https://github.com/Beta/Gadget',
                'review'      => ['reviewed_at' => '2026-06-01'],
            ],
        ]]);
    }

    // ---- availability / index ----------------------------------------------

    #[Test]
    public function available_is_true_when_the_index_cache_is_fresh(): void
    {
        $this->primeTwoModuleIndex();
        $this->assertTrue(Tiger_Module_Registry::available());
        $this->assertIsArray(Tiger_Module_Registry::index());
    }

    // ---- taxonomy (the data-driven filter vocabulary) ----------------------

    #[Test]
    public function taxonomy_returns_the_types_and_categories_folded_into_the_index(): void
    {
        $this->primeIndex([
            'taxonomy' => [
                'types'      => [['id' => 'app', 'label' => 'Apps'], ['id' => 'developer', 'label' => 'Developer']],
                'categories' => [['id' => 'commerce', 'label' => 'Commerce', 'types' => ['app']]],
            ],
            'modules' => [],
        ]);
        $tax = Tiger_Module_Registry::taxonomy();
        $this->assertSame(['app', 'developer'], array_column($tax['types'], 'id'));
        $this->assertSame(['commerce'], array_column($tax['categories'], 'id'));
    }

    #[Test]
    public function taxonomy_is_empty_arrays_when_the_index_predates_it(): void
    {
        $this->primeTwoModuleIndex();                 // an index with no `taxonomy` key
        $tax = Tiger_Module_Registry::taxonomy();
        $this->assertSame([], $tax['types']);
        $this->assertSame([], $tax['categories']);
    }

    // ---- search filtering ---------------------------------------------------

    #[Test]
    public function search_with_no_query_returns_every_module(): void
    {
        $this->primeTwoModuleIndex();
        $this->assertCount(2, Tiger_Module_Registry::search(''));
    }

    #[Test]
    public function search_matches_across_name_slug_description_keywords_vendor_and_type(): void
    {
        $this->primeTwoModuleIndex();
        $this->assertCount(1, Tiger_Module_Registry::search('widget'), 'name/slug hit');
        $this->assertCount(1, Tiger_Module_Registry::search('shiny'), 'description hit');
        $this->assertCount(1, Tiger_Module_Registry::search('theme'), 'keyword/type hit — Gadget');
        $this->assertSame([], Tiger_Module_Registry::search('nonexistent-term'), 'a miss returns []');
    }

    // ---- orderings (the directory is neutral — no paid placement) -----------

    #[Test]
    public function the_directory_is_neutral_no_priority_or_sponsored_fields(): void
    {
        $this->primeTwoModuleIndex();
        $widget = Tiger_Module_Registry::search('widget')[0];
        $this->assertArrayNotHasKey('priority', $widget, 'no paid-placement priority in the directory');
        $this->assertArrayNotHasKey('sponsored', $widget, 'no sponsored badge — promotion is on-platform');
    }

    #[Test]
    public function featured_sort_preserves_the_index_neutral_order(): void
    {
        $this->primeTwoModuleIndex();   // primed order: Widget, then Gadget
        $rows = Tiger_Module_Registry::search('', 'featured');
        $this->assertSame(['widget', 'gadget'], [$rows[0]['slug'], $rows[1]['slug']], 'featured = the index order');
    }

    #[Test]
    public function title_sort_orders_alphabetically(): void
    {
        $this->primeTwoModuleIndex();
        $rows = Tiger_Module_Registry::search('', 'title');
        $this->assertSame(['gadget', 'widget'], [$rows[0]['slug'], $rows[1]['slug']]);
    }

    #[Test]
    public function latest_sort_orders_by_newest_review(): void
    {
        $this->primeTwoModuleIndex();
        $rows = Tiger_Module_Registry::search('', 'latest');
        $this->assertSame('gadget', $rows[0]['slug'], 'Gadget was reviewed more recently');
    }

    #[Test]
    public function an_unknown_sort_falls_back_to_featured(): void
    {
        $this->primeTwoModuleIndex();
        $rows = Tiger_Module_Registry::search('', 'bogus-sort');
        $this->assertSame('widget', $rows[0]['slug']);   // featured = index order (Widget first)
    }

    // ---- media resolution ---------------------------------------------------

    #[Test]
    public function repo_relative_media_resolves_to_raw_urls_and_video_providers_become_embeds(): void
    {
        $this->primeIndex(['modules' => [[
            'module'      => 'Media',
            'slug'        => 'media',
            'repository'  => 'https://github.com/Acme/Media',
            'ref'         => 'v2.0.0',
            'logo'        => 'assets/logo.png',
            'hero'        => 'https://cdn.example/hero.png',   // already absolute — passed through
            'screenshots' => ['assets/a.png', 'assets/b.png'],
            'video'       => ['src' => 'https://youtu.be/abc123', 'poster' => 'assets/poster.png'],
        ]]]);

        $m = Tiger_Module_Registry::search('media')[0];
        $this->assertSame('https://raw.githubusercontent.com/Acme/Media/v2.0.0/assets/logo.png', $m['logo']);
        $this->assertSame('https://cdn.example/hero.png', $m['hero'], 'an absolute URL is untouched');
        $this->assertSame('https://raw.githubusercontent.com/Acme/Media/v2.0.0/assets/a.png', $m['screenshots'][0]);
        $this->assertSame('https://www.youtube-nocookie.com/embed/abc123', $m['video']['src']);
        $this->assertSame('iframe', $m['video']['type']);
        $this->assertStringStartsWith('https://raw.githubusercontent.com/Acme/Media/v2.0.0/', $m['video']['poster']);
    }

    #[Test]
    public function a_vimeo_video_becomes_a_player_embed_and_a_self_hosted_mp4_resolves_to_raw(): void
    {
        $this->primeIndex(['modules' => [
            ['module' => 'Vim', 'slug' => 'vim', 'repository' => 'https://github.com/Acme/Vim',
             'video' => 'https://vimeo.com/76543'],
            ['module' => 'Mp4', 'slug' => 'mp4', 'repository' => 'https://github.com/Acme/Mp4',
             'video' => 'media/demo.mp4'],
        ]]);

        $vim = Tiger_Module_Registry::search('vim')[0];
        $this->assertSame('https://player.vimeo.com/video/76543', $vim['video']['src']);
        $this->assertSame('iframe', $vim['video']['type']);

        $mp4 = Tiger_Module_Registry::search('mp4')[0];
        $this->assertStringEndsWith('/media/demo.mp4', $mp4['video']['src']);
        $this->assertSame('video', $mp4['video']['type']);
    }

    // ---- config-overridable endpoints --------------------------------------

    #[Test]
    public function index_url_defaults_then_honors_a_config_override(): void
    {
        $this->assertSame(Tiger_Module_Registry::DEFAULT_INDEX, Tiger_Module_Registry::indexUrl());

        $this->setConfig(['tiger' => ['modules' => ['registry' => 'https://example.test/index.json']]]);
        $this->assertSame('https://example.test/index.json', Tiger_Module_Registry::indexUrl());
    }
}
