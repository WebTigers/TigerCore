<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration;

use Analytics_Plugin_Tag;
use Analytics_Service_Analytics;
use Cms_Service_Settings;
use Comment_Service_Settings;
use Email_Service_Template;
use IndexController;
use Identity_Plugin_Favicon;
use Identity_Service_Identity;
use Matomo_Plugin_Tag;
use Matomo_Service_Matomo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;
use Seo_Service_Head;
use Seo_Service_Social;
use Signup_Service_Signup;
use System_Service_Settings;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Comment_Spam;
use Tiger_Consent;
use Tiger_Google_Analytics;
use Tiger_Mail;
use Tiger_Model_Config;
use Tiger_Model_EmailTemplate;
use Tiger_Model_Option;
use Tiger_Model_Org;
use Tiger_Recaptcha;
use Tiger_Uuid;
use Zend_Config;
use Zend_Registry;

/**
 * Per-site settings are ORG-scoped and never bleed into another org (TIGER-511).
 *
 * Every install has an org. A settings screen writes at the caller's org (never the global tier) and
 * shows that same tier; a public path reads the SITE org (the host's tenant, else the install org) —
 * so a visitor signed in to org B still sees site A's value; an org with no value of its own falls back
 * to the install-wide (global) value, which is how legacy global rows keep resolving.
 *
 * Each setting is proved the same way (prove()): org A's save never changes org B's effective value;
 * nothing is written global; a signed-out request on a single-site install reads the install org's
 * value; a signed-in member of B on site A sees A's; a global-only legacy value still resolves.
 */
#[CoversClass(Tiger_Model_Config::class)]
#[CoversClass(Tiger_Model_Option::class)]
#[CoversClass(Tiger_Recaptcha::class)]
#[CoversClass(Tiger_Consent::class)]
#[CoversClass(Tiger_Mail::class)]
#[CoversClass(Tiger_Google_Analytics::class)]
#[CoversClass(Tiger_Comment_Spam::class)]
final class OrgScopedSettingsTest extends IntegrationTestCase
{
    /** The install's founding org (oldest) — "site A" on a single-site install. */
    private string $a = '';
    /** A second tenant org. */
    private string $b = '';

    private bool $hadConfig = false;
    private $priorConfig = null;

    protected function setUp(): void
    {
        parent::setUp();
        Zend_Registry::set('tiger.auth.stateless', true);   // no CSRF token in a service-level test

        // An empty ini tier, so every value read below comes from the rows this test writes.
        $reg = Zend_Registry::getInstance();
        $this->hadConfig   = $reg->offsetExists('Zend_Config');
        $this->priorConfig = $this->hadConfig ? Zend_Registry::get('Zend_Config') : null;
        Zend_Registry::set('Zend_Config', new Zend_Config(['tiger' => []]));
        Tiger_Model_Config::setIniBase(null);

        $this->a = $this->seedOrg('Site A', '1970-01-01 00:00:01');
        $this->b = $this->seedOrg('Org B', date('Y-m-d H:i:s'));

        Tiger_Mail::registerTemplate(['key' => 'reset', 'label' => 'Reset']);
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
        Tiger_Model_Config::setIniBase(null);
        parent::tearDown();
    }

    // =====================================================================================
    //  Harness
    // =====================================================================================

    private function seedOrg(string $name, string $createdAt): string
    {
        $id = Tiger_Uuid::v7();
        $this->db->insert('org', [
            'org_id'     => $id,
            'name'       => $name,
            'slug'       => 'o-' . substr($id, -12),
            'status'     => 'active',
            'deleted'    => 0,
            'created_at' => $createdAt,
        ]);
        return $id;
    }

    private function adminOf(string $org): void
    {
        $this->login('admin-' . substr($org, -8), $org, 'admin');
        Tiger_Model_Config::resetCache();
    }

    /** A fresh request on the given site (null = no host mapping: the install org), signed out. */
    private function anonymousOn(?string $site): void
    {
        $this->logout();
        Tiger_Model_Org::setSiteOrgId($site);
        Tiger_Model_Config::resetCache();
    }

    private function globalConfig(string $key): ?string
    {
        return (new Tiger_Model_Config())->get(Tiger_Model_Config::SCOPE_GLOBAL, '', $key);
    }

    private function setGlobal(string $key, string $value): void
    {
        (new Tiger_Model_Config())->set(Tiger_Model_Config::SCOPE_GLOBAL, '', $key, $value);
    }

    private static function call($obj, string $method, array $args = [])
    {
        $m = new ReflectionMethod($obj, $method);
        return $m->invokeArgs(is_object($obj) ? $obj : null, $args);
    }

    /**
     * The five-part proof every per-site setting must pass.
     *
     * @param string        $label      the setting, for failure messages
     * @param callable      $save       fn(string $v): void — the real writer, run as the signed-in admin
     * @param callable      $admin      fn(): string — what the admin screen shows for the caller's org
     * @param callable|null $site       fn(): string — the site-facing read (null: an admin-only setting)
     * @param string        $globalKey  a key the writer would have written global before TIGER-511
     * @param callable      $seedLegacy fn(string $v): void — store $v the old (global) way
     * @param string[]      $v          [A's value, B's value, the legacy global value]
     */
    private function prove(string $label, callable $save, callable $admin, ?callable $site,
                           string $globalKey, callable $seedLegacy, array $v): void
    {
        [$va, $vb, $vl] = $v;

        // 1. Org A saves; nothing lands in the global tier.
        $this->adminOf($this->a);
        $save($va);
        $this->assertSame($va, $admin(), "$label: A's screen shows what A saved");
        $this->assertNull($this->globalConfig($globalKey), "$label: nothing written global");

        // 2. A's save never changes B's effective value — and B's save never changes A's.
        $this->adminOf($this->b);
        $this->assertNotSame($va, $admin(), "$label: A's save did not reach org B");
        $save($vb);
        $this->assertSame($vb, $admin(), "$label: B's screen shows what B saved");
        $this->adminOf($this->a);
        $this->assertSame($va, $admin(), "$label: B's save did not reach org A");

        if ($site !== null) {
            // 3. Signed out on a single-site install: the install org's (founding org A's) value.
            $this->anonymousOn(null);
            $this->assertSame($va, $site(), "$label: a signed-out visitor reads the install org");

            // 4. A member of B, signed in, visiting site A sees A's value — never B's.
            $this->login('member-b', $this->b, 'user');
            Tiger_Model_Org::setSiteOrgId($this->a);
            Tiger_Model_Config::resetCache();
            $this->assertSame($va, $site(), "$label: B's member on site A sees site A's value");
            Tiger_Model_Org::setSiteOrgId($this->b);
            Tiger_Model_Config::resetCache();
            $this->assertSame($vb, $site(), "$label: and on site B sees B's");
        }

        // 5. A global-only legacy value still resolves for an org with none of its own.
        $c = $this->seedOrg('Org C', date('Y-m-d H:i:s'));
        $seedLegacy($vl);
        $this->adminOf($c);
        $this->assertSame($vl, $admin(), "$label: the legacy global value is C's fallback");
        if ($site !== null) {
            $this->anonymousOn($c);
            $this->assertSame($vl, $site(), "$label: and the public site of C reads it");
        }
    }

    // =====================================================================================
    //  The core helper
    // =====================================================================================

    #[Test]
    public function org_value_never_returns_another_orgs_value_and_falls_back_global_then_ini(): void
    {
        $cfg = new Tiger_Model_Config();
        $cfg->set(Tiger_Model_Config::SCOPE_ORG, $this->a, 'tiger.x.k', 'a');
        Tiger_Model_Config::setIniBase(['tiger' => ['x' => ['k' => 'ini', 'section' => ['n' => '1']]]]);

        $this->assertSame('a', Tiger_Model_Config::orgValue($this->a, 'tiger.x.k'));
        $this->assertSame('ini', Tiger_Model_Config::orgValue($this->b, 'tiger.x.k'), 'B never sees A — the ini answers');

        $cfg->set(Tiger_Model_Config::SCOPE_GLOBAL, '', 'tiger.x.k', 'global');
        $this->assertSame('global', Tiger_Model_Config::orgValue($this->b, 'tiger.x.k'), 'global beats ini');
        $this->assertSame('a', Tiger_Model_Config::orgValue($this->a, 'tiger.x.k'), 'the org row beats global');
        $this->assertSame('dflt', Tiger_Model_Config::orgValue($this->b, 'tiger.x.section', 'dflt'), 'a section is not a value');
        $this->assertSame('dflt', Tiger_Model_Config::orgValue($this->b, 'tiger.x.none', 'dflt'));
    }

    #[Test]
    public function site_value_reads_the_site_org_not_the_signed_in_visitors(): void
    {
        $cfg = new Tiger_Model_Config();
        $cfg->set(Tiger_Model_Config::SCOPE_ORG, $this->a, 'tiger.x.k', 'a');
        $cfg->set(Tiger_Model_Config::SCOPE_ORG, $this->b, 'tiger.x.k', 'b');

        $this->login('member-b', $this->b, 'user');
        Tiger_Model_Org::setSiteOrgId($this->a);
        $this->assertSame('a', Tiger_Model_Config::siteValue('tiger.x.k'));
        $this->assertSame('b', Tiger_Model_Config::currentValue('tiger.x.k'), 'currentValue is the caller\'s org');
        $this->assertSame($this->b, Tiger_Model_Config::currentOrgId());

        $this->anonymousOn(null);
        $this->assertSame($this->a, Tiger_Model_Config::currentOrgId(), 'signed out: the site (install) org');
    }

    #[Test]
    public function setting_scope_is_the_callers_org_and_global_only_with_no_org(): void
    {
        $this->adminOf($this->b);
        $this->assertSame([Tiger_Model_Config::SCOPE_ORG, $this->b], Tiger_Model_Config::settingScope());
        $this->assertSame([Tiger_Model_Config::SCOPE_ORG, $this->a], Tiger_Model_Config::settingScope($this->a));
        $this->assertSame([Tiger_Model_Config::SCOPE_GLOBAL, ''], Tiger_Model_Config::settingScope(''));
    }

    #[Test]
    public function a_write_invalidates_the_per_request_cache(): void
    {
        $this->assertNull(Tiger_Model_Config::orgValue($this->a, 'tiger.x.k'));
        (new Tiger_Model_Config())->set(Tiger_Model_Config::SCOPE_ORG, $this->a, 'tiger.x.k', 'now');
        $this->assertSame('now', Tiger_Model_Config::orgValue($this->a, 'tiger.x.k'));
    }

    // =====================================================================================
    //  Each per-site setting
    // =====================================================================================

    #[Test]
    public function identity_site_name(): void
    {
        $this->prove('identity name',
            function ($v) { $this->assertOk((new Identity_Service_Identity(['action' => 'save', 'site_name' => $v]))->getResponse()); },
            fn () => (string) Tiger_Model_Config::currentValue('tiger.site.name', ''),
            fn () => (string) self::call(Seo_Service_Head::class, '_config', ['site.name', '']),
            'tiger.site.name',
            fn ($v) => $this->setGlobal('tiger.site.name', $v),
            ['Alpha Books', 'Beta Books', 'Legacy Books']);
    }

    #[Test]
    public function identity_favicon(): void
    {
        $ids = ['0191aaaa-0000-7000-8000-00000000000a', '0191bbbb-0000-7000-8000-00000000000b', '0191cccc-0000-7000-8000-00000000000c'];
        $this->prove('identity favicon',
            function ($v) { $this->assertOk((new Identity_Service_Identity(['action' => 'save', 'site_name' => 'S', 'favicon_media_id' => $v]))->getResponse()); },
            fn () => (string) Tiger_Model_Config::currentValue('tiger.site.favicon', ''),
            fn () => (string) self::call(Identity_Plugin_Favicon::class, '_config', ['site.favicon', '']),
            'tiger.site.favicon',
            fn ($v) => $this->setGlobal('tiger.site.favicon', $v),
            $ids);
    }

    #[Test]
    public function analytics_tag(): void
    {
        $this->prove('analytics',
            function ($v) {
                $this->assertOk((new Analytics_Service_Analytics(['action' => 'save', 'ga4_measurement_id' => $v, 'enabled' => '1']))->getResponse());
            },
            fn () => (string) Tiger_Model_Config::currentValue('tiger.analytics.ga4.measurement_id', ''),
            fn () => Analytics_Plugin_Tag::measurementId(),
            'tiger.analytics.ga4.measurement_id',
            function ($v) {
                $this->setGlobal('tiger.analytics.enabled', '1');
                $this->setGlobal('tiger.analytics.ga4.measurement_id', $v);
            },
            ['G-AAAA1111', 'G-BBBB2222', 'G-CCCC9999']);
    }

    #[Test]
    public function analytics_reporting_connection_is_org_scoped(): void
    {
        $this->prove('GA property',
            fn ($v) => Tiger_Google_Analytics::saveOauthConfig('', '', $v),
            fn () => Tiger_Google_Analytics::propertyId(),
            null,                                                         // admin-only (reporting)
            'tiger.analytics.property_id',
            fn ($v) => $this->setGlobal('tiger.analytics.property_id', $v),
            ['111', '222', '999']);

        // The stored grant: disconnect clears the CALLER's org token, never a global one.
        $this->adminOf($this->b);
        Tiger_Google_Analytics::disconnect();
        $this->assertSame('', (new Tiger_Model_Config())->get(Tiger_Model_Config::SCOPE_ORG, $this->b, 'tiger.analytics.oauth.refresh_token_enc'));
        $this->assertNull($this->globalConfig('tiger.analytics.oauth.refresh_token_enc'));

        // The connect mode rides with it.
        Tiger_Google_Analytics::saveMode(Tiger_Google_Analytics::MODE_BYO);
        $this->assertSame(Tiger_Google_Analytics::MODE_BYO, Tiger_Google_Analytics::mode());
        $this->adminOf($this->a);
        $this->assertSame(Tiger_Google_Analytics::MODE_BROKER, Tiger_Google_Analytics::mode(), 'B\'s BYO switch did not reach A');
    }

    #[Test]
    public function matomo_tag(): void
    {
        $this->prove('matomo',
            function ($v) {
                $this->assertOk((new Matomo_Service_Matomo([
                    'action' => 'save', 'matomo_url' => $v, 'matomo_site_id' => '7', 'enabled' => '1',
                ]))->getResponse());
            },
            fn () => (string) Tiger_Model_Config::currentValue('tiger.matomo.url', ''),
            fn () => Matomo_Plugin_Tag::url(),
            'tiger.matomo.url',
            fn ($v) => $this->setGlobal('tiger.matomo.url', $v),
            ['https://a.example.test/', 'https://b.example.test/', 'https://legacy.example.test/']);
    }

    #[Test]
    public function email_templates(): void
    {
        $this->prove('email template',
            function ($v) {
                $this->assertOk((new Email_Service_Template([
                    'action' => 'save', 'template_key' => 'reset', 'subject' => $v, 'body' => '<p>b</p>',
                ]))->getResponse());
            },
            fn () => (string) ((new Email_Service_Template(['action' => 'get', 'key' => 'reset']))->getResponse()->data['subject'] ?? ''),
            function () {
                $row = self::call(new Tiger_Mail(), '_override', ['reset']);   // an unscoped send → the site org
                return $row ? (string) $row->subject : '';
            },
            'tiger.none',                                                     // its own table; checked below
            fn ($v) => (new Tiger_Model_EmailTemplate())->store('reset', '', $v, '<p>legacy</p>'),
            ['A subject', 'B subject', 'Legacy subject']);
    }

    #[Test]
    public function an_email_template_save_never_writes_the_global_row(): void
    {
        $this->adminOf($this->a);
        $this->assertOk((new Email_Service_Template([
            'action' => 'save', 'template_key' => 'reset', 'subject' => 'S', 'body' => '<p>b</p>', 'scope' => '',
        ]))->getResponse());
        $m = new Tiger_Model_EmailTemplate();
        $this->assertNull($m->byKey('reset', ''), 'a client-sent scope is ignored — nothing global');
        $this->assertNotNull($m->byKey('reset', $this->a));
    }

    #[Test]
    public function cms_home_page(): void
    {
        $home = function () {
            $ctl = (new ReflectionClass(IndexController::class))->newInstanceWithoutConstructor();
            return (string) self::call($ctl, '_homePageId');
        };
        $this->prove('cms home page',
            function ($v) { $this->assertOk((new Cms_Service_Settings(['action' => 'save', 'site_name' => 'S', 'home_page' => $v]))->getResponse()); },
            fn () => (string) Tiger_Model_Config::currentValue('tiger.site.home_page', ''),
            $home,
            'tiger.site.home_page',
            fn ($v) => $this->setGlobal('tiger.site.home_page', $v),
            ['/alpha', '/beta', '/legacy']);
    }

    #[Test]
    public function seo_social_cards(): void
    {
        $this->prove('social card',
            function ($v) { $this->assertOk((new Seo_Service_Social(['action' => 'save', 'page_key' => 'index', 'title' => $v]))->getResponse()); },
            fn () => (string) Tiger_Model_Config::currentValue('tiger.seo.page.index.title', ''),
            fn () => (string) (Seo_Service_Head::pageDefaults('index')['title'] ?? ''),
            'tiger.seo.page.index.title',
            fn ($v) => $this->setGlobal('tiger.seo.page.index.title', $v),
            ['A card', 'B card', 'Legacy card']);
    }

    #[Test]
    public function public_signup_kill_switch(): void
    {
        $this->prove('signup switch',
            function ($v) {
                $this->assertOk((new System_Service_Settings([
                    'action' => 'save', 'session_ttl_privileged' => '7200', 'session_ttl' => '3600', 'session_ttl_guest' => '1800',
                    'autologout_seconds' => '900', 'autologout_action' => 'logout',
                    'signup_settings' => '1', 'signup_disabled' => $v,
                ]))->getResponse());
            },
            fn () => (string) ((new Tiger_Model_Option())->getForOrg(Tiger_Model_Config::currentOrgId(), 'signup.public_disabled') ?? '0'),
            fn () => Signup_Service_Signup::isPublicDisabled() ? '1' : '0',
            'tiger.none',
            fn ($v) => (new Tiger_Model_Option())->set(Tiger_Model_Option::SCOPE_GLOBAL, '', 'signup.public_disabled', $v),
            ['1', '0', '1']);
    }

    #[Test]
    public function the_signup_switch_is_never_written_global(): void
    {
        $this->adminOf($this->a);
        $this->assertOk((new System_Service_Settings([
            'action' => 'save', 'session_ttl_privileged' => '7200', 'session_ttl' => '3600', 'session_ttl_guest' => '1800',
            'autologout_seconds' => '900', 'autologout_action' => 'logout',
            'signup_settings' => '1', 'signup_disabled' => '1',
        ]))->getResponse());
        $this->assertNull((new Tiger_Model_Option())->get(Tiger_Model_Option::SCOPE_GLOBAL, '', 'signup.public_disabled'));
    }

    #[Test]
    public function comment_spam_switch(): void
    {
        // No agent is connected in a test, so the screen can only store OFF — enough to prove the tier:
        // the install-wide legacy ON stays B's, and A's OFF never reaches it.
        $this->setGlobal(Tiger_Comment_Spam::CONFIG_AGENT, '1');
        $this->adminOf($this->a);
        $this->assertOk((new Comment_Service_Settings(['action' => 'save', 'spam_agent' => '0']))->getResponse());

        $this->assertSame('0', (new Tiger_Model_Config())->get(Tiger_Model_Config::SCOPE_ORG, $this->a, Tiger_Comment_Spam::CONFIG_AGENT));
        $this->assertSame('1', $this->globalConfig(Tiger_Comment_Spam::CONFIG_AGENT), 'the global row is untouched');

        $on = fn (?string $org) => (bool) self::call(Tiger_Comment_Spam::class, '_switchedOn', [$org]);
        $this->assertFalse($on($this->a), 'A: its own OFF');
        $this->assertTrue($on($this->b), 'B: still the install-wide ON');

        $this->login('member-b', $this->b, 'user');
        Tiger_Model_Org::setSiteOrgId($this->a);
        $this->assertFalse($on(null), 'a comment posted on site A uses A\'s switch, whoever posts it');
    }

    #[Test]
    public function recaptcha_keys(): void
    {
        $this->prove('recaptcha',
            fn ($v) => Tiger_Recaptcha::saveSettings(['site_key' => $v]),
            fn () => (string) Tiger_Recaptcha::settings()['site_key'],
            fn () => Tiger_Recaptcha::siteKey(),
            'tiger.recaptcha.site_key',
            fn ($v) => $this->setGlobal('tiger.recaptcha.site_key', $v),
            ['site-key-a', 'site-key-b', 'site-key-legacy']);
    }

    #[Test]
    public function a_saved_plaintext_fallback_secret_stays_with_its_org(): void
    {
        // No crypto in the test → the plaintext fallback; it must still land at the org, not global.
        $this->adminOf($this->a);
        Tiger_Recaptcha::saveSettings(['secret_key' => 'secret-a']);
        $this->assertNull($this->globalConfig('tiger.recaptcha.secret_key'));
        $this->assertSame('secret-a', Tiger_Recaptcha::secretKey($this->a));
        $this->assertSame('', Tiger_Recaptcha::secretKey($this->b));
    }

    #[Test]
    public function consent_banner(): void
    {
        $this->prove('consent',
            fn ($v) => Tiger_Consent::saveSettings(['mode' => 'always', 'message' => $v, 'honor_gpc' => 1]),
            fn () => Tiger_Consent::settings(Tiger_Model_Config::currentOrgId())['message'],
            fn () => Tiger_Consent::settings()['message'],
            'tiger.consent.message',
            fn ($v) => $this->setGlobal('tiger.consent.message', $v),
            ['Cookies on A.', 'Cookies on B.', 'Legacy cookies.']);
    }

    #[Test]
    public function mail_from_identity(): void
    {
        $this->prove('mail from',
            fn ($v) => Tiger_Mail::saveSettings(['from_email' => $v]),
            fn () => Tiger_Mail::settings()['from_email'],
            fn () => (string) self::call(new Tiger_Mail(), '_configFrom')[0],
            'mail.from.email',
            fn ($v) => $this->setGlobal('mail.from.email', $v),
            ['a@a.example.test', 'b@b.example.test', 'legacy@example.test']);
    }

    #[Test]
    public function the_mail_transport_stays_install_wide(): void
    {
        $this->adminOf($this->a);
        Tiger_Mail::saveSettings(['transport' => 'smtp', 'host' => 'smtp.example.test', 'from_email' => 'a@a.example.test']);
        $this->assertSame('smtp', $this->globalConfig('mail.transport'), 'transport is install plumbing — global');
        $this->assertSame('smtp.example.test', $this->globalConfig('mail.smtp.host'));
        $this->assertNull($this->globalConfig('mail.from.email'), 'the From is per site');
    }

    private function assertOk(object $res): void
    {
        $this->assertSame(1, (int) $res->result, json_encode($res->messages ?? []));
    }
}
