<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Analytics;

use Analytics_Widget_Ga;
use Analytics_Widget_Traffic;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Analytics;
use Tiger_Analytics_Adapter_Matomo;
use Tiger_Crypto;
use Tiger_Dashboard;
use Tiger_Model_Config;
use Tiger_View_Helper_PageAsset_Abstract;
use Zend_Config;
use Zend_Layout;
use Zend_Registry;
use Zend_View;

/**
 * Analytics_Widget_Traffic — the provider-agnostic "Traffic" dashboard card (1.61). Server-rendered
 * states: no provider → "Choose an analytics provider"; chosen-but-not-set-up → "Finish setting up X";
 * ready (GA or Matomo) → the async chart shell, which registers its script with pageScript() and carries
 * the provider + its strings as data-* (no inline script/style). The configured-but-erroring state is
 * the shell's client side, fed by Analytics_Service_Reports::report's error envelope (ProviderChoiceTest).
 */
#[CoversClass(Analytics_Widget_Traffic::class)]
final class WidgetTest extends IntegrationTestCase
{
    private const KEY = 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=';

    protected function tearDown(): void
    {
        Zend_Layout::resetMvcInstance();
        Tiger_View_Helper_PageAsset_Abstract::reset();
        AdminRenderTest::cacheBust(null);
        parent::tearDown();
    }

    #[Test]
    public function with_no_provider_it_asks_the_admin_to_choose_one(): void
    {
        $this->config([]);
        $this->loginAs('admin');
        $html = (new Analytics_Widget_Traffic())->render();

        $this->assertStringContainsString('analytics.widget.choose', $html);
        $this->assertStringContainsString('href="/analytics/admin"', $html);
        $this->assertStringNotContainsString('<canvas', $html, 'no chart shell without a provider');
        $this->assertNoInlineMarkup($html);
    }

    #[Test]
    public function an_existing_ga_install_renders_the_chart_shell_for_ga(): void
    {
        $this->config(['analytics' => ['property_id' => '123456789', 'oauth' => ['mode' => 'broker', 'refresh_token_enc' => '__TOKEN__']]]);
        $this->loginAs('admin');
        $html = (new Analytics_Widget_Traffic())->render();

        $this->assertStringContainsString('<canvas', $html);
        $this->assertStringContainsString('data-provider="ga"', $html);
        $this->assertStringContainsString('/analytics/admin/dashboard', $html);
        $this->assertNoInlineMarkup($html);
    }

    #[Test]
    public function a_matomo_org_renders_the_chart_shell_for_matomo_and_registers_its_script(): void
    {
        $this->config([]);
        $this->matomo('org-test');
        AdminRenderTest::cacheBust(false);   // asset() pass-through (no docroot outside a web boot)
        $view = new Zend_View();
        $view->addHelperPath(TIGER_CORE_PATH . '/library/Tiger/View/Helper', 'Tiger_View_Helper');
        Zend_Layout::startMvc()->setView($view);
        $this->loginAs('admin');

        $html = (new Analytics_Widget_Traffic())->render();

        $this->assertStringContainsString('data-provider="matomo"', $html);
        $this->assertStringContainsString('data-analytics-traffic', $html);
        $this->assertStringContainsString('analytics.widget.js', (string) $view->pageScripts, 'behaviour is a registered asset');
        $this->assertNoInlineMarkup($html);
    }

    #[Test]
    public function a_chosen_provider_that_is_not_set_up_says_finish_setting_it_up(): void
    {
        $this->config([]);
        $this->loginAs('admin');
        Tiger_Analytics::saveProvider('matomo', 'org-test');
        $html = (new Analytics_Widget_Traffic())->render();

        $this->assertStringContainsString('analytics.widget.finish_setup', $html);
        $this->assertStringContainsString('/analytics/admin?provider=matomo', $html);
        $this->assertStringNotContainsString('<canvas', $html);
    }

    #[Test]
    public function the_widget_keeps_its_dashboard_id_and_the_old_class_still_renders(): void
    {
        $this->config([]);
        $this->loginAs('admin');
        $d = Tiger_Dashboard::get('analytics.traffic');
        if ($d !== null) {
            $this->assertSame('Analytics_Widget_Traffic', $d['widget'], 'same id, provider-agnostic class');
        }
        $this->assertInstanceOf(Analytics_Widget_Traffic::class, new Analytics_Widget_Ga());
        $this->assertStringContainsString('analytics.widget.choose', (new Analytics_Widget_Ga())->render());
    }

    // -- helpers -------------------------------------------------------------------------------------

    private function config(array $tiger): void
    {
        Zend_Registry::set('Zend_Config', new Zend_Config(['tiger' => ['crypto' => ['key' => self::KEY]]], true));
        $enc = Tiger_Crypto::encrypt('fake-refresh-token');
        array_walk_recursive($tiger, static function (&$v) use ($enc) { if ($v === '__TOKEN__') { $v = $enc; } });
        Zend_Registry::set('Zend_Config', new Zend_Config(['tiger' => array_replace_recursive([
            'crypto' => ['key' => self::KEY],
            'site'   => ['org_id' => 'org-test'],
        ], $tiger)], true));
        Tiger_Model_Config::resetCache();
    }

    private function matomo(string $org): void
    {
        $cfg = new Tiger_Model_Config();
        $cfg->set('org', $org, 'tiger.matomo.url', 'https://stats.example.test/');
        $cfg->set('org', $org, 'tiger.matomo.site_id', '3');
        $cfg->set('org', $org, Tiger_Analytics_Adapter_Matomo::CFG_TOKEN, Tiger_Crypto::encrypt('tok12345678'));
    }

    private function assertNoInlineMarkup(string $html): void
    {
        $this->assertDoesNotMatchRegularExpression('~<script\b|<style\b|\sstyle\s*=~i', $html, 'no inline script/style');
    }
}
