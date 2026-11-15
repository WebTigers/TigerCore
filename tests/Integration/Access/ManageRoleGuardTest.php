<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Access;

use Access_Service_User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Model_User;
use Tiger_Service_Service;
use Tiger_Uuid;
use Zend_Config;
use Zend_Registry;

/**
 * TIGER-511 — "may the caller manage a member who holds role X?" (`Tiger_Access_ManageRole`).
 *
 * Before the guard any `admin` could update any user and set the password of a superadmin or developer,
 * then sign in as them (superadmin reaches the Code Area = PHP execution). The capability's PRIVILEGE is
 * the TARGET's role, checked across EVERY membership the target holds, on save, password-set and delete
 * — and the same primitive gates GRANTING a role.
 *
 * No test here commits: the savepoint adapter lets the service's own `_transaction()` nest inside the
 * harness transaction, so every row (user, org, membership, credential) rolls back.
 */
#[CoversClass(Access_Service_User::class)]
#[CoversClass(Tiger_Service_Service::class)]
final class ManageRoleGuardTest extends IntegrationTestCase
{
    private ?Zend_Config $priorConfig = null;

    protected function setUp(): void
    {
        parent::setUp();
        Zend_Registry::set('tiger.auth.stateless', true);   // CSRF-immune API path (no session in CLI)
        $this->priorConfig = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        Zend_Registry::set('Zend_Config', new Zend_Config(['tiger' => ['i18n' => ['locales' => 'en,es']]], true));
    }

    protected function tearDown(): void
    {
        $reg = Zend_Registry::getInstance();
        if ($reg->offsetExists('tiger.auth.stateless')) { $reg->offsetUnset('tiger.auth.stateless'); }
        Zend_Registry::set('Zend_Config', $this->priorConfig ?? new Zend_Config([]));
        parent::tearDown();
    }

    /** A user holding `$role` in a fresh org — plus, optionally, a lower role in a second org. */
    private function member(string $role, ?string $alsoRole = null): string
    {
        $userId = (new Tiger_Model_User())->insert(['email' => 'm-' . bin2hex(random_bytes(5)) . '@w3ctest.com', 'status' => 'active']);
        $this->seedIdentityRows($userId, Tiger_Uuid::v7(), $role);
        if ($alsoRole !== null) {
            $this->seedIdentityRows($userId, Tiger_Uuid::v7(), $alsoRole);
        }
        return $userId;
    }

    private function call(string $action, array $params): object
    {
        return (new Access_Service_User(['action' => $action] + $params))->getResponse();
    }

    private function passwordCount(string $userId): int
    {
        return (int) $this->db->fetchOne("SELECT COUNT(*) FROM user_credential WHERE user_id = ? AND type = 'password'", [$userId]);
    }

    private function emailOf(string $userId): string
    {
        return (string) $this->db->fetchOne('SELECT email FROM user WHERE user_id = ?', [$userId]);
    }

    // ----- save / password-set ------------------------------------------------------------------

    #[Test]
    public function an_admin_cannot_set_a_superadmins_password(): void
    {
        $target = $this->member('superadmin');
        $before = $this->emailOf($target);
        $this->login('acting-admin', 'org-test', 'admin');

        $res = $this->call('save', ['user_id' => $target, 'email' => 'hijack@w3ctest.com', 'status' => 'active',
                                    'new_password' => 'S3cure-P@ssw0rd-9x']);

        $this->assertSame(0, (int) $res->result, 'refused');
        $this->assertStringContainsString('role_not_manageable', json_encode($res->messages));
        $this->assertSame(0, $this->passwordCount($target), 'no password was set on the superadmin');
        $this->assertSame($before, $this->emailOf($target), 'and the identity was not edited either');
    }

    #[Test]
    public function the_highest_role_in_any_org_decides(): void
    {
        // `user` in one org, `developer` in another: an admin must still be refused.
        $target = $this->member('user', 'developer');
        $this->login('acting-admin', 'org-test', 'admin');

        $res = $this->call('save', ['user_id' => $target, 'email' => $this->emailOf($target), 'status' => 'active',
                                    'new_password' => 'S3cure-P@ssw0rd-9x']);
        $this->assertSame(0, (int) $res->result, 'a developer membership anywhere puts the user out of an admin\'s reach');
        $this->assertSame(0, $this->passwordCount($target));
    }

    #[Test]
    public function a_superadmin_can_set_a_superadmins_password(): void
    {
        $target = $this->member('superadmin');
        $this->login('acting-super', 'org-test', 'superadmin');

        $res = $this->call('save', ['user_id' => $target, 'email' => $this->emailOf($target), 'status' => 'active',
                                    'new_password' => 'S3cure-P@ssw0rd-9x']);
        $this->assertSame(1, (int) $res->result, json_encode($res->messages));
        $this->assertSame(1, $this->passwordCount($target));
    }

    #[Test]
    public function an_admin_still_manages_admins_and_below(): void
    {
        $target = $this->member('admin', 'manager');
        $this->login('acting-admin', 'org-test', 'admin');

        $res = $this->call('save', ['user_id' => $target, 'email' => $this->emailOf($target), 'status' => 'active',
                                    'new_password' => 'S3cure-P@ssw0rd-9x']);
        $this->assertSame(1, (int) $res->result, json_encode($res->messages));
        $this->assertSame(1, $this->passwordCount($target));
    }

    // ----- delete -------------------------------------------------------------------------------

    #[Test]
    public function an_admin_cannot_delete_a_superadmin_but_a_superadmin_can(): void
    {
        $target = $this->member('superadmin');

        $this->login('acting-admin', 'org-test', 'admin');
        $res = $this->call('delete', ['user_id' => $target]);
        $this->assertSame(0, (int) $res->result, 'admin refused');
        $this->assertSame(0, (int) $this->db->fetchOne('SELECT deleted FROM user WHERE user_id = ?', [$target]));

        $this->login('acting-super', 'org-test', 'superadmin');
        $res = $this->call('delete', ['user_id' => $target]);
        $this->assertSame(1, (int) $res->result, 'superadmin allowed');
        $this->assertSame(1, (int) $this->db->fetchOne('SELECT deleted FROM user WHERE user_id = ?', [$target]));
    }

    #[Test]
    public function the_grid_does_not_offer_controls_on_an_unmanageable_row(): void
    {
        $target = $this->member('superadmin');
        $this->login('acting-admin', 'org-test', 'admin');

        $res = $this->call('datatable', ['draw' => 1, 'start' => 0, 'length' => 100, 'search' => $this->emailOf($target)]);
        $row = $res->data['data'][0] ?? null;
        $this->assertNotNull($row);
        $this->assertFalse($row['can_edit'], 'no edit control on a superadmin for an admin');
        $this->assertFalse($row['can_delete']);
    }

    // ----- granting a role (the primitive) ------------------------------------------------------

    /** The base-service primitive, as a role would see it — a throwaway service subclass. */
    private function mayGrant(string $callerRole, string $role): bool
    {
        $this->login('grantor-' . $callerRole, 'org-test', $callerRole);
        $probe = new class() extends Tiger_Service_Service {
            public function grant(string $role): bool { return $this->_mayManageRole($role); }
        };
        return $probe->grant($role);
    }

    #[Test]
    public function a_role_can_only_be_granted_by_someone_who_may_manage_it(): void
    {
        $this->assertTrue($this->mayGrant('admin', 'user'));
        $this->assertTrue($this->mayGrant('admin', 'admin'));
        $this->assertFalse($this->mayGrant('admin', 'superadmin'), 'an admin can never mint a superadmin');
        $this->assertFalse($this->mayGrant('admin', 'developer'));
        $this->assertTrue($this->mayGrant('superadmin', 'superadmin'));
        $this->assertFalse($this->mayGrant('superadmin', 'developer'));
        $this->assertTrue($this->mayGrant('developer', 'developer'));
        $this->assertFalse($this->mayGrant('manager', 'user'), 'below admin, no user management at all');
        $this->assertFalse($this->mayGrant('admin', ''), 'an empty role fails closed');
        $this->assertFalse($this->mayGrant('admin', 'some-custom-role'), 'an ungranted custom role fails closed');
    }
}
