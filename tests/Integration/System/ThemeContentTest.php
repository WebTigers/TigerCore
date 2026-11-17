<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\System;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use System_Service_Themecontent;
use System_ThemecontentController;
use Tiger\Tests\Support\ControllerTestCase;
use Tiger_Theme_Remote;
use Zend_Registry;
use Zend_Session;

/**
 * TIGER-455 — "Update now" for theme content: the scheduled 15-minute fetch, on demand, on this server. The service
 * runs Tiger_Theme_Remote::sync() and reports files checked / updated / removed + the manifest's publish stamp; it
 * refuses (with a reason) when no source is configured or a fetch is already running; it needs the screen's CSRF
 * token in a browser session; and the screen renders status from the LOCAL manifest without ever fetching.
 */
#[CoversClass(System_Service_Themecontent::class)]
#[CoversClass(System_ThemecontentController::class)]
final class ThemeContentTest extends ControllerTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FakeThemeContentRemote::reset();
        Zend_Registry::set('tiger.auth.stateless', true);   // the /api path with no browser session (CSRF pinned below)
        $this->login('u-455', 'org-test', 'admin');
    }

    protected function tearDown(): void
    {
        $reg = Zend_Registry::getInstance();
        if ($reg->offsetExists('tiger.auth.stateless')) { $reg->offsetUnset('tiger.auth.stateless'); }
        parent::tearDown();
    }

    private function call(string $method, array $params = []): object
    {
        return (new ThemeContentServiceDouble(['action' => $method] + $params))->getResponse();
    }

    private function messages(object $res): string
    {
        return implode(' | ', array_map(fn ($m) => is_object($m) ? (string) $m->message : (string) ($m['message'] ?? ''), (array) $res->messages));
    }

    #[Test]
    public function update_now_fetches_and_reports_the_counts_and_publish_stamp(): void
    {
        FakeThemeContentRemote::$result = ['ok' => true, 'changed' => 2, 'removed' => 1, 'files' => 40, 'bytes' => 9,
                                           'generated' => '2026-11-17T15:00:00Z', 'busy' => false, 'error' => ''];
        $res = $this->call('fetch', ['force' => '1']);

        $this->assertSame(1, (int) $res->result, $this->messages($res));
        $this->assertSame(['files' => 40, 'changed' => 2, 'removed' => 1, 'generated' => '2026-11-17T15:00:00Z', 'synced_at' => 1763391600],
            $res->data);
        $this->assertSame([true], FakeThemeContentRemote::$forced, 'force=1 re-downloads every file');
    }

    #[Test]
    public function it_refuses_with_a_reason_when_there_is_nothing_to_fetch_or_a_fetch_is_running(): void
    {
        FakeThemeContentRemote::$enabled = false;
        $res = $this->call('fetch');
        $this->assertSame(0, (int) $res->result);
        $this->assertSame([], FakeThemeContentRemote::$forced, 'no source → never fetched');

        FakeThemeContentRemote::$enabled = true;
        FakeThemeContentRemote::$result = ['busy' => true, 'error' => '', 'files' => 0, 'changed' => 0, 'removed' => 0, 'generated' => ''];
        $this->assertSame(0, (int) $this->call('fetch')->result, 'a fetch already running is refused, not doubled');

        FakeThemeContentRemote::$result = ['busy' => false, 'error' => 'manifest unreachable', 'files' => 0, 'changed' => 0, 'removed' => 0, 'generated' => ''];
        $this->assertSame(0, (int) $this->call('fetch')->result, 'a failed fetch is reported as a failure');
    }

    #[Test]
    public function status_reads_the_local_manifest_and_a_non_admin_is_refused(): void
    {
        $res = $this->call('status');
        $this->assertSame(1, (int) $res->result);
        $this->assertSame(40, $res->data['files']);
        $this->assertSame('2026-11-17T14:00:00Z', $res->data['generated']);
        $this->assertArrayNotHasKey('dir', $res->data, 'the server path is not exposed');
        $this->assertSame([], FakeThemeContentRemote::$forced, 'status never fetches');

        $this->login('u-455b', 'org-test', 'user');
        $this->assertSame(0, (int) $this->call('fetch')->result, 'fetch is admin+');
        $this->assertSame(0, (int) $this->call('status')->result, 'status is admin+');
        $this->assertSame([], FakeThemeContentRemote::$forced);
    }

    #[Test]
    public function in_a_browser_session_a_fetch_without_the_screens_token_is_refused(): void
    {
        Zend_Registry::set('tiger.auth.stateless', false);
        $prior = Zend_Session::$_unitTestEnabled;
        Zend_Session::$_unitTestEnabled = true;
        $_SESSION = [];
        $w = new \ReflectionProperty(\Zend_Session_Abstract::class, '_writable');
        $priorW = (bool) $w->getValue();
        $w->setValue(null, true);
        try {
            $res = $this->call('fetch', ['service' => 'themecontent']);
            $this->assertSame(0, (int) $res->result, 'tokenless');
            $this->assertSame([], FakeThemeContentRemote::$forced, 'nothing fetched without the token');

            $el = (new \System_Form_ThemeContent())->getElement('_csrf');
            $el->initCsrfToken();
            FakeThemeContentRemote::$result = ['busy' => false, 'error' => '', 'files' => 1, 'changed' => 0, 'removed' => 0, 'generated' => 'g'];
            $ok = $this->call('fetch', ['service' => 'themecontent', '_csrf' => $el->getHash()]);
            $this->assertSame(1, (int) $ok->result, 'the screen\'s token is accepted: ' . $this->messages($ok));
        } finally {
            Zend_Registry::set('tiger.auth.stateless', true);
            Zend_Session::$_unitTestEnabled = $prior;
            $_SESSION = [];
            $w->setValue(null, $priorW);
        }
    }

    #[Test]
    public function the_screen_renders_without_fetching(): void
    {
        $this->dispatchAction(System_ThemecontentController::class, 'index');
        $view = $this->controller->view;
        $this->assertArrayHasKey('enabled', $view->status);
        $this->assertInstanceOf(\System_Form_ThemeContent::class, $view->form, 'the form whose _csrf the view renders (cookie mode)');
    }
}

/** The service, with the network-free remote double. */
class ThemeContentServiceDouble extends System_Service_Themecontent
{
    protected function _remote(): Tiger_Theme_Remote { return new FakeThemeContentRemote(); }
}

/** A remote that never touches the network: canned status + sync results, and a record of every sync call. */
class FakeThemeContentRemote extends Tiger_Theme_Remote
{
    public static bool $enabled = true;
    public static array $result = [];
    public static array $forced = [];
    public static function reset(): void
    {
        self::$enabled = true;
        self::$result  = ['ok' => true, 'changed' => 0, 'removed' => 0, 'files' => 0, 'bytes' => 0, 'generated' => '', 'busy' => false, 'error' => ''];
        self::$forced  = [];
    }
    public function isEnabled() { return self::$enabled; }
    public function status()
    {
        return ['enabled' => self::$enabled, 'url' => 'https://cdn.test/marketing', 'dir' => '/srv/secret/path', 'files' => 40,
                'bytes' => 9, 'generated' => '2026-11-17T14:00:00Z', 'synced_at' => 1763391600, 'error' => ''];
    }
    public function sync($force = false) { self::$forced[] = (bool) $force; return self::$result; }
}
