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
 * index()/search()/taxonomy() resolve offline. Covered: search filtering, the orderings (`featured` floats
 * sponsored listings first — a `live-api` marketplace source flags them — while `title`/`latest` stay
 * neutral; the git directory itself ships no sponsored field), module-contributed sources via register(),
 * repo-relative media resolution (logo / screenshots / YouTube+Vimeo+mp4 video), the taxonomy, and the
 * config-overridable index URL. The genuine HTTP fetch + the offline-null fallback are live territory,
 * left to integration.
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
        $this->primeSourceCache('registry-index.json', $index);   // the Directory source's cache
    }

    /** Prime any source's fresh file cache so the client resolves it with no network. */
    private function primeSourceCache(string $cacheFile, array $index): void
    {
        $file = $this->cacheDir . '/' . $cacheFile;
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

    // ---- sponsored (featured floats promoted listings; a marketplace source flags them) --------

    #[Test]
    public function featured_floats_a_sponsored_listing_first_while_other_sorts_ignore_it(): void
    {
        // Alpha is first in index order and NOT sponsored; Beta is second but sponsored.
        $this->primeIndex(['modules' => [
            ['module' => 'Alpha', 'slug' => 'alpha', 'type' => 'app', 'repository' => 'https://github.com/x/alpha'],
            ['module' => 'Beta',  'slug' => 'beta',  'type' => 'app', 'repository' => 'https://github.com/x/beta',
             'sponsored' => true, 'sponsored_rank' => 3],
        ]]);
        $featured = Tiger_Module_Registry::search('', 'featured');
        $this->assertSame(['beta', 'alpha'], [$featured[0]['slug'], $featured[1]['slug']], 'the sponsored listing floats above the neutral one');
        // A buyer can always sort past promotion — title is pure alphabetical, sponsorship ignored.
        $title = Tiger_Module_Registry::search('', 'title');
        $this->assertSame(['alpha', 'beta'], [$title[0]['slug'], $title[1]['slug']]);
    }

    #[Test]
    public function featured_orders_multiple_sponsors_by_rank_descending(): void
    {
        $this->primeIndex(['modules' => [
            ['module' => 'Low',  'slug' => 'low',  'repository' => 'https://github.com/x/low',  'sponsored' => true, 'sponsored_rank' => 1],
            ['module' => 'High', 'slug' => 'high', 'repository' => 'https://github.com/x/high', 'sponsored' => true, 'sponsored_rank' => 9],
        ]]);
        $rows = Tiger_Module_Registry::search('', 'featured');
        $this->assertSame(['high', 'low'], [$rows[0]['slug'], $rows[1]['slug']], 'higher rank is checked first');
    }

    // ---- module-contributed sources (register) -------------------------------------------------

    #[Test]
    public function register_adds_a_module_source_with_module_provenance(): void
    {
        Tiger_Module_Registry::register('acme', [
            'label' => 'Acme Market', 'kind' => 'live-api', 'url' => 'https://acme.test/index.json', 'priority' => 7,
        ], 'acme-mod');
        try {
            $acme = null;
            foreach (Tiger_Module_Registry::sources() as $s) {
                if ($s->id === 'acme') { $acme = $s; break; }
            }
            $this->assertNotNull($acme, 'the registered source appears in sources()');
            $this->assertSame('module', $acme->origin);
            $this->assertSame('acme-mod', $acme->provider);
            $this->assertSame(7, $acme->priority);
            $this->assertTrue($acme->enabled);
        } finally {
            Tiger_Module_Registry::unregister('acme');   // in-memory registry persists across tests
        }
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

    // ---- multi-source registry ---------------------------------------------

    #[Test]
    public function ships_two_removable_defaults_marketplace_first_directory_second(): void
    {
        $sources = Tiger_Module_Registry::sources();
        $this->assertSame(
            [Tiger_Module_Registry::SOURCE_MARKETPLACE, Tiger_Module_Registry::SOURCE_DIRECTORY],
            array_map(static fn($s) => $s->id, $sources),
            'marketplace #0 (priority 0) is ordered before the directory (priority 10)'
        );
        [$mkt, $dir] = $sources;
        $this->assertFalse($mkt->isFetchable(), 'the live-API marketplace is inert until its URL is set');
        $this->assertTrue($dir->isFetchable(), 'the git directory is active by default');
        $this->assertTrue($mkt->default && $mkt->removable && $dir->default && $dir->removable, 'both are removable defaults');
        $this->assertSame(Tiger_Module_Registry::DEFAULT_INDEX, $dir->url, 'the directory carries the registry URL');
    }

    #[Test]
    public function the_registry_override_feeds_the_directory_source_url(): void
    {
        $this->setConfig(['tiger' => ['modules' => ['registry' => 'https://example.test/index.json']]]);
        $dir = Tiger_Module_Registry::sources()[1];
        $this->assertSame('https://example.test/index.json', $dir->url, 'back-compat: tiger.modules.registry still points the directory');
    }

    #[Test]
    public function a_config_source_is_added_and_ordered_by_priority(): void
    {
        $this->setConfig(['tiger' => ['modules' => ['sources' => [
            'extra' => ['kind' => 'git-index', 'url' => 'https://x/extra.json', 'priority' => 5],
        ]]]]);
        $this->assertSame(
            [Tiger_Module_Registry::SOURCE_MARKETPLACE, 'extra', Tiger_Module_Registry::SOURCE_DIRECTORY],
            array_map(static fn($s) => $s->id, Tiger_Module_Registry::sources()),
            'a priority-5 source slots between marketplace(0) and directory(10)'
        );
    }

    #[Test]
    public function a_config_override_can_disable_a_shipped_default(): void
    {
        $this->setConfig(['tiger' => ['modules' => ['sources' => [
            Tiger_Module_Registry::SOURCE_DIRECTORY => ['enabled' => '0'],
        ]]]]);
        $dir = Tiger_Module_Registry::sources()[1];
        $this->assertFalse($dir->isFetchable(), 'the directory default can be turned off from config');
    }

    #[Test]
    public function index_aggregates_dedupes_by_slug_and_enriches_from_lower_precedence(): void
    {
        // The directory (priority 10) carries widget + gadget.
        $this->primeIndex(['modules' => [
            ['module' => 'Widget', 'slug' => 'widget', 'vendor' => 'Directory', 'description' => 'from directory'],
            ['module' => 'Gadget', 'slug' => 'gadget', 'vendor' => 'Directory'],
        ]]);
        // A higher-precedence source (priority 5) re-lists widget (wins) minus a description, + a new plugin.
        $this->setConfig(['tiger' => ['modules' => ['sources' => [
            'extra' => ['kind' => 'git-index', 'url' => 'https://x/extra.json', 'priority' => 5, 'cache' => 'registry-extra.json'],
        ]]]]);
        $this->primeSourceCache('registry-extra.json', ['modules' => [
            ['module' => 'Widget', 'slug' => 'widget', 'vendor' => 'Extra'],   // no description → enriched from directory
            ['module' => 'Plugin', 'slug' => 'plugin-x', 'vendor' => 'Extra'],
        ]]);

        $rows   = Tiger_Module_Registry::search('');
        $bySlug = [];
        foreach ($rows as $r) { $bySlug[$r['slug']] = $r; }

        $this->assertSame(['widget', 'plugin-x', 'gadget'], array_column($rows, 'slug'), 'extra(5) walked before directory(10)');
        $this->assertSame('Extra', $bySlug['widget']['vendor'], 'the lower-priority source wins the slug');
        $this->assertSame('extra', $bySlug['widget']['source_id'], 'and stamps its home source');
        $this->assertSame('from directory', $bySlug['widget']['description'], 'the later source only FILLS the missing field');
        $this->assertSame('tiger-vendors', $bySlug['gadget']['source_id'], 'a directory-only module keeps its source');
    }

    #[Test]
    public function taxonomy_unions_types_and_categories_across_sources(): void
    {
        $this->primeIndex(['taxonomy' => ['types' => [['id' => 'app', 'label' => 'Apps']]], 'modules' => []]);
        $this->setConfig(['tiger' => ['modules' => ['sources' => [
            'extra' => ['kind' => 'git-index', 'url' => 'https://x/extra.json', 'priority' => 5, 'cache' => 'registry-extra.json'],
        ]]]]);
        $this->primeSourceCache('registry-extra.json', ['taxonomy' => [
            'types'      => [['id' => 'developer', 'label' => 'Developer']],
            'categories' => [['id' => 'commerce', 'label' => 'Commerce', 'types' => ['app']]],
        ], 'modules' => []]);

        $tax = Tiger_Module_Registry::taxonomy();
        $this->assertSame(['developer', 'app'], array_column($tax['types'], 'id'), 'union in source order (extra first)');
        $this->assertSame(['commerce'], array_column($tax['categories'], 'id'));
    }

    #[Test]
    public function a_down_source_is_skipped_and_the_others_still_resolve(): void
    {
        // The directory resolves from its primed cache; a config source with NO cache + a dead URL
        // is skipped — the aggregate still returns the directory's modules (never a hard-fail).
        $this->primeTwoModuleIndex();
        $this->setConfig(['tiger' => ['modules' => ['sources' => [
            'dead' => ['kind' => 'git-index', 'url' => 'https://127.0.0.1:0/never.json', 'priority' => 5, 'enabled' => '0'],
        ]]]]);   // enabled=0 so no real network is attempted in a unit test
        $this->assertCount(2, Tiger_Module_Registry::search(''), 'the surviving source still yields its catalog');
        $this->assertTrue(Tiger_Module_Registry::available());
    }

    #[Test]
    public function the_marketplace_source_activates_when_its_url_is_configured_and_wins_precedence(): void
    {
        $this->primeIndex(['modules' => [
            ['module' => 'Widget', 'slug' => 'widget', 'vendor' => 'Directory'],   // free listing
        ]]);
        $this->setConfig(['tiger' => ['modules' => ['marketplace' => 'https://store.test/api/index']]]);
        // marketplace #0 cache: an enriched widget (wins) + a paid-only listing.
        $this->primeSourceCache('registry-webtigers.json', ['modules' => [
            ['module' => 'Widget', 'slug' => 'widget', 'vendor' => 'Marketplace', 'rating' => 4.8],
            ['module' => 'Pro',    'slug' => 'pro-module', 'vendor' => 'Marketplace', 'pricing' => ['model' => 'licensed']],
        ]]);

        $mkt = Tiger_Module_Registry::sources()[0];
        $this->assertTrue($mkt->isFetchable(), 'a configured marketplace URL activates the live-API source');

        $rows = Tiger_Module_Registry::search('');
        $bySlug = [];
        foreach ($rows as $r) { $bySlug[$r['slug']] = $r; }
        $this->assertArrayHasKey('pro-module', $bySlug, 'the marketplace contributes its paid catalog');
        $this->assertSame('Marketplace', $bySlug['widget']['vendor'], 'marketplace #0 (priority 0) wins the shared slug');
        $this->assertSame('webtigers', $bySlug['widget']['source_id']);
    }
}
