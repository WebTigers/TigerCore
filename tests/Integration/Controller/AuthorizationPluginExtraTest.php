<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\ControllerTestCase;
use Tiger_Controller_Plugin_Authorization;
use Zend_Controller_Front;
use Zend_Controller_Request_Http;
use Zend_Controller_Response_Http;
use Zend_Registry;
use Zend_Session;

/**
 * Tiger_Controller_Plugin_Authorization — the preDispatch() gate itself (the sibling
 * AuthorizationPluginTest covers the `_resolveRole`/`_resourceFor` decision helpers in isolation).
 *
 * Here the whole front-controller entry runs against the real dispatcher + the real shipped ACL:
 *   - a non-dispatchable URL is left alone (ZF's ErrorHandler renders the 404 — a guest is NOT bounced
 *     to login for a URL that doesn't exist);
 *   - the gate fails OPEN when no `Zend_Acl` is registered (a partial boot never locks the app out);
 *   - a guest-allowed controller (IndexController) proceeds untouched;
 *   - an authenticated-but-forbidden caller is DENIED → re-dispatched to the themed 403 (error/forbidden,
 *     HTTP 403) rather than emitting a bare string. That forbidden branch of `_deny()` doesn't exit the
 *     process, so it's assertable in-process (the guest/locked branches call gotoUrlAndExit — a real
 *     process exit — so they stay at the functional/smoke level).
 *
 * Uses Zend's array-backed unit-test session so `setReturnTo()` / `isLocked()` (session reads/writes in
 * the deny path) run for real under CLI.
 */
#[CoversClass(Tiger_Controller_Plugin_Authorization::class)]
final class AuthorizationPluginExtraTest extends ControllerTestCase
{
    private bool $priorUnitTestMode;

    protected function setUp(): void
    {
        parent::setUp();
        $this->priorUnitTestMode = Zend_Session::$_unitTestEnabled;
        Zend_Session::$_unitTestEnabled = true;
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        Zend_Session::$_unitTestEnabled = $this->priorUnitTestMode;
        parent::tearDown();
    }

    private function request(string $controller, string $action = 'index', string $module = 'default'): Zend_Controller_Request_Http
    {
        $r = new Zend_Controller_Request_Http();
        $r->setModuleName($module)->setControllerName($controller)->setActionName($action)->setDispatched(true);
        return $r;
    }

    /** Run preDispatch with the plugin wired to $request + a fresh response (as the front would). */
    private function runGate(Zend_Controller_Request_Http $request): Zend_Controller_Response_Http
    {
        $response = new Zend_Controller_Response_Http();
        Zend_Controller_Front::getInstance()->setRequest($request)->setResponse($response);

        $plugin = new Tiger_Controller_Plugin_Authorization();
        $plugin->setRequest($request);
        $plugin->setResponse($response);
        $plugin->preDispatch($request);
        return $response;
    }

    #[Test]
    public function a_non_dispatchable_url_is_left_for_the_error_handler(): void
    {
        $this->login('anon', 'o-1', 'guest');
        $req = $this->request('no-such-controller-xyz');
        $res = $this->runGate($req);

        // Untouched: not re-dispatched to the 403, no forbidden code — ZF's ErrorHandler will 404 it.
        $this->assertNotSame(403, $res->getHttpResponseCode());
        $this->assertSame('no-such-controller-xyz', $req->getControllerName(), 'the request was not rewritten');
    }

    #[Test]
    public function the_gate_fails_open_when_no_acl_is_registered(): void
    {
        // A real, dispatchable controller, but the ACL never loaded (partial boot) → the gate must return
        // rather than lock everyone out.
        if (Zend_Registry::isRegistered('Zend_Acl')) { Zend_Registry::getInstance()->offsetUnset('Zend_Acl'); }

        $req = $this->request('index');
        $res = $this->runGate($req);

        $this->assertNotSame(403, $res->getHttpResponseCode());
        $this->assertSame('index', $req->getControllerName());
    }

    #[Test]
    public function a_guest_allowed_controller_proceeds(): void
    {
        $this->login('anon', 'o-1', 'guest');   // registers the real shipped ACL
        $req = $this->request('index');          // IndexController is allowed to guest in core acl.ini
        $res = $this->runGate($req);

        $this->assertNotSame(403, $res->getHttpResponseCode(), 'a guest-allowed page is not denied');
        $this->assertSame('index', $req->getControllerName(), 'no rewrite — the action proceeds');
    }

    #[Test]
    public function an_authenticated_but_forbidden_caller_is_forwarded_to_the_403(): void
    {
        $this->loginAs('user');                  // a plain user...
        $req = $this->request('admin');          // ...hitting AdminController, which has no allow rule
        $res = $this->runGate($req);

        // _deny(): not a guest, not locked → re-dispatch to the themed 403 (no process exit).
        $this->assertSame(403, $res->getHttpResponseCode());
        $this->assertSame('error', $req->getControllerName());
        $this->assertSame('forbidden', $req->getActionName());
        $this->assertFalse($req->isDispatched(), 'the request is re-queued for the error controller');
    }
}
