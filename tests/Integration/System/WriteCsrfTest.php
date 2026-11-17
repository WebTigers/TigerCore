<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\System;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use System_Form_DashboardLayout;
use System_Form_ModuleInstall;
use System_Form_ModuleState;
use System_Form_NavSort;
use System_Form_Settings;
use System_Form_Site;
use System_Form_UpdateApply;
use System_Service_Dashboard;
use System_Service_Modules;
use System_Service_Nav;
use System_Service_Sites;
use System_Service_Updates;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger\Tests\Support\StatelessApi;
use Tiger_Model_Config;
use Tiger_Model_Option;
use Tiger_Model_Org;
use Tiger_Model_SiteDomain;

/**
 * TIGER-528 — every System write takes the browser (session) path through a Tiger_Form, so it is refused
 * without the screen's `_csrf` and writes nothing, and accepted with it.
 *
 * Each test runs in COOKIE mode (`withCookieSession`: stateless off, an array-backed session) — the mode
 * a cross-site page would reach. A Bearer/MCP call is stateless and unaffected (the rest of the suite).
 * Where screens drive several endpoints from one rendered token, the test mints the token from ONE form
 * and spends it on another, pinning the shared salt.
 */
#[CoversClass(System_Service_Dashboard::class)]
#[CoversClass(System_Service_Nav::class)]
#[CoversClass(System_Service_Sites::class)]
#[CoversClass(System_Service_Updates::class)]
#[CoversClass(System_Service_Modules::class)]
final class WriteCsrfTest extends IntegrationTestCase
{
    use StatelessApi;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useStatelessApi();
    }

    protected function tearDown(): void
    {
        $this->restoreStatelessApi();
        parent::tearDown();
    }

    private function csrfRefused(object $res, string $label): void
    {
        $this->assertSame(0, (int) $res->result, $label . ' is refused without a token');
        $this->assertStringContainsString('core.api.error.csrf', json_encode($res->messages), $label);
    }

    #[Test]
    public function dashboard_layout_and_widget_prefs_need_the_dashboard_token(): void
    {
        $this->loginAs('admin');
        $this->withCookieSession(function () {
            $layout = '{"order":[],"collapsed":{}}';
            $this->csrfRefused((new System_Service_Dashboard(['action' => 'saveLayout', 'layout' => $layout]))->getResponse(), 'saveLayout');
            $this->csrfRefused((new System_Service_Dashboard(['action' => 'saveWidgetPrefs', 'hidden' => '[]']))->getResponse(), 'saveWidgetPrefs');
            $opt = new Tiger_Model_Option();
            $this->assertNull($opt->get(Tiger_Model_Option::SCOPE_USER, 'user-admin', System_Service_Dashboard::LAYOUT_KEY), 'nothing written');

            // The sidebar's token (System_Form_NavSort) is the same salt — either rendered token works.
            $tok = $this->csrfTokenFor(new System_Form_NavSort());
            $res = (new System_Service_Dashboard(['action' => 'saveLayout', 'layout' => $layout, '_csrf' => $tok]))->getResponse();
            $this->assertSame(1, (int) $res->result, json_encode($res->messages));
            $res = (new System_Service_Dashboard(['action' => 'saveWidgetPrefs', 'hidden' => '[]', '_csrf' => $tok]))->getResponse();
            $this->assertSame(1, (int) $res->result, json_encode($res->messages));
            $this->assertNotNull($opt->get(Tiger_Model_Option::SCOPE_USER, 'user-admin', System_Service_Dashboard::LAYOUT_KEY));
        });
    }

    #[Test]
    public function the_mail_test_needs_the_settings_screens_token(): void
    {
        // mailTest SENDS real mail with the stored credentials to the address it is given — without a token a
        // cross-site page could make an admin's browser send mail through the install (TIGER-528).
        $this->loginAs('admin');
        $this->withCookieSession(function () {
            $msg = ['action' => 'mailTest', 'to' => 'not-an-address', 'mail_provider' => 'sendmail'];
            $this->csrfRefused((new \System_Service_Settings($msg))->getResponse(), 'mailTest');

            // With the token the Settings screen renders (System_Form_Settings — the same salt), the call gets
            // past CSRF to the recipient check: a field error, so nothing is sent by this test.
            $res = (new \System_Service_Settings($msg + ['_csrf' => $this->csrfTokenFor(new System_Form_Settings())]))->getResponse();
            $this->assertSame(0, (int) $res->result);
            $this->assertStringNotContainsString('core.api.error.csrf', json_encode($res->messages), 'the screen token is accepted');
            $this->assertArrayHasKey('to', (array) $res->form, 'the bad address is a field error');
        });
    }

    #[Test]
    public function nav_sort_needs_the_sidebar_token(): void
    {
        $this->loginAs('admin');
        $this->withCookieSession(function () {
            $msg = ['action' => 'sort', 'group' => 'root', 'keys' => '["csrfa","csrfb"]'];
            $this->csrfRefused((new System_Service_Nav($msg))->getResponse(), 'sort');
            $cfg = new Tiger_Model_Config();
            $this->assertNull($cfg->get(Tiger_Model_Config::SCOPE_USER, 'user-admin', 'tiger.nav.root.csrfa.sort'), 'nothing written');

            $res = (new System_Service_Nav($msg + ['_csrf' => $this->csrfTokenFor(new System_Form_DashboardLayout())]))->getResponse();
            $this->assertSame(1, (int) $res->result, json_encode($res->messages));
            $this->assertSame('0', (string) $cfg->get(Tiger_Model_Config::SCOPE_USER, 'user-admin', 'tiger.nav.root.csrfa.sort'));
        });
    }

    #[Test]
    public function sites_delete_needs_the_sites_screens_one_token(): void
    {
        $this->loginAs('admin');
        $org = (string) (new Tiger_Model_Org())->insert(['name' => 'Csrf Co', 'slug' => 'csrf-co-' . substr(md5(uniqid()), 0, 6), 'status' => 'active']);
        (new System_Service_Sites(['action' => 'save', 'domain' => 'csrf.example', 'org_id' => $org]))->getResponse();   // stateless setup
        $row = (new Tiger_Model_SiteDomain())->findByHost('csrf.example');
        $this->assertNotNull($row);

        $this->withCookieSession(function () use ($row) {
            $this->csrfRefused((new System_Service_Sites(['action' => 'delete', 'site_domain_id' => $row->site_domain_id]))->getResponse(), 'delete');
            $this->assertNotNull((new Tiger_Model_SiteDomain())->orgForHost('csrf.example'), 'nothing written');

            // The screen renders System_Form_Site's token; the delete shares its salt.
            $res = (new System_Service_Sites(['action' => 'delete', 'site_domain_id' => $row->site_domain_id,
                '_csrf' => $this->csrfTokenFor(new System_Form_Site())]))->getResponse();
            $this->assertSame(1, (int) $res->result, json_encode($res->messages));
            $this->assertNull((new Tiger_Model_SiteDomain())->orgForHost('csrf.example'));
        });
    }

    #[Test]
    public function updates_apply_needs_the_updates_token(): void
    {
        $this->loginAs('superadmin');
        $this->withCookieSession(function () {
            $this->csrfRefused((new System_Service_Updates(['action' => 'apply', 'items' => 'tiger-core']))->getResponse(), 'apply');

            // With the token the request reaches the service's own checks (no selection → its own refusal;
            // asserting that path keeps the test offline — a real item would hit the network).
            $res = (new System_Service_Updates(['action' => 'apply', 'items' => '', '_csrf' => $this->csrfTokenFor(new System_Form_UpdateApply())]))->getResponse();
            $this->assertSame(0, (int) $res->result);
            $this->assertStringContainsString('none_selected', json_encode($res->messages));
            $this->assertStringNotContainsString('csrf', json_encode($res->messages));
        });
    }

    #[Test]
    public function every_module_manager_write_is_refused_without_the_token(): void
    {
        $this->loginAs('superadmin');
        $this->withCookieSession(function () {
            $writes = [
                'activate'        => ['slug' => 'blog'],
                'deactivate'      => ['slug' => 'blog'],
                'autoUpdate'      => ['slug' => 'blog', 'on' => '1'],
                'delete'          => ['slug' => 'nope', 'confirm' => 'x/nope'],
                'setPassNag'      => ['disabled' => '1'],
                'snoozePassNag'   => [],
                'connectSource'   => ['label' => 'Evil', 'url' => 'https://evil.example/index.json'],
                'updateSource'    => ['id' => 'tiger-vendors', 'enabled' => '0'],
                'removeSource'    => ['id' => 'tiger-vendors'],
                'activatePass'    => ['key' => '01890000-0000-7000-8000-000000000000'],
                'deactivatePass'  => [],
                'install'         => ['url' => 'https://github.com/evil/module'],
                'upload'          => [],
                'connectVendor'   => ['vendor' => 'evil/TigerVendor'],
                'pinVendor'       => ['vendor' => 'evil/TigerVendor'],
                'installLicensed' => ['vendor' => 'evil/TigerVendor', 'product' => 'x', 'key' => 'k'],
            ];
            foreach ($writes as $method => $params) {
                $this->csrfRefused((new System_Service_Modules(['action' => $method] + $params))->getResponse(), $method);
            }
            $this->assertNull((new Tiger_Model_Config())->get(Tiger_Model_Config::SCOPE_GLOBAL, '', System_Service_Modules::SOURCE_KEY_PREFIX . 'evil.url'),
                'the tokenless connectSource wrote nothing');
        });
    }

    #[Test]
    public function the_module_manager_shares_one_token_across_its_forms(): void
    {
        $this->loginAs('superadmin');
        $this->withCookieSession(function () {
            // Minted from the Modules screen's form, spent on the Add screen's TigerPASS + source writes.
            $tok = $this->csrfTokenFor(new System_Form_ModuleState());

            $res = (new System_Service_Modules(['action' => 'setPassNag', 'disabled' => '1', '_csrf' => $tok]))->getResponse();
            $this->assertSame(1, (int) $res->result, json_encode($res->messages));
            $this->assertTrue((bool) ($res->data['nag']['disabled'] ?? false), 'the switch was written');

            $res = (new System_Service_Modules(['action' => 'connectSource', 'label' => '', 'url' => 'x', '_csrf' => $tok]))->getResponse();
            $this->assertStringContainsString('system.source.err_label', json_encode($res->messages), 'past the token, the service keeps its own refusal + key');

            $res = (new System_Service_Modules(['action' => 'install', 'url' => 'not-a-repo', '_csrf' => $this->csrfTokenFor(new System_Form_ModuleInstall())]))->getResponse();
            $this->assertStringContainsString('system.error.repo_url', json_encode($res->messages));
        });
    }

    #[Test]
    public function the_settings_form_declares_every_field_the_save_reads(): void
    {
        $form = new System_Form_Settings();
        foreach (['mail_field', 'location_ip_provider', 'location_address_provider', 'location_cache_ttl', 'location_adapter',
                  'consent_mode', 'consent_message', 'consent_accept_label', 'consent_reject_label', 'consent_policy_url',
                  'consent_ccpa_notice', 'consent_honor_gpc', 'signup_settings', 'signup_disabled'] as $field) {
            $this->assertNotNull($form->getElement($field), $field . ' is declared on System_Form_Settings');
            $this->assertSame([], array_keys($form->getElement($field)->getFilters()), $field . ' carries no filter (may be an array)');
        }
    }
}
