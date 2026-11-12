<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Newsletter;

use Newsletter_Model_Subscriber;
use Newsletter_Service_Subscribe;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;

/**
 * The newsletter subscribe `/api` service against a real database — the policy that makes public
 * collection safe: consent-first (never auto-confirmed), the honeypot + too-fast + rate-limit flood
 * guards, token-redeemed confirm/unsubscribe, and the admin-only grid.
 */
#[CoversClass(Newsletter_Service_Subscribe::class)]
final class SubscribeServiceTest extends IntegrationTestCase
{
    /** The registry config before this test; restored in tearDown so the reCAPTCHA flag never leaks. */
    private $_configBefore = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->_configBefore = \Zend_Registry::isRegistered('Zend_Config') ? \Zend_Registry::get('Zend_Config') : null;
        // Run as a Bearer-style API call (no CLI session); the form has no CSRF element anyway.
        \Zend_Registry::set('tiger.auth.stateless', true);
        $_SERVER['REMOTE_ADDR']    = '203.0.113.7';
        $_SERVER['HTTP_USER_AGENT'] = 'phpunit';
    }

    protected function tearDown(): void
    {
        $reg = \Zend_Registry::getInstance();
        if ($reg->offsetExists('tiger.auth.stateless')) { $reg->offsetUnset('tiger.auth.stateless'); }
        unset($_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);
        if ($this->_configBefore !== null) {
            \Zend_Registry::set('Zend_Config', $this->_configBefore);
        } elseif ($reg->offsetExists('Zend_Config')) {
            $reg->offsetUnset('Zend_Config');
        }
        parent::tearDown();
    }

    /** @return array{result:int,data:array} */
    private function subscribe(array $params): array
    {
        $svc = new Newsletter_Service_Subscribe();
        $svc->subscribe($params + ['_t' => time() - 10]);
        $res = $svc->getResponse();
        return ['result' => (int) $res->result, 'data' => (array) $res->data];
    }

    private function rowFor(string $email): ?array
    {
        return (new Newsletter_Model_Subscriber())->forEmail('', $email);
    }

    #[Test]
    public function a_subscribe_is_stored_pending_and_never_auto_confirmed(): void
    {
        $out = $this->subscribe(['email' => 'ada@example.com', 'name' => 'Ada', 'source' => 'footer']);

        $this->assertSame(1, $out['result']);
        $row = $this->rowFor('ada@example.com');
        $this->assertNotNull($row);
        $this->assertSame(Newsletter_Model_Subscriber::STATUS_PENDING, $row['status'], 'consent-first: pending, not confirmed');
        $this->assertNull($row['consent_at'], 'consent is recorded at confirm, not at subscribe');
        $this->assertSame('footer', $row['consent_source']);
        $this->assertNotEmpty($row['token'], 'an opaque token is minted for the confirm/unsubscribe links');
    }

    #[Test]
    public function the_honeypot_rejects_a_bot(): void
    {
        $out = $this->subscribe(['email' => 'bot@example.com', '_hp' => 'http://spam.example']);

        $this->assertSame(0, $out['result']);
        $this->assertNull($this->rowFor('bot@example.com'), 'nothing is stored for a honeypot hit');
    }

    #[Test]
    public function an_instant_submission_is_rejected(): void
    {
        $svc = new Newsletter_Service_Subscribe();
        $svc->subscribe(['email' => 'fast@example.com', '_t' => time()]);

        $this->assertSame(0, (int) $svc->getResponse()->result);
        $this->assertNull($this->rowFor('fast@example.com'));
    }

    #[Test]
    public function an_invalid_email_is_refused(): void
    {
        $out = $this->subscribe(['email' => 'not-an-email']);

        $this->assertSame(0, $out['result']);
    }

    #[Test]
    public function a_flood_from_one_address_is_rate_limited(): void
    {
        for ($i = 0; $i < Newsletter_Service_Subscribe::RATE_LIMIT; $i++) {
            $out = $this->subscribe(['email' => "flood{$i}@example.com"]);
            $this->assertSame(1, $out['result'], "attempt $i is within the limit");
        }
        $out = $this->subscribe(['email' => 'flood-over@example.com']);
        $this->assertSame(0, $out['result'], 'past the limit the same IP is refused');
    }

    #[Test]
    public function confirming_a_token_records_consent(): void
    {
        $this->subscribe(['email' => 'confirm@example.com']);
        $token = $this->rowFor('confirm@example.com')['token'];

        $svc = new Newsletter_Service_Subscribe();
        $svc->confirm(['token' => $token]);
        $this->assertSame(1, (int) $svc->getResponse()->result);

        $row = $this->rowFor('confirm@example.com');
        $this->assertSame(Newsletter_Model_Subscriber::STATUS_CONFIRMED, $row['status']);
        $this->assertNotNull($row['consent_at'], 'consent time is stamped at confirm');
    }

    #[Test]
    public function a_bad_confirm_token_is_refused(): void
    {
        $svc = new Newsletter_Service_Subscribe();
        $svc->confirm(['token' => 'no-such-token']);

        $this->assertSame(0, (int) $svc->getResponse()->result);
    }

    #[Test]
    public function unsubscribing_by_token_opts_the_person_out(): void
    {
        $this->subscribe(['email' => 'bye@example.com']);
        $token = $this->rowFor('bye@example.com')['token'];

        $svc = new Newsletter_Service_Subscribe();
        $svc->unsubscribe(['token' => $token]);
        $this->assertSame(1, (int) $svc->getResponse()->result);

        $row = $this->rowFor('bye@example.com');
        $this->assertSame(Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED, $row['status']);
        $this->assertNotNull($row['unsubscribed_at']);
    }

    /** A previously-unsubscribed address must re-opt-in, never silently resurrect its consent. */
    #[Test]
    public function re_subscribing_an_unsubscribed_address_returns_to_pending(): void
    {
        $this->subscribe(['email' => 'again@example.com']);
        $token = $this->rowFor('again@example.com')['token'];
        (new Newsletter_Service_Subscribe())->unsubscribe(['token' => $token]);

        $out = $this->subscribe(['email' => 'again@example.com']);
        $this->assertSame(1, $out['result']);

        $row = $this->rowFor('again@example.com');
        $this->assertSame(Newsletter_Model_Subscriber::STATUS_PENDING, $row['status'], 'consent is not resurrected — they must confirm again');
        $this->assertNull($row['consent_at']);
    }

    #[Test]
    public function the_subscriber_grid_is_admin_only(): void
    {
        // Guest.
        $svc = new Newsletter_Service_Subscribe();
        $svc->datatable([]);
        $this->assertSame(0, (int) $svc->getResponse()->result, 'a guest cannot read the grid');

        // Signed-in non-admin.
        $this->loginAs('user');
        $svc = new Newsletter_Service_Subscribe();
        $svc->datatable([]);
        $this->assertSame(0, (int) $svc->getResponse()->result, 'a plain user cannot read the grid');

        // Admin.
        $this->loginAs('admin');
        $svc = new Newsletter_Service_Subscribe();
        $svc->datatable([]);
        $this->assertSame(1, (int) $svc->getResponse()->result, 'an admin can');
    }

    /** reCAPTCHA on with a site key but no secret: verification fails closed with no HTTP. */
    private function recaptchaOn(): void
    {
        $base = \Zend_Registry::isRegistered('Zend_Config') ? \Zend_Registry::get('Zend_Config')->toArray() : [];
        $cfg  = new \Zend_Config($base, true);
        $cfg->merge(new \Zend_Config(['tiger' => ['recaptcha' => [
            'enabled' => '1', 'version' => 'v2', 'site_key' => 'PUBLIC-SITE-KEY', 'secret_key' => '',
        ]]], true));
        \Zend_Registry::set('Zend_Config', $cfg);
    }

    #[Test]
    public function a_subscribe_without_a_recaptcha_token_is_refused_and_sends_nothing(): void
    {
        $this->recaptchaOn();

        $svc = new Newsletter_Service_Subscribe();
        $svc->subscribe(['email' => 'bot-target@example.com', '_t' => time() - 10]);
        $res = $svc->getResponse();

        $this->assertSame(0, (int) $res->result);
        $this->assertArrayHasKey('recaptcha', (array) $res->form, 'the refusal comes from the form\'s reCAPTCHA element');
        $this->assertNull($this->rowFor('bot-target@example.com'), 'no row, so no confirmation mail to an address a script chose');
    }

    #[Test]
    public function the_embeddable_form_carries_the_recaptcha_widget_only_when_it_is_on(): void
    {
        $off = (new \Newsletter_Service_Render())->form();
        $this->assertStringNotContainsString('g-recaptcha', $off, 'reCAPTCHA off: nothing rendered');

        $this->recaptchaOn();
        $on = (new \Newsletter_Service_Render())->form();
        $this->assertMatchesRegularExpression('#<form[^>]*>.*class="g-recaptcha".*</form>#s', $on, 'the widget sits INSIDE the form, so its token posts with it');
    }
}
