<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Service_Authentication;
use Zend_Auth;

/**
 * Tiger_Service_Authentication::establishSession — programmatic sign-in (no password round-trip) for an
 * account that was just created or otherwise proven. The seam guest checkout uses: create a passwordless
 * account, then establish its session so the rest of the flow runs as that user. These characterize the
 * contract: it signs in a real user (resolving their active-membership org + role), and it fails closed on
 * an unknown user id.
 */
#[CoversClass(Tiger_Service_Authentication::class)]
final class AuthenticationEstablishSessionTest extends IntegrationTestCase
{
    #[Test]
    public function it_establishes_a_session_for_a_known_user_without_a_password(): void
    {
        // Use the harness's fixture user to get a real id + active membership, then drop the session.
        $this->loginAs('user');
        $uid = Zend_Auth::getInstance()->getIdentity()->user_id;
        $this->logout();
        $this->assertFalse(Zend_Auth::getInstance()->hasIdentity(), 'precondition: signed out');

        $identity = (new Tiger_Service_Authentication())->establishSession($uid);

        $this->assertIsObject($identity, 'a resolvable user yields an identity');
        $this->assertTrue(Zend_Auth::getInstance()->hasIdentity(), 'the session is now established');
        $this->assertSame($uid, Zend_Auth::getInstance()->getIdentity()->user_id);
        $this->assertNotNull($identity->org_id, 'the active-membership org is resolved');
    }

    #[Test]
    public function it_fails_closed_on_an_unknown_user(): void
    {
        $this->assertFalse(Zend_Auth::getInstance()->hasIdentity());
        $result = (new Tiger_Service_Authentication())->establishSession('00000000-0000-0000-0000-000000000000');
        $this->assertFalse($result, 'an unknown user id returns false');
        $this->assertFalse(Zend_Auth::getInstance()->hasIdentity(), 'and never establishes a session');
    }
}
