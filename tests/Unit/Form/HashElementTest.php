<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Form;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Form_Element_Hash;
use Zend_Form_Element_Hash;

/**
 * Tiger_Form_Element_Hash — the CSRF token armed on its TIMEOUT, not a single request hop.
 *
 * The regression: Zend's stock hash sets a 1-hop session expiration, so the first submit burns the
 * token; a submit that fails another field then can't be corrected and resubmitted without a full page
 * refresh ("your security token expired"). The subclass drops the hop and keeps only the seconds TTL.
 * A fake session (injected via setSession) records what initCsrfToken() arms — no real session needed.
 */
#[CoversClass(Tiger_Form_Element_Hash::class)]
final class HashElementTest extends UnitTestCase
{
    /** A session double that records the expiration calls + the stored token. */
    private function fakeSession(): object
    {
        return new class {
            public $hops = 'UNSET';
            public $seconds = null;
            public $hash = null;
            public function setExpirationHops($hops, $ns = null, $hop = false) { $this->hops = $hops; return $this; }
            public function setExpirationSeconds($s) { $this->seconds = $s; return $this; }
        };
    }

    #[Test]
    public function armsTokenOnTimeoutNotASingleHop(): void
    {
        $el = new Tiger_Form_Element_Hash('_csrf', ['salt' => 'unit', 'timeout' => 7200]);
        $session = $this->fakeSession();
        $el->setSession($session);

        $el->initCsrfToken();

        $this->assertSame('UNSET', $session->hops, 'no single-hop expiration is armed — that was the bug');
        $this->assertSame(7200, $session->seconds, 'the token lives for its full timeout instead');
        $this->assertNotEmpty($session->hash, 'a token is generated and stored');
    }

    #[Test]
    public function contrastStockZendHashArmsASingleHop(): void
    {
        // Documents exactly what the subclass overrides: the stock element sets a 1-hop expiration.
        $el = new Zend_Form_Element_Hash('_csrf', ['salt' => 'unit', 'timeout' => 7200]);
        $session = $this->fakeSession();
        $el->setSession($session);

        $el->initCsrfToken();

        $this->assertSame(1, $session->hops, 'stock Zend arms a single-use (1-hop) token');
    }
}
