<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Support;

use Zend_Registry;
use Zend_Session;

/**
 * StatelessApi — run service tests the way a Bearer-token `/api` call runs: CSRF-immune.
 *
 * Every `/api` write validates a `Tiger_Form`, and a form adds a `_csrf` element unless the request is
 * stateless (`tiger.auth.stateless`, set by the gateway for a Bearer call). A CLI test has no browser
 * session to mint a token into, so a test that exercises a service's BEHAVIOUR (not its CSRF gate) calls
 * `useStatelessApi()` in setUp and `restoreStatelessApi()` in tearDown — the pattern PageServiceTest uses.
 *
 * A test of the CSRF gate itself calls `withCookieSession()` to switch to cookie mode with an
 * array-backed session, run the body, and restore.
 */
trait StatelessApi
{
    private bool $statelessHad = false;
    private $statelessPrior = null;

    /** Treat this test's service calls as stateless (Bearer) — no CSRF element on any form. */
    protected function useStatelessApi(): void
    {
        $reg = Zend_Registry::getInstance();
        $this->statelessHad   = $reg->offsetExists('tiger.auth.stateless');
        $this->statelessPrior = $this->statelessHad ? Zend_Registry::get('tiger.auth.stateless') : null;
        Zend_Registry::set('tiger.auth.stateless', true);
    }

    /** Put the stateless flag back the way setUp found it. */
    protected function restoreStatelessApi(): void
    {
        $reg = Zend_Registry::getInstance();
        if ($this->statelessHad) {
            Zend_Registry::set('tiger.auth.stateless', $this->statelessPrior);
        } elseif ($reg->offsetExists('tiger.auth.stateless')) {
            $reg->offsetUnset('tiger.auth.stateless');
        }
    }

    /**
     * Run $body in COOKIE mode (CSRF applies) with an array-backed session, then restore stateless mode.
     *
     * @param  callable $body the assertions to run
     * @return void
     */
    protected function withCookieSession(callable $body): void
    {
        $prior = Zend_Session::$_unitTestEnabled;
        Zend_Session::$_unitTestEnabled = true;
        $_SESSION = [];
        // An earlier test in the process may have write-closed the session (Zend_Session::writeClose marks
        // every namespace read-only process-wide); a token can't be minted into a read-only session.
        $writable = new \ReflectionProperty(\Zend_Session_Abstract::class, '_writable');
        $readable = new \ReflectionProperty(\Zend_Session_Abstract::class, '_readable');
        $priorW = $writable->getValue();
        $priorR = $readable->getValue();
        $writable->setValue(null, true);
        $readable->setValue(null, true);
        Zend_Registry::set('tiger.auth.stateless', false);
        try {
            $body();
        } finally {
            Zend_Registry::set('tiger.auth.stateless', true);
            $writable->setValue(null, $priorW);
            $readable->setValue(null, $priorR);
            Zend_Session::$_unitTestEnabled = $prior;
            $_SESSION = [];
        }
    }

    /**
     * Mint the live `_csrf` token for a form's salt in the current (array-backed) session.
     *
     * @param  \Tiger_Form $form any form of the salt the screen renders
     * @return string            the token value a rendered page would carry
     */
    protected function csrfTokenFor(\Tiger_Form $form): string
    {
        $el = $form->getElement('_csrf');
        $el->initCsrfToken();
        return (string) $el->getHash();
    }
}
