<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Cms;

use Cms_Form_Settings;
use Cms_Service_Paths;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Routing_Overrides;

/**
 * The home-page selector — choosing what "/" serves.
 *
 * The home page is ANY valid path, so the control ALWAYS accepts a free-typed value and a discovery
 * service (`Cms_Service_Paths`) supplies the *convenience* list the combobox searches: the built-in
 * landing, CMS pages, each installed theme's home (and, in ADVANCED mode, every theme page), and module
 * home prefixes. These pin the load-bearing properties: the stored value's shape is validated before it
 * can become the front door; module pages are generated from the routing registry (so they can't drift
 * from what's actually routable) with non-page endpoints filtered; discovery is admin-gated; the search
 * term narrows; and `advanced` is a strict superset of `basic`.
 */
#[CoversClass(Cms_Form_Settings::class)]
#[CoversClass(Cms_Service_Paths::class)]
final class HomePageSelectorTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Tiger_Routing_Overrides::clear();
    }

    protected function tearDown(): void
    {
        Tiger_Routing_Overrides::clear();
        parent::tearDown();
    }

    /** Call the discovery service as the current identity and return the flattened groups. */
    private function search(array $params = []): array
    {
        $res = (new Cms_Service_Paths(['action' => 'search'] + $params))->getResponse();
        if ((int) $res->result !== 1) { return ['__denied__' => true]; }
        $data = json_decode(json_encode($res->data), true) ?: [];
        return $data['groups'] ?? [];
    }

    /** Every option value across all groups, flat. */
    private function values(array $groups): array
    {
        $out = [];
        foreach ($groups as $g) {
            foreach (($g['options'] ?? []) as $o) { $out[] = $o['value']; }
        }
        return $out;
    }

    // ----- the stored value's shape ---------------------------------------------------------------

    #[Test]
    public function the_home_page_value_accepts_every_valid_shape_and_refuses_junk(): void
    {
        $el = (new Cms_Form_Settings())->getElement('home_page');

        $this->assertTrue($el->isValid(''), 'blank = the built-in landing');
        $this->assertTrue($el->isValid('/marketplace'), 'a module path');
        $this->assertTrue($el->isValid('/shop/index/cart'), 'a deeper route');
        $this->assertTrue($el->isValid('@theme:grey-mist'), 'a theme home');
        $this->assertTrue($el->isValid('@theme:grey-mist:about/team'), 'a nested theme page');
        $this->assertTrue($el->isValid('0192a1b2-c3d4-7e5f-8a9b-0c1d2e3f4a5b'), 'a CMS page_id (UUID)');

        $this->assertFalse($el->isValid('marketplace'), 'a path must be rooted');
        $this->assertFalse($el->isValid('https://evil.test/x'), 'no absolute URL — this forwards internally, never redirects offsite');
        $this->assertFalse($el->isValid('/x?y=1'), 'no query string — the home page takes no caller params');
        $this->assertFalse($el->isValid('@theme:'), 'a theme value needs a key');
    }

    // ----- discovery is admin-gated ---------------------------------------------------------------

    #[Test]
    public function discovery_is_denied_to_guests_and_plain_users_and_cleared_for_admin(): void
    {
        $this->login('anon', 'org-test', 'guest');
        $this->assertArrayHasKey('__denied__', $this->search(), 'guest denied');

        $this->loginAs('user');
        $this->assertArrayHasKey('__denied__', $this->search(), 'plain user denied');

        $this->loginAs('admin');
        $this->assertArrayNotHasKey('__denied__', $this->search(), 'admin clears');
    }

    // ----- module pages come from the routing registry --------------------------------------------

    #[Test]
    public function module_pages_come_from_the_routing_registry_and_non_pages_are_filtered(): void
    {
        $this->loginAs('admin');
        Tiger_Routing_Overrides::register('marketplace', ['pattern' => 'marketplace', 'target' => 'marketplace/index/index']);
        Tiger_Routing_Overrides::register('robots', ['pattern' => 'robots.txt', 'target' => 'seo/robots/txt']);

        $values = $this->values($this->search());

        $this->assertContains('/marketplace', $values, 'a registered module page is offered, stored as its path');
        $this->assertNotContains('/robots.txt', $values, 'a file-like (text/xml) endpoint is never offered as a home page');
    }

    // ----- the built-in landing is always there, search narrows -----------------------------------

    #[Test]
    public function the_builtin_landing_is_offered_and_the_query_narrows(): void
    {
        $this->loginAs('admin');
        Tiger_Routing_Overrides::register('marketplace', ['pattern' => 'marketplace', 'target' => 'marketplace/index/index']);

        $this->assertContains('', $this->values($this->search()), 'the built-in landing (value "") is always an option');

        $hits = $this->values($this->search(['q' => 'marketplace']));
        $this->assertContains('/marketplace', $hits, 'the query keeps a match');
        $this->assertNotContains('', $hits, 'and drops the built-in landing, which does not match "marketplace"');
    }

    // ----- advanced is a superset of basic (the litterbox) ----------------------------------------

    #[Test]
    public function advanced_is_a_superset_of_basic(): void
    {
        $this->loginAs('admin');

        $basic    = $this->values($this->search(['advanced' => '0']));
        $advanced = $this->values($this->search(['advanced' => '1']));

        $this->assertGreaterThanOrEqual(count($basic), count($advanced),
            'advanced never offers fewer paths than basic — it only adds every theme page');
        foreach ($basic as $v) {
            $this->assertContains($v, $advanced, 'every basic option is still present in advanced');
        }
    }

    // ----- labelFor renders each stored shape -----------------------------------------------------

    #[Test]
    public function label_for_renders_each_value_shape(): void
    {
        $this->assertNotSame('', Cms_Service_Paths::labelFor(''), 'the built-in landing has a human label');
        $this->assertSame('/marketplace', Cms_Service_Paths::labelFor('/marketplace'), 'a path shows verbatim');

        // A theme value → "<Name> — <Home|slug>". With the theme absent the key stands in for the name;
        // either way the home/slug half is present and it is not shown as a raw "@theme:" token.
        $home = Cms_Service_Paths::labelFor('@theme:grey-mist');
        $this->assertStringNotContainsString('@theme:', $home, 'a theme value is never shown as its raw token');
        $this->assertStringContainsString('—', $home, 'it reads "<theme> — <page>"');

        $sub = Cms_Service_Paths::labelFor('@theme:grey-mist:about');
        $this->assertStringContainsString('About', $sub, 'a nested theme page shows its slug as a friendly title');
    }
}
