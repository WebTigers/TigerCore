<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Message;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Acl_Acl;
use Tiger_Message;
use Zend_Config;
use Zend_Registry;

/**
 * The TIGER-335 reference conversion, pinned at the ACL layer (the direct mitigation for the
 * blanket→scoped risk: a conversion is proven via explain(), not eyeballed).
 *
 * Asserts the message module's acl.ini, as shipped, (1) carries NO blanket resource-level grant on
 * Message_Service_Message, (2) still admits `user` to every one of its enumerated privileges, (3)
 * declares the Tiger_Message_Compose capability granted to admin-and-up only, and (4) that
 * Tiger_Message::maySend() ORs that capability with the user-to-user flag — so a renamed admin-class
 * role is handled by inheritance and no role string decides anything.
 */
#[CoversClass(Tiger_Acl_Acl::class)]
#[CoversClass(Tiger_Message::class)]
final class MessageAclConversionTest extends UnitTestCase
{
    private const SERVICE = 'Message_Service_Message';
    private const COMPOSE = 'Tiger_Message_Compose';

    /** Every public method of Message_Service_Message is granted to `user` at the service (enumerated). */
    private const USER_PRIVILEGES = [
        'list', 'recent', 'unreadCount', 'get', 'recipients', 'markRead', 'markUnread',
        'archive', 'unarchive', 'delete', 'block', 'unblock', 'blocked', 'send',
    ];

    private Tiger_Acl_Acl $acl;

    protected function setUp(): void
    {
        parent::setUp();
        $this->acl = new Tiger_Acl_Acl();                 // the real shipped policy (core + module acl.ini)
        Zend_Registry::set('Zend_Acl', $this->acl);
        $this->userToUser(false);
    }

    private function userToUser(bool $on): void
    {
        Zend_Registry::set('Zend_Config', new Zend_Config(['tiger' => ['message' => ['user_to_user' => $on ? '1' : '0']]]));
    }

    #[Test]
    public function the_service_carries_no_blanket_resource_level_grant(): void
    {
        // A resource-level (privilege = null) check is the F5 footgun. After the conversion it must be
        // deny-by-default — no rule grants the whole service at once.
        $this->assertFalse(
            $this->acl->isAllowed('user', self::SERVICE, null),
            'Message_Service_Message must NOT have a blanket resource-level allow (TIGER-335)'
        );
        $this->assertFalse($this->acl->isAllowed('guest', self::SERVICE, null), 'and certainly not for guest');
    }

    #[Test]
    #[DataProvider('userPrivileges')]
    public function each_enumerated_privilege_still_admits_user(string $privilege): void
    {
        // explain() returns the deciding rule — a conversion is PROVEN, not eyeballed.
        $r = $this->acl->explain('user', self::SERVICE, $privilege);
        $this->assertTrue($r['allowed'], "user must still be allowed '{$privilege}' — {$r['reason']}");
    }

    public static function userPrivileges(): array
    {
        return array_map(fn ($p) => [$p], self::USER_PRIVILEGES);
    }

    #[Test]
    public function the_compose_capability_is_admin_class_only(): void
    {
        $this->assertFalse($this->acl->isAllowed('user', self::COMPOSE, 'send'), 'a plain user is not admin-class');
        $this->assertFalse($this->acl->isAllowed('manager', self::COMPOSE, 'send'), 'a manager is below admin');
        $this->assertTrue($this->acl->isAllowed('admin', self::COMPOSE, 'send'), 'admin holds the capability');
        // Inheritance carries it upward — the rename-safety property. (These roles ship above admin.)
        $this->assertTrue($this->acl->isAllowed('superadmin', self::COMPOSE, 'send'), 'superadmin inherits it');
        $this->assertTrue($this->acl->isAllowed('developer', self::COMPOSE, 'send'), 'developer inherits it');
    }

    #[Test]
    public function maySend_ORs_the_capability_with_the_user_to_user_flag(): void
    {
        $this->userToUser(false);
        $this->assertTrue(Tiger_Message::maySend('admin'), 'admin may always compose (capability)');
        $this->assertTrue(Tiger_Message::maySend('developer'), 'a role above admin inherits the capability');
        $this->assertFalse(Tiger_Message::maySend('user'), 'a plain user may not compose with the flag OFF');
        $this->assertFalse(Tiger_Message::maySend('manager'), 'nor a manager');

        $this->userToUser(true);
        $this->assertTrue(Tiger_Message::maySend('user'), 'a plain user MAY compose once user-to-user is ON');
    }

    #[Test]
    public function maySend_fails_closed_with_no_acl_registered(): void
    {
        // Defect #4 in the ticket: the old `$role === 'admin'` shortcut passed admin before the
        // ACL-registered check. The capability check fails CLOSED instead — no role is admin-class
        // without a policy to grant it.
        Zend_Registry::getInstance()->offsetUnset('Zend_Acl');
        $this->userToUser(false);
        $this->assertFalse(Tiger_Message::maySend('admin'), 'no ACL registered → fail closed, even for admin');
    }
}
