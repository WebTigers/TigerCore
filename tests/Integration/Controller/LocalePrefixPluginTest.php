<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Controller_Plugin_LocalePrefix;
use Tiger_Model_User;
use Zend_Controller_Request_Http;
use Zend_Controller_Request_Simple;

/**
 * Tiger_Controller_Plugin_LocalePrefix — semantic /xx/ locale URLs + language resolution at
 * routeStartup. It strips a leading SUPPORTED-language segment so every route matches the remainder,
 * and resolves the request language by precedence: URL prefix → cookie → signed-in user's `user.locale` →
 * `locale` cookie → browser Accept-Language → configured default. The choice persists to the cookie.
 *
 * The user tier reads `user.locale` from the DB (an account choice follows a person across devices),
 * so this is an integration test. The observable, deterministic effect used throughout is the resolved
 * language landing in `$_COOKIE['locale']` (and the path being locale-stripped) — the process-global
 * LANG constant is asserted only for its presence, since `define()` fires once per process.
 */
#[CoversClass(Tiger_Controller_Plugin_LocalePrefix::class)]
final class LocalePrefixPluginTest extends IntegrationTestCase
{
    private array $priorCookie;
    private array $priorServer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->priorCookie = $_COOKIE;
        $this->priorServer = $_SERVER;
        unset($_COOKIE['locale'], $_SERVER['HTTP_ACCEPT_LANGUAGE']);
    }

    protected function tearDown(): void
    {
        $_COOKIE = $this->priorCookie;
        $_SERVER = $this->priorServer;
        parent::tearDown();
    }

    private function plugin(): Tiger_Controller_Plugin_LocalePrefix
    {
        return new Tiger_Controller_Plugin_LocalePrefix(['en', 'es'], 'en');
    }

    private function http(string $pathInfo): Zend_Controller_Request_Http
    {
        $r = new Zend_Controller_Request_Http();
        $r->setPathInfo($pathInfo);
        return $r;
    }

    #[Test]
    public function a_non_http_request_is_ignored(): void
    {
        $req = new Zend_Controller_Request_Simple();
        $this->plugin()->routeStartup($req);
        $this->assertTrue(true, 'no throw on a non-HTTP request');
    }

    #[Test]
    public function a_supported_locale_prefix_is_stripped_from_the_path(): void
    {
        $req = $this->http('/es/pricing');
        $this->plugin()->routeStartup($req);

        $this->assertSame('/pricing', $req->getPathInfo(), 'the /es prefix is stripped so routes match');
        $this->assertSame('es', $_COOKIE['locale'], 'the URL locale is resolved + persisted');
    }

    #[Test]
    public function a_bare_locale_prefix_becomes_the_root_path(): void
    {
        $req = $this->http('/es');
        $this->plugin()->routeStartup($req);

        $this->assertSame('/', $req->getPathInfo());
        $this->assertSame('es', $_COOKIE['locale']);
    }

    #[Test]
    public function an_unsupported_two_letter_segment_is_not_treated_as_a_locale(): void
    {
        // "no" (a content slug) is NOT in the supported set, so the path is left intact.
        $req = $this->http('/no/thanks');
        $this->plugin()->routeStartup($req);

        $this->assertSame('/no/thanks', $req->getPathInfo(), 'a non-locale 2-letter head is preserved');
    }

    #[Test]
    public function a_three_letter_iso_639_2_locale_prefix_is_supported(): void
    {
        // Klingon (tlh) has no 2-letter ISO 639-1 code; a /xxx/ prefix must still resolve when supported.
        $plugin = new Tiger_Controller_Plugin_LocalePrefix(['en', 'es', 'tlh'], 'en');
        $req = $this->http('/tlh/pricing');
        $plugin->routeStartup($req);

        $this->assertSame('/pricing', $req->getPathInfo(), 'the /tlh prefix is stripped so routes match');
        $this->assertSame('tlh', $_COOKIE['locale'], 'the 3-letter locale is resolved + persisted');
    }

    #[Test]
    public function an_unsupported_three_letter_segment_is_not_treated_as_a_locale(): void
    {
        // "/cms" is a real controller path, 3 letters, NOT in the supported set — so it's left intact.
        $req = $this->http('/cms/edit');
        $this->plugin()->routeStartup($req);

        $this->assertSame('/cms/edit', $req->getPathInfo(), 'a non-locale 3-letter head is preserved');
    }

    #[Test]
    public function the_cookie_resolves_the_language_when_there_is_no_prefix(): void
    {
        $_COOKIE['locale'] = 'es';
        $req = $this->http('/pricing');
        $this->plugin()->routeStartup($req);

        $this->assertSame('/pricing', $req->getPathInfo());
        $this->assertSame('es', $_COOKIE['locale']);
    }

    #[Test]
    public function the_browser_accept_language_resolves_when_no_prefix_or_cookie(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'es-ES,es;q=0.9,en;q=0.8';
        $req = $this->http('/pricing');
        $this->plugin()->routeStartup($req);

        $this->assertSame('es', $_COOKIE['locale'], 'the first supported browser language wins');
    }

    #[Test]
    public function it_falls_back_to_the_configured_default(): void
    {
        // No prefix, no cookie, no (supported) browser header => the default 'en'.
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'fr-FR,fr;q=0.9';
        $req = $this->http('/pricing');
        $this->plugin()->routeStartup($req);

        $this->assertSame('en', $_COOKIE['locale']);
    }

    #[Test]
    public function an_explicit_cookie_choice_beats_the_users_stored_locale(): void
    {
        // user.locale is a SUGGESTION, not a mandate: an explicit UI selection (the switcher writes the
        // cookie) wins over the stored account preference — so switching the language works when signed in.
        $userId = (new Tiger_Model_User())->insert(['email' => 'loc@user.test', 'status' => 'active', 'locale' => 'en']);
        $this->login($userId, 'org-test', 'user');
        $_COOKIE['locale'] = 'es';   // the user just picked Spanish in the switcher

        $req = $this->http('/pricing');
        $this->plugin()->routeStartup($req);

        $this->assertSame('es', $_COOKIE['locale'], 'the explicit cookie choice wins over user.locale');
    }

    #[Test]
    public function the_users_stored_locale_is_the_fallback_when_no_url_or_cookie(): void
    {
        // With no explicit choice (no /xx/ URL, no cookie, no supported browser header), the account
        // locale fills in — a personalized fallback that follows the person across devices.
        $userId = (new Tiger_Model_User())->insert(['email' => 'loc3@user.test', 'status' => 'active', 'locale' => 'es']);
        $this->login($userId, 'org-test', 'user');

        $req = $this->http('/pricing');
        $this->plugin()->routeStartup($req);

        $this->assertSame('es', $_COOKIE['locale'], 'user.locale is the fallback when nothing explicit is set');
    }

    #[Test]
    public function an_explicit_url_prefix_still_wins_over_the_users_locale(): void
    {
        $userId = (new Tiger_Model_User())->insert(['email' => 'loc2@user.test', 'status' => 'active', 'locale' => 'es']);
        $this->login($userId, 'org-test', 'user');

        $req = $this->http('/en/pricing');   // explicit for THIS navigation
        $this->plugin()->routeStartup($req);

        $this->assertSame('en', $_COOKIE['locale'], 'the URL prefix is the top of the precedence chain');
    }

    #[Test]
    public function it_defines_the_lang_and_supported_langs_constants(): void
    {
        $req = $this->http('/pricing');
        $this->plugin()->routeStartup($req);

        // define() fires once per process, so assert presence (not a specific value that a prior test may own).
        $this->assertTrue(defined('LANG'));
        $this->assertTrue(defined('SUPPORTED_LANGS'));
    }
}
