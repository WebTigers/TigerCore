<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Seo;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Seo_Service_Social;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Model_Config;
use Zend_Config;
use Zend_Registry;

/**
 * Seo_Service_Social — the /api service behind the Social Cards screen.
 *
 * It authors `tiger.seo.page.<key>.{title,description,image}` in the `config` tier — the same keys
 * `Seo_Service_Head::pageDefaults()` reads at render time — so a public VIEW page (a shipped `.phtml`
 * with no CMS row) gets a real share card with no deploy.
 *
 * The contract these tests pin, in order of how easy it is to get wrong:
 *   1. **Blank REMOVES the override.** Storing '' would win the cascade and mask both the `.ini` base
 *      and the site-wide fallback, leaving a blank card. `save()` must `forget()` instead.
 *   2. **page_key is an allow-list, not a request value** — it becomes a config-key segment, so only
 *      a DISCOVERED page may be authored.
 *   3. A media id beats the URL escape hatch (it resolves to true pixel dimensions).
 *   4. Deny-by-default: the whole surface is admin+, which is also what bounds the AI agent's reach.
 */
#[CoversClass(Seo_Service_Social::class)]
final class SocialServiceTest extends IntegrationTestCase
{
    private const UUID = '0191aabb-ccdd-7eff-8899-aabbccddeeff';

    private bool $hadConfig = false;
    private $priorConfig = null;

    protected function setUp(): void
    {
        parent::setUp();
        Zend_Registry::set('tiger.auth.stateless', true);   // no CSRF token in a service-level test

        $reg = Zend_Registry::getInstance();
        $this->hadConfig   = $reg->offsetExists('Zend_Config');
        $this->priorConfig = $this->hadConfig ? Zend_Registry::get('Zend_Config') : null;
        $this->config([
            'site' => ['name' => 'Tiger', 'description' => 'The AI-native SaaS platform.'],
            'seo'  => ['og_image' => 'https://cdn.example.test/og-default.png'],
        ]);
    }

    protected function tearDown(): void
    {
        $reg = Zend_Registry::getInstance();
        if ($reg->offsetExists('tiger.auth.stateless')) { $reg->offsetUnset('tiger.auth.stateless'); }
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

    private function call(string $method, array $params = []): object
    {
        return (new Seo_Service_Social(['action' => $method] + $params))->getResponse();
    }

    private function cfg(string $key): ?string
    {
        return (new Tiger_Model_Config())->get(Tiger_Model_Config::SCOPE_GLOBAL, '', $key);
    }

    /** The `pages` row for one key, or null. */
    private function page(object $res, string $key): ?array
    {
        foreach ((array) ($res->data['pages'] ?? []) as $row) {
            if ($row['key'] === $key) { return $row; }
        }
        return null;
    }

    // ----- ACL: the boundary that also bounds the AI agent ---------------------------------------

    #[Test]
    public function a_guest_can_neither_read_nor_write(): void
    {
        $this->login('anon', 'org-test', 'guest');

        foreach (['pages' => [], 'save' => ['page_key' => 'agency', 'title' => 'x']] as $method => $params) {
            $res = $this->call($method, $params);
            $this->assertSame(0, (int) $res->result, $method . ' is denied');
            $this->assertStringContainsString('not_allowed', json_encode($res->messages));
        }
    }

    #[Test]
    public function a_plain_user_is_denied(): void
    {
        $this->loginAs('user');
        $res = $this->call('save', ['page_key' => 'agency', 'title' => 'x']);
        $this->assertSame(0, (int) $res->result);
        $this->assertStringContainsString('not_allowed', json_encode($res->messages));
    }

    // ----- pages() ------------------------------------------------------------------------------

    #[Test]
    public function pages_lists_the_discovered_view_pages_with_the_site_fallbacks(): void
    {
        $this->loginAs('admin');
        $res = $this->call('pages');

        $this->assertSame(1, (int) $res->result);
        $agency = $this->page($res, 'agency');
        $this->assertNotNull($agency, 'the shipped /agency page is listed');
        $this->assertSame('/agency', $agency['url']);
        $this->assertFalse($agency['has_title'], 'nothing authored yet');
        $this->assertSame('', $agency['title']);

        $defaults = $res->data['defaults'];
        $this->assertSame('Tiger', $defaults['site_name']);
        $this->assertSame('The AI-native SaaS platform.', $defaults['site_description']);
        $this->assertSame('https://cdn.example.test/og-default.png', $defaults['og_image']);
        // An absolute URL is its own preview — no media lookup needed.
        $this->assertSame($defaults['og_image'], $defaults['og_image_url']);
    }

    #[Test]
    public function pages_reports_the_values_the_live_cascade_resolves(): void
    {
        $this->config([
            'site' => ['name' => 'Tiger'],
            'seo'  => ['page' => ['agency' => [
                'title'       => 'For agencies',
                'description' => 'Run every client site from one install.',
                'image'       => 'https://cdn.example.test/og-agency.png',
            ]]],
        ]);
        $this->loginAs('admin');

        $agency = $this->page($this->call('pages'), 'agency');

        $this->assertSame('For agencies', $agency['title']);
        $this->assertSame('Run every client site from one install.', $agency['description']);
        $this->assertSame('https://cdn.example.test/og-agency.png', $agency['image']);
        $this->assertTrue($agency['has_title']);
        $this->assertTrue($agency['has_description']);
        $this->assertTrue($agency['has_image']);
    }

    // ----- save() -------------------------------------------------------------------------------

    #[Test]
    public function save_writes_one_config_row_per_field(): void
    {
        $this->loginAs('admin');
        $res = $this->call('save', [
            'page_key'    => 'agency',
            'title'       => '  For agencies  ',
            'description' => 'One install, every client site.',
            'image_url'   => 'https://cdn.example.test/og-agency.png',
        ]);

        $this->assertSame(1, (int) $res->result);
        $this->assertSame('For agencies', $this->cfg('tiger.seo.page.agency.title'), 'trimmed on save');
        $this->assertSame('One install, every client site.', $this->cfg('tiger.seo.page.agency.description'));
        $this->assertSame('https://cdn.example.test/og-agency.png', $this->cfg('tiger.seo.page.agency.image'));
    }

    #[Test]
    public function a_media_id_wins_over_the_url_escape_hatch(): void
    {
        $this->loginAs('admin');
        $res = $this->call('save', [
            'page_key'       => 'agency',
            'image_media_id' => self::UUID,
            'image_url'      => 'https://cdn.example.test/ignored.png',
        ]);

        $this->assertSame(1, (int) $res->result);
        $this->assertSame(self::UUID, $this->cfg('tiger.seo.page.agency.image'));
    }

    #[Test]
    public function a_blank_media_id_falls_through_to_the_url(): void
    {
        $this->loginAs('admin');
        $this->call('save', [
            'page_key'       => 'agency',
            'image_media_id' => '',
            'image_url'      => 'https://cdn.example.test/og-agency.png',
        ]);

        $this->assertSame('https://cdn.example.test/og-agency.png', $this->cfg('tiger.seo.page.agency.image'));
    }

    #[Test]
    public function a_malformed_media_id_is_a_real_error_not_a_silent_ignore(): void
    {
        // The media id is a declared form element precisely so a non-browser caller (the AI agent,
        // MCP) is told its id was wrong instead of quietly getting the URL — or nothing — instead.
        $this->loginAs('admin');
        $res = $this->call('save', ['page_key' => 'agency', 'image_media_id' => 'not-a-uuid']);

        $this->assertSame(0, (int) $res->result);
        $this->assertArrayHasKey('image_media_id', (array) $res->form);
        $this->assertNull($this->cfg('tiger.seo.page.agency.image'));
    }

    // ----- the load-bearing one: blank REMOVES the override --------------------------------------

    #[Test]
    public function blanking_a_field_removes_the_override_instead_of_storing_an_empty_string(): void
    {
        $this->loginAs('admin');
        $this->call('save', [
            'page_key'    => 'agency',
            'title'       => 'For agencies',
            'description' => 'One install, every client site.',
            'image_url'   => 'https://cdn.example.test/og-agency.png',
        ]);
        $this->assertSame('For agencies', $this->cfg('tiger.seo.page.agency.title'));

        // Now clear the title only.
        $res = $this->call('save', [
            'page_key'    => 'agency',
            'title'       => '',
            'description' => 'One install, every client site.',
            'image_url'   => 'https://cdn.example.test/og-agency.png',
        ]);

        $this->assertSame(1, (int) $res->result);
        // Gone from the cascade entirely — NOT stored as '' (which would mask the site fallback).
        $this->assertNull($this->cfg('tiger.seo.page.agency.title'), 'the override is removed');
        $this->assertSame('One install, every client site.', $this->cfg('tiger.seo.page.agency.description'), 'the others stand');
    }

    #[Test]
    public function an_all_blank_save_clears_every_override_and_re_setting_revives_it(): void
    {
        $this->loginAs('admin');
        $this->call('save', ['page_key' => 'vibe', 'title' => 'T', 'description' => 'D', 'image_url' => 'https://x.test/i.png']);

        $this->call('save', ['page_key' => 'vibe']);   // nothing posted = clear all three
        foreach (['title', 'description', 'image'] as $field) {
            $this->assertNull($this->cfg('tiger.seo.page.vibe.' . $field), $field . ' override removed');
        }

        // forget() soft-deletes; a later set() must REVIVE the row, not collide with the unique index.
        $res = $this->call('save', ['page_key' => 'vibe', 'title' => 'Back again']);
        $this->assertSame(1, (int) $res->result);
        $this->assertSame('Back again', $this->cfg('tiger.seo.page.vibe.title'));
    }

    #[Test]
    public function saving_nothing_for_a_never_authored_page_is_a_harmless_no_op(): void
    {
        $this->loginAs('admin');
        $before = (int) $this->db->fetchOne('SELECT COUNT(*) FROM config');

        $res = $this->call('save', ['page_key' => 'features']);

        $this->assertSame(1, (int) $res->result);
        $this->assertSame($before, (int) $this->db->fetchOne('SELECT COUNT(*) FROM config'), 'no empty rows written');
    }

    // ----- rejects ------------------------------------------------------------------------------

    #[Test]
    public function an_unknown_page_key_is_refused_and_writes_nothing(): void
    {
        $this->loginAs('admin');
        $before = (int) $this->db->fetchOne('SELECT COUNT(*) FROM config');

        $res = $this->call('save', ['page_key' => 'not-a-real-page', 'title' => 'Nope']);

        $this->assertSame(0, (int) $res->result);
        $this->assertStringContainsString('unknown_page', json_encode($res->messages));
        $this->assertSame($before, (int) $this->db->fetchOne('SELECT COUNT(*) FROM config'));
        $this->assertNull($this->cfg('tiger.seo.page.not-a-real-page.title'));
    }

    #[Test]
    public function a_missing_page_key_returns_form_errors(): void
    {
        $this->loginAs('admin');
        $res = $this->call('save', ['title' => 'Orphan']);

        $this->assertSame(0, (int) $res->result);
        $this->assertNotNull($res->form);
        $this->assertArrayHasKey('page_key', $res->form);
    }

    #[Test]
    public function a_page_key_that_could_never_be_a_config_segment_is_rejected_by_the_form(): void
    {
        $this->loginAs('admin');
        $res = $this->call('save', ['page_key' => '../../etc/passwd', 'title' => 'x']);

        $this->assertSame(0, (int) $res->result);
        $this->assertNotNull($res->form);
        $this->assertArrayHasKey('page_key', $res->form);
    }

    #[Test]
    public function a_malformed_image_url_is_rejected(): void
    {
        $this->loginAs('admin');
        $res = $this->call('save', ['page_key' => 'agency', 'image_url' => 'ftp://nope']);

        $this->assertSame(0, (int) $res->result);
        $this->assertNotNull($res->form);
        $this->assertArrayHasKey('image_url', $res->form);
        $this->assertNull($this->cfg('tiger.seo.page.agency.image'));
    }

    #[Test]
    public function an_over_long_description_is_rejected(): void
    {
        $this->loginAs('admin');
        $res = $this->call('save', ['page_key' => 'agency', 'description' => str_repeat('x', 301)]);

        $this->assertSame(0, (int) $res->result);
        $this->assertArrayHasKey('description', (array) $res->form);
    }
}
