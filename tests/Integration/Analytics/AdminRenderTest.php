<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Analytics;

use Analytics_AdminController;
use Matomo_AdminController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\ModuleControllerTestCase;
use Tiger_Analytics;
use Tiger_Analytics_Adapter_Matomo;
use Tiger_Crypto;
use Tiger_Model_Config;
use Tiger_View_Helper_PageAsset_Abstract;

/**
 * The analytics setup screen and dashboard, RENDERED — the real .phtml through the real view helpers
 * (t, i18n, pageScript, partial into the matomo module), not just the action's view vars. A green suite
 * that never renders the page proves nothing about the page.
 *
 * Covers the admin flow: step 1 "Choose your analytics provider" (a card per provider, the current one
 * marked), step 2 the chosen provider's panel (GA's form, or the matomo module's own form via the shared
 * partial, with the token write-only), and /matomo/admin redirecting to that one Matomo screen.
 */
#[CoversClass(Analytics_AdminController::class)]
#[CoversClass(Matomo_AdminController::class)]
final class AdminRenderTest extends ModuleControllerTestCase
{
    private const SECRET = 'ssssssssssssssssssssssssssssssss';

    protected function setUp(): void
    {
        parent::setUp();
        // asset() stats files under PUBLIC_PATH (undefined outside a web boot): render with cache-busting
        // off — a pass-through — rather than define a process-global constant other tests own.
        self::cacheBust(false);
        $this->tiger([
            'site'      => ['name' => 'Tiger', 'org_id' => 'org-test'],
            'i18n'      => ['locales' => 'en,es'],
            'crypto'    => ['key' => 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA='],
            'analytics' => ['enabled' => '0', 'exclude_signed_in' => '1', 'ga4' => ['measurement_id' => '']],
        ]);
        Tiger_Model_Config::resetCache();
        Tiger_View_Helper_PageAsset_Abstract::reset();
    }

    #[Test]
    public function with_no_provider_the_screen_asks_for_one_and_opens_no_panel(): void
    {
        $html = $this->renderIndex();

        $this->assertStringContainsString('analytics.provider.heading', $html, 'step 1 is rendered');
        $this->assertMatchesRegularExpression('~name="provider"[^>]*value="ga"~', $html);
        $this->assertMatchesRegularExpression('~name="provider"[^>]*value="matomo"~', $html);
        $this->assertDoesNotMatchRegularExpression('~name="provider"[^>]*checked~', $html, 'nothing chosen');
        $this->assertMatchesRegularExpression('~id="analytics-panel-none"(?![^>]*d-none)~', $html, 'the "choose above" hint shows');
        $this->assertMatchesRegularExpression('~id="analytics-panel-ga"[^>]*class="d-none"~', $html);
        $this->assertMatchesRegularExpression('~id="analytics-panel-matomo"[^>]*class="d-none"~', $html);
        $this->assertSame(1, substr_count($html, 'name="_csrf"') - substr_count($this->between($html, 'id="matomo-form"', '</form>'), 'name="_csrf"'),
            'one analytics token on the page (the provider choice rides it)');
        $this->assertNoInlineMarkup($html);
    }

    #[Test]
    public function choosing_matomo_marks_it_current_and_renders_its_form_with_a_write_only_token(): void
    {
        $this->matomo();
        Tiger_Analytics::saveProvider('matomo', 'org-test');
        $html = $this->renderIndex();

        $this->assertMatchesRegularExpression('~value="matomo" checked~', $html, 'the current choice is marked');
        $this->assertMatchesRegularExpression('~id="analytics-panel-matomo"(?![^>]*d-none)~', $html, 'its panel is open');
        $matomo = $this->between($html, 'id="matomo-form"', '</form>');
        $this->assertStringContainsString('name="matomo_url"', $matomo);
        $this->assertStringContainsString('value="https://stats.example.test/"', $matomo);
        $this->assertStringContainsString('name="matomo_site_id"', $matomo);
        $this->assertStringContainsString('name="matomo_token_auth"', $matomo);
        $this->assertStringContainsString('name="enabled"', $matomo, 'the tracking-tag toggle');
        $this->assertStringContainsString('id="matomo-test"', $matomo, 'Test connection');
        $this->assertStringContainsString('name="_csrf"', $matomo, "the matomo form carries its own module's token");
        $this->assertMatchesRegularExpression('~id="matomo-token-saved"(?![^>]*d-none)~', $matomo, '"token saved" shows');
        $this->assertStringNotContainsString(self::SECRET, $html, 'the token is never rendered');
        preg_match_all('~\sid="([^"]+)"~', $html, $ids);
        $this->assertSame([], array_keys(array_filter(array_count_values($ids[1]), static fn ($n) => $n > 1)),
            'every id on the two-form page is unique');
        $this->assertStringContainsString('analytics.admin-index.js', (string) $this->controller()->view->pageScripts);
        $this->assertNoInlineMarkup($html);
    }

    #[Test]
    public function an_existing_ga_install_opens_on_ga_as_detected(): void
    {
        $this->tiger([
            'site'      => ['name' => 'Tiger', 'org_id' => 'org-test'],
            'crypto'    => ['key' => 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA='],
            'analytics' => ['property_id' => '123456789', 'oauth' => ['mode' => 'broker']],
        ]);
        (new Tiger_Model_Config())->set('org', 'org-test', 'tiger.analytics.oauth.refresh_token_enc', Tiger_Crypto::encrypt('r'));
        $html = $this->renderIndex();

        $this->assertMatchesRegularExpression('~value="ga" checked~', $html);
        $this->assertStringContainsString('analytics-provider-detected', $html, 'auto-detected, not yet chosen');
        $this->assertMatchesRegularExpression('~id="analytics-panel-ga"(?![^>]*d-none)~', $html);
        $this->assertStringContainsString('name="ga4_measurement_id"', $html, 'the GA form is unchanged');
    }

    #[Test]
    public function the_provider_query_param_opens_that_panel_without_saving(): void
    {
        $html = $this->renderIndex(['provider' => 'matomo']);
        $this->assertMatchesRegularExpression('~id="analytics-panel-matomo"(?![^>]*d-none)~', $html);
        $this->assertSame('', Tiger_Analytics::chosen('org-test'), 'a deep link saves nothing');
    }

    #[Test]
    public function matomo_admin_redirects_to_the_one_matomo_setup_screen(): void
    {
        $this->loginAs('admin');
        $this->dispatchAction(Matomo_AdminController::class, 'index', [], 'GET');
        $this->assertStringContainsString('/analytics/admin?provider=matomo', $this->redirectLocation());
    }

    #[Test]
    public function the_dashboard_renders_for_a_provider_and_asks_for_one_without(): void
    {
        $this->loginAs('admin');
        $this->dispatchAction(Analytics_AdminController::class, 'dashboard', [], 'GET');
        $html = $this->render('admin/dashboard.phtml');
        $this->assertStringContainsString('analytics.dashboard.not_connected_title', $html);
        $this->assertStringNotContainsString('ga-chart', $html);

        $this->matomo();
        Tiger_View_Helper_PageAsset_Abstract::reset();
        $this->dispatchAction(Analytics_AdminController::class, 'dashboard', [], 'GET');
        $this->assertSame('matomo', $this->controller()->view->provider);
        $html = $this->render('admin/dashboard.phtml');
        $this->assertStringContainsString('id="ga-chart"', $html);
        $this->assertStringContainsString('analytics.card.top_referrers', $html);
        $this->assertStringContainsString('analytics.dashboard.js', (string) $this->controller()->view->pageScripts);
        $this->assertNoInlineMarkup($html);
    }

    protected function tearDown(): void
    {
        self::cacheBust(null);
        parent::tearDown();
    }

    // -- helpers -------------------------------------------------------------------------------------

    /** Set Tiger_View_Helper_Asset's memoized cache-bust flag (null = re-read config) and clear its memo. */
    public static function cacheBust(?bool $on): void
    {
        (new \ReflectionProperty(\Tiger_View_Helper_Asset::class, '_enabled'))->setValue(null, $on);
        (new \ReflectionProperty(\Tiger_View_Helper_Asset::class, '_memo'))->setValue(null, []);
    }

    private function renderIndex(array $params = []): string
    {
        $this->loginAs('admin');
        $this->dispatchAction(Analytics_AdminController::class, 'index', $params, 'GET');
        return $this->render('admin/index.phtml');
    }

    private function render(string $script): string
    {
        $view = $this->controller()->view;
        $view->addScriptPath(TIGER_CORE_PATH . '/modules/analytics/views/scripts');
        return $view->render($script);
    }

    private function matomo(): void
    {
        $cfg = new Tiger_Model_Config();
        $cfg->set('org', 'org-test', 'tiger.matomo.url', 'https://stats.example.test/');
        $cfg->set('org', 'org-test', 'tiger.matomo.site_id', '3');
        $cfg->set('org', 'org-test', Tiger_Analytics_Adapter_Matomo::CFG_TOKEN, Tiger_Crypto::encrypt(self::SECRET));
    }

    private function between(string $html, string $from, string $to): string
    {
        $a = strpos($html, $from);
        if ($a === false) { return ''; }
        $b = strpos($html, $to, $a);
        return substr($html, $a, $b === false ? null : $b - $a);
    }

    private function assertNoInlineMarkup(string $html): void
    {
        $this->assertDoesNotMatchRegularExpression('~<script\b|<style\b~i', $html, 'no inline script/style in the rendered page');
    }
}
