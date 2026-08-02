<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Model_Org;
use Tiger_Model_OrgUser;
use Tiger_Model_User;
use Tiger_Service_Authentication;
use Zend_Auth;

/**
 * Tiger_Service_Authentication::establishSession — programmatic sign-in (no password round-trip) for an
 * account that was just created or otherwise proven. The seam guest checkout uses: create a passwordless
 * account, then establish its session so the rest of the flow runs as that user. These characterize the
 * contract: it signs in a REAL user (resolving their active-membership org + role) and fails closed on an
 * unknown user id. Note loginAs() writes a NON-persistent fixture identity (no DB row), so a real
 * user/org/membership is created here — establishSession() does a findById.
 */
#[CoversClass(Tiger_Service_Authentication::class)]
final class AuthenticationEstablishSessionTest extends IntegrationTestCase
{
    #[Test]
    public function it_establishes_a_session_for_a_known_user_without_a_password(): void
    {
        // A real, active user + org + active membership (rolled back with the per-test transaction).
        $rnd   = substr(bin2hex(random_bytes(4)), 0, 8);
        $orgId = (string) (new Tiger_Model_Org())->insert(['name' => 'EstablishSession Org', 'slug' => 'est-' . $rnd, 'status' => 'active']);
        $uid   = (string) (new Tiger_Model_User())->insert(['email' => 'est-' . $rnd . '@example.test', 'status' => 'active']);
        (new Tiger_Model_OrgUser())->insert(['org_id' => $orgId, 'user_id' => $uid, 'role' => 'user', 'status' => 'active']);

        Zend_Auth::getInstance()->clearIdentity();
        $this->assertFalse(Zend_Auth::getInstance()->hasIdentity(), 'precondition: signed out');

        $identity = (new Tiger_Service_Authentication())->establishSession($uid);

        $this->assertIsObject($identity, 'a resolvable user yields an identity');
        $this->assertTrue(Zend_Auth::getInstance()->hasIdentity(), 'the session is now established');
        $this->assertSame($uid, Zend_Auth::getInstance()->getIdentity()->user_id);
        $this->assertSame($orgId, (string) $identity->org_id, 'the active-membership org is resolved');
    }

    #[Test]
    public function it_fails_closed_on_an_unknown_user(): void
    {
        Zend_Auth::getInstance()->clearIdentity();
        $result = (new Tiger_Service_Authentication())->establishSession('00000000-0000-0000-0000-000000000000');
        $this->assertFalse($result, 'an unknown user id returns false');
        $this->assertFalse(Zend_Auth::getInstance()->hasIdentity(), 'and never establishes a session');
    }
}
