<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Matomo;

use Matomo_Plugin_Tag;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use Tiger\Tests\Support\IntegrationTestCase;
use Zend_Config;
use Zend_Controller_Request_Simple;
use Zend_Registry;
use Zend_View;

/**
 * Matomo_Plugin_Tag — emits the self-hosted Matomo `_paq` snippet into the `tigerTracking` head
 * placeholder on public pages, only when Matomo is enabled with a valid URL + positive site id AND
 * consent allows AND the visitor isn't excluded signed-in staff. Coverage: `url()`/`siteId()`/
 * `isConfigured()` gating; `preDispatch()` emission (happy path + staff exclusion); the affiliate
 * custom-dimension read from the `/a/<name>` contract cookie (present / absent / invalid / custom index);
 * and the emit-once latch.
 *
 * The plugin has a process-wide emit-once latch (`$_done`); each test resets it via reflection.
 */
#[CoversClass(Matomo_Plugin_Tag::class)]
final class TagPluginTest extends IntegrationTestCase
{
    private Zend_View $view;

    protected function setUp(): void
    {
        parent::setUp();
        $this->view = new Zend_View();
        Zend_Registry::set('Zend_View', $this->view);
        // Placeholder containers are a process-wide singleton — clear the one this plugin writes.
        $this->view->placeholder('tigerTracking')->exchangeArray([]);
        unset($_COOKIE[Matomo_Plugin_Tag::AFFILIATE_COOKIE]);
        $this->resetLatch();
    }

    protected function tearDown(): void
    {
        unset($_COOKIE[Matomo_Plugin_Tag::AFFILIATE_COOKIE]);
        $this->resetLatch();
        parent::tearDown();
    }

    /** Reset the plugin's emit-once static so each test starts clean. */
    private function resetLatch(): void
    {
        $p = new ReflectionProperty(Matomo_Plugin_Tag::class, '_done');
        $p->setValue(null, false);
    }

    private function config(array $matomo): void
    {
        Zend_Registry::set('Zend_Config', new Zend_Config(['tiger' => ['matomo' => $matomo]], true));
    }

    private function tracking(): string
    {
        return (string) $this->view->placeholder('tigerTracking');
    }

    // ----- url / siteId / isConfigured ----------------------------------------------------------

    #[Test]
    public function is_not_configured_when_disabled(): void
    {
        $this->config(['enabled' => '0', 'url' => 'https://a.example.com/', 'site_id' => '1']);
        $this->assertFalse(Matomo_Plugin_Tag::isConfigured(), 'disabled → not configured even with url + id');
    }

    #[Test]
    public function url_is_empty_for_an_invalid_url(): void
    {
        $this->config(['enabled' => '1', 'url' => 'not a url', 'site_id' => '1']);
        $this->assertSame('', Matomo_Plugin_Tag::url(), 'a non-http(s) url is rejected');
        $this->assertFalse(Matomo_Plugin_Tag::isConfigured());
    }

    #[Test]
    public function is_not_configured_without_a_positive_site_id(): void
    {
        $this->config(['enabled' => '1', 'url' => 'https://a.example.com/', 'site_id' => '0']);
        $this->assertSame(0, Matomo_Plugin_Tag::siteId());
        $this->assertFalse(Matomo_Plugin_Tag::isConfigured());
    }

    #[Test]
    public function url_is_normalized_with_a_trailing_slash(): void
    {
        $this->config(['enabled' => '1', 'url' => 'https://a.example.com', 'site_id' => '3']);
        $this->assertSame('https://a.example.com/', Matomo_Plugin_Tag::url(), 'no trailing slash → one is added');
        $this->assertSame(3, Matomo_Plugin_Tag::siteId());
        $this->assertTrue(Matomo_Plugin_Tag::isConfigured());
    }

    // ----- preDispatch emission -----------------------------------------------------------------

    #[Test]
    public function predispatch_appends_the_snippet_when_configured_for_a_guest(): void
    {
        $this->config(['enabled' => '1', 'url' => 'https://mat.example.com/', 'site_id' => '7']);

        (new Matomo_Plugin_Tag())->preDispatch(new Zend_Controller_Request_Simple());

        $out = $this->tracking();
        $this->assertStringContainsString("u=\"https:\\/\\/mat.example.com\\/\"", $out, 'the tracker base url is emitted');
        $this->assertStringContainsString("u+'matomo.js'", $out, 'the matomo.js loader is emitted');
        $this->assertStringContainsString("_paq.push(['setSiteId', '7'])", $out, 'the site id is emitted');
        $this->assertStringContainsString("_paq.push(['trackPageView'])", $out);
    }

    #[Test]
    public function predispatch_emits_nothing_when_unconfigured(): void
    {
        $this->config(['enabled' => '0', 'url' => 'https://mat.example.com/', 'site_id' => '7']);
        (new Matomo_Plugin_Tag())->preDispatch(new Zend_Controller_Request_Simple());
        $this->assertSame('', $this->tracking(), 'disabled → nothing in the placeholder');
    }

    #[Test]
    public function predispatch_skips_signed_in_staff_when_exclusion_is_on(): void
    {
        $this->config(['enabled' => '1', 'exclude_signed_in' => '1', 'url' => 'https://mat.example.com/', 'site_id' => '7']);
        $this->loginAs('admin');

        (new Matomo_Plugin_Tag())->preDispatch(new Zend_Controller_Request_Simple());
        $this->assertSame('', $this->tracking(), 'staff traffic is not tagged');
    }

    #[Test]
    public function predispatch_tags_signed_in_staff_when_exclusion_is_off(): void
    {
        $this->config(['enabled' => '1', 'exclude_signed_in' => '0', 'url' => 'https://mat.example.com/', 'site_id' => '7']);
        $this->loginAs('admin');

        (new Matomo_Plugin_Tag())->preDispatch(new Zend_Controller_Request_Simple());
        $this->assertStringContainsString("setSiteId', '7'", $this->tracking(), 'exclusion off → staff tagged');
    }

    // ----- affiliate custom dimension (the /a/<name> contract cookie) ----------------------------

    #[Test]
    public function affiliate_dimension_is_set_from_the_cookie_before_pageview(): void
    {
        $this->config(['enabled' => '1', 'url' => 'https://mat.example.com/', 'site_id' => '7']);
        $_COOKIE[Matomo_Plugin_Tag::AFFILIATE_COOKIE] = 'jane';

        (new Matomo_Plugin_Tag())->preDispatch(new Zend_Controller_Request_Simple());

        $out = $this->tracking();
        $this->assertStringContainsString("_paq.push(['setCustomDimension', 1, \"jane\"])", $out, 'default dimension index 1 holds the affiliate name');
        $this->assertLessThan(
            strpos($out, "trackPageView"),
            strpos($out, 'setCustomDimension'),
            'the dimension is set BEFORE the pageview'
        );
    }

    #[Test]
    public function affiliate_dimension_honors_a_configured_index(): void
    {
        $this->config(['enabled' => '1', 'url' => 'https://mat.example.com/', 'site_id' => '7', 'affiliate' => ['dimension_id' => '4']]);
        $_COOKIE[Matomo_Plugin_Tag::AFFILIATE_COOKIE] = 'bob';

        (new Matomo_Plugin_Tag())->preDispatch(new Zend_Controller_Request_Simple());
        $this->assertStringContainsString("_paq.push(['setCustomDimension', 4, \"bob\"])", $this->tracking());
    }

    #[Test]
    public function no_affiliate_dimension_without_the_cookie(): void
    {
        $this->config(['enabled' => '1', 'url' => 'https://mat.example.com/', 'site_id' => '7']);
        (new Matomo_Plugin_Tag())->preDispatch(new Zend_Controller_Request_Simple());
        $this->assertStringNotContainsString('setCustomDimension', $this->tracking(), 'no cookie → no dimension');
    }

    #[Test]
    public function an_invalid_affiliate_name_is_ignored(): void
    {
        $this->config(['enabled' => '1', 'url' => 'https://mat.example.com/', 'site_id' => '7']);
        $_COOKIE[Matomo_Plugin_Tag::AFFILIATE_COOKIE] = 'not valid"; evil()';

        (new Matomo_Plugin_Tag())->preDispatch(new Zend_Controller_Request_Simple());
        $this->assertStringNotContainsString('setCustomDimension', $this->tracking(), 'an unsafe cookie value is refused');
    }

    #[Test]
    public function the_emit_once_latch_prevents_a_second_append(): void
    {
        $this->config(['enabled' => '1', 'url' => 'https://mat.example.com/', 'site_id' => '7']);
        $plugin = new Matomo_Plugin_Tag();
        $plugin->preDispatch(new Zend_Controller_Request_Simple());
        $plugin->preDispatch(new Zend_Controller_Request_Simple());
        $this->assertSame(1, substr_count($this->tracking(), "u+'matomo.js'"), 'the snippet is appended exactly once');
    }
}
