<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Seo;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Seo_Service_Pages;
use Tiger\Tests\Support\IntegrationTestCase;

/**
 * Seo_Service_Pages — discovery of the public VIEW pages whose social card is authorable.
 *
 * The filesystem IS the registry here: a shipped `.phtml` under the default namespace's `index`
 * view-script dir is a page, its basename is its page key, and that key is what
 * `Seo_Service_Head::pageKey()` resolves a live request to. These tests pin the three rules that
 * make the two halves address the same config node: locale variants collapse (one key per page, not
 * one per language), the key shape matches `pageKey()`'s `[a-z0-9-]`, and `index` is the site root.
 *
 * `exists()` is the write allow-list — the reason a caller can never author
 * `tiger.seo.page.<anything>.*` for a page that isn't real — so its deny path is covered too.
 */
#[CoversClass(Seo_Service_Pages::class)]
final class PagesDiscoveryTest extends IntegrationTestCase
{
    private string $tmp = '';

    protected function tearDown(): void
    {
        if ($this->tmp !== '' && is_dir($this->tmp)) {
            foreach (glob($this->tmp . '/*/*') ?: [] as $f) { @unlink($f); }
            foreach (glob($this->tmp . '/*') ?: [] as $d) { @rmdir($d); }
            @rmdir($this->tmp);
        }
        parent::tearDown();
    }

    /** Build a throwaway ['core' => …, 'app' => …] pair of view-script dirs holding $files. */
    private function fixture(array $core, array $app = []): array
    {
        $this->tmp = sys_get_temp_dir() . '/tiger-seo-pages-' . bin2hex(random_bytes(4));
        $dirs = [];
        foreach (['core' => $core, 'app' => $app] as $source => $files) {
            $dir = $this->tmp . '/' . $source;
            @mkdir($dir, 0777, true);
            foreach ($files as $name => $body) { file_put_contents($dir . '/' . $name, $body); }
            $dirs[$source] = $dir;
        }
        return $dirs;
    }

    /** The keys discovered from a dir set, in order. */
    private function keys(array $dirs): array
    {
        return array_column(Seo_Service_Pages::discover($dirs), 'key');
    }

    // ----- the shipped pages --------------------------------------------------------------------

    #[Test]
    public function it_finds_the_shipped_marketing_pages(): void
    {
        $keys = array_column(Seo_Service_Pages::discover(), 'key');

        $this->assertContains('agency', $keys);
        $this->assertContains('vibe', $keys);
        $this->assertContains('get-tiger', $keys);
        $this->assertContains('how-it-works', $keys);
        $this->assertContains('index', $keys);
    }

    #[Test]
    public function a_locale_variant_never_becomes_a_page_of_its_own(): void
    {
        // agency.es.phtml / index.tlh.phtml are the SAME pages in another language — one key each.
        $keys = array_column(Seo_Service_Pages::discover(), 'key');

        $this->assertSame(array_unique($keys), $keys, 'keys are unique');
        foreach ($keys as $key) {
            $this->assertMatchesRegularExpression('/^[a-z0-9-]+$/', $key);
            $this->assertStringNotContainsString('-es', $key);
            $this->assertStringNotContainsString('-tlh', $key);
        }
    }

    #[Test]
    public function every_discovered_page_carries_a_url_and_a_readable_file(): void
    {
        foreach (Seo_Service_Pages::discover() as $page) {
            $this->assertSame(Seo_Service_Pages::url($page['key']), $page['url']);
            $this->assertFileExists($page['file']);
            $this->assertContains($page['source'], ['core', 'app']);
        }
    }

    // ----- the discovery rules ------------------------------------------------------------------

    #[Test]
    public function locale_variants_and_partials_are_skipped(): void
    {
        $dirs = $this->fixture([
            'agency.phtml'     => 'x',
            'agency.es.phtml'  => 'x',
            'agency.hi.phtml'  => 'x',
            '_sidebar.phtml'   => 'x',
            'notes.txt'        => 'x',
        ]);

        $this->assertSame(['agency'], $this->keys($dirs));
    }

    #[Test]
    public function index_maps_to_the_site_root_and_everything_else_to_its_key(): void
    {
        $this->assertSame('/', Seo_Service_Pages::url('index'));
        $this->assertSame('/', Seo_Service_Pages::url(''));
        $this->assertSame('/agency', Seo_Service_Pages::url('agency'));
        $this->assertSame('/get-tiger', Seo_Service_Pages::url('get-tiger'));
    }

    #[Test]
    public function an_app_page_shadows_a_shipped_one_of_the_same_name(): void
    {
        // Mirrors the view-script path cascade that resolves the page itself: app last wins.
        $dirs  = $this->fixture(['agency.phtml' => 'core', 'vibe.phtml' => 'core'], ['agency.phtml' => 'app']);
        $pages = Seo_Service_Pages::discover($dirs);

        $this->assertSame(['agency', 'vibe'], array_column($pages, 'key'), 'sorted, deduped');
        $this->assertSame('app', $pages[0]['source'], 'the app copy of agency wins');
        $this->assertSame('core', $pages[1]['source']);
    }

    #[Test]
    public function a_missing_directory_is_simply_skipped(): void
    {
        $this->assertSame([], Seo_Service_Pages::discover(['core' => '/no/such/dir', 'app' => '']));
    }

    #[Test]
    public function a_key_is_normalised_to_the_same_shape_pagekey_produces(): void
    {
        $this->assertSame('get-tiger', Seo_Service_Pages::key('Get-Tiger'));
        $this->assertSame('how-it-works', Seo_Service_Pages::key('How It Works'));
        $this->assertSame('agency', Seo_Service_Pages::key('  --agency--  '));
        $this->assertSame('', Seo_Service_Pages::key('///'));
    }

    // ----- the write allow-list -----------------------------------------------------------------

    #[Test]
    public function exists_accepts_a_real_page_and_refuses_anything_else(): void
    {
        $this->assertTrue(Seo_Service_Pages::exists('agency'));
        $this->assertTrue(Seo_Service_Pages::exists('AGENCY'), 'normalised before the check');
        $this->assertFalse(Seo_Service_Pages::exists('not-a-real-page'));
        $this->assertFalse(Seo_Service_Pages::exists(''));
    }
}
