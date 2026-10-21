<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\ControllerTestCase;
use Tiger_Controller_Plugin_Authorization;
use Tiger_Log;
use Zend_Config;
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
 *   - an authenticated-but-forbidden caller is DENIED → redirected to their own dashboard (TIGER-359),
 *     EXCEPT when the forbidden resource IS that dashboard, which falls back to the themed 403
 *     (error/forbidden, HTTP 403) — the loop-guard branch, which doesn't exit the process, so it's
 *     assertable in-process. The ordinary redirect + the guest/locked branches call gotoUrlAndExit (a
 *     real process exit), so the redirect TARGET is pinned by `_forbiddenRedirect` in the sibling test.
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
    public function an_authenticated_forbidden_caller_hitting_their_own_dashboard_falls_back_to_403(): void
    {
        // The ordinary forbidden case now REDIRECTS to the dashboard via gotoUrlAndExit (a real exit —
        // its target is pinned by _forbiddenRedirect in the sibling test). The one in-process-assertable
        // branch is the LOOP GUARD: an authenticated user forbidden their OWN dashboard path must NOT be
        // bounced to the dashboard again — it re-dispatches to the themed 403 instead (TIGER-359).
        $this->loginAsReal('user');                  // a plain user → dashboard is /account...
        $req = $this->request('admin');              // ...hitting AdminController (no allow rule for user)
        $req->setPathInfo('/account');               // ...reached AT the dashboard path → loop guard fires

        // _deny() logs the attempt via Tiger_Log; route it to a null writer so the diagnostic line
        // doesn't print during the test (the established Tiger_Log test pattern).
        $prior = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        $arr = $prior ? $prior->toArray() : [];
        $arr['tiger']['log']['writer'] = 'null';
        Zend_Registry::set('Zend_Config', new Zend_Config($arr));
        Tiger_Log::reset();
        try {
            $res = $this->runGate($req);
        } finally {
            if ($prior !== null) { Zend_Registry::set('Zend_Config', $prior); }
            Tiger_Log::reset();
        }

        $this->assertSame(403, $res->getHttpResponseCode());
        $this->assertSame('error', $req->getControllerName());
        $this->assertSame('forbidden', $req->getActionName());
        $this->assertFalse($req->isDispatched(), 'the request is re-queued for the error controller, no redirect loop');
    }
}
