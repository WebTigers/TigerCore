<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Access;

use Access_Service_User;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Model_Org;
use Tiger_Model_OrgUser;
use Tiger_Model_User;
use Tiger_Model_UserCredential;
use Zend_Config;
use Zend_Mail_Transport_Abstract;
use Tiger_Mail;
use Zend_Registry;

/**
 * The Users admin's add/edit modal: a user is created WITH their org memberships, edited in place
 * (memberships, status, the Deleted switch) and can't be used to lock the admin out.
 *
 * Pins the rules that matter: a new user must start in an org; a role is only grantable by someone who
 * may manage that tier; an edit that doesn't send memberships leaves them alone; an admin can't
 * delete, suspend or re-member themselves; a deleted user can be listed, opened and restored, and
 * still holds its email; a password left blank emails a set-your-password link.
 */
#[CoversClass(Access_Service_User::class)]
final class UserMembershipTest extends IntegrationTestCase
{
    private static ?CaptureTransport $mail = null;

    protected function setUp(): void
    {
        parent::setUp();
        Zend_Registry::set('tiger.auth.stateless', true);   // the Bearer/API path — no CSRF session in CLI
        Zend_Registry::set('Zend_Config', new Zend_Config(['tiger' => [
            'i18n' => ['locales' => 'en,es'],
            'site' => ['url' => 'https://example.test', 'name' => 'Example'],
        ]], true));
        self::$mail = new CaptureTransport();
        Tiger_Mail::setDefaultTransport(self::$mail);
    }

    protected function tearDown(): void
    {
        try {
            $this->db->query('DELETE FROM auth_challenge');
            $this->db->query('DELETE FROM user_credential');
            $this->db->query('DELETE FROM org_user');
            $this->db->query("DELETE FROM org WHERE slug LIKE 'umt-%'");
            $this->db->query('DELETE FROM user');
        } catch (\Throwable $e) {}
        $reg = Zend_Registry::getInstance();
        if ($reg->offsetExists('tiger.auth.stateless')) { $reg->offsetUnset('tiger.auth.stateless'); }
        Tiger_Mail::setDefaultTransport(null);
        parent::tearDown();
    }

    // ---- helpers -----------------------------------------------------------------------------------

    private function call(string $action, array $params = []): object
    {
        return (new Access_Service_User(['action' => $action] + $params))->getResponse();
    }

    private function org(string $name = 'UMT Org'): string
    {
        return (new Tiger_Model_Org())->insert(['name' => $name, 'slug' => 'umt-' . bin2hex(random_bytes(4))]);
    }

    /** @param array<string,string> $byOrg org_id => role */
    private function ms(array $byOrg): string
    {
        $out = [];
        foreach ($byOrg as $o => $r) { $out[] = ['org_id' => $o, 'role' => $r]; }
        return json_encode($out);
    }

    /** @return array<string,string> org_id => role, live memberships only */
    private function rolesOf(string $userId): array
    {
        $out = [];
        foreach ((new Tiger_Model_OrgUser())->membershipsWithOrgs($userId) as $m) { $out[$m['org_id']] = $m['role']; }
        ksort($out);
        return $out;
    }

    private function formErrorsFor(object $res, string $field): bool
    {
        return isset(((array) $res->form)[$field]);
    }

    // ---- create ------------------------------------------------------------------------------------

    #[Test]
    public function a_new_user_is_created_in_the_org_with_the_role(): void
    {
        $this->loginAs('admin');
        $org = $this->org();
        $this->db->commit();

        $res = $this->call('save', ['email' => 'umt-new@w3ctest.com', 'status' => 'active', 'new_password' => 'S3cure-P@ssw0rd-9x',
                                    'memberships' => $this->ms([$org => 'manager'])]);

        $this->assertSame(1, (int) $res->result, json_encode($res->messages));
        $this->assertSame([$org => 'manager'], $this->rolesOf($res->data['user_id']));
    }

    #[Test]
    public function a_new_user_without_a_membership_is_refused_and_nothing_is_written(): void
    {
        $this->loginAs('admin');
        $this->db->commit();

        $res = $this->call('save', ['email' => 'umt-orphan@w3ctest.com', 'status' => 'active', 'memberships' => '[]']);

        $this->assertSame(0, (int) $res->result);
        $this->assertTrue($this->formErrorsFor($res, 'memberships'));
        $this->assertNull((new Tiger_Model_User())->findByEmail('umt-orphan@w3ctest.com'));
    }

    #[Test]
    public function an_admin_cannot_grant_a_role_above_their_own(): void
    {
        $this->loginAs('admin');
        $org = $this->org();
        $this->db->commit();

        $res = $this->call('save', ['email' => 'umt-climber@w3ctest.com', 'status' => 'active',
                                    'memberships' => $this->ms([$org => 'superadmin'])]);

        $this->assertSame(0, (int) $res->result);
        $this->assertTrue($this->formErrorsFor($res, 'memberships'));
        $this->assertNull((new Tiger_Model_User())->findByEmail('umt-climber@w3ctest.com'));
    }

    #[Test]
    public function a_blank_password_emails_a_set_your_password_link(): void
    {
        $this->loginAs('admin');
        $org = $this->org();
        $this->db->commit();

        $res = $this->call('save', ['email' => 'umt-invitee@w3ctest.com', 'status' => 'active', 'memberships' => $this->ms([$org => 'user'])]);

        $this->assertSame(1, (int) $res->result);
        $this->assertTrue($res->data['invited']);
        $this->assertCount(1, self::$mail->sent);
        $this->assertStringContainsString('umt-invitee@w3ctest.com', self::$mail->sent[0]['to']);
        $this->assertMatchesRegularExpression('#https://example\.test/auth/reset/cid/[^/]+/code/[0-9a-f]{64}#', self::$mail->sent[0]['body']);
        $this->assertSame(0, (int) $this->db->fetchOne("SELECT COUNT(*) FROM user_credential WHERE user_id = ? AND type = 'password'", [$res->data['user_id']]),
            'no password is set for them — they choose it');
    }

    #[Test]
    public function a_chosen_password_sends_no_email(): void
    {
        $this->loginAs('admin');
        $org = $this->org();
        $this->db->commit();

        $res = $this->call('save', ['email' => 'umt-haspw@w3ctest.com', 'status' => 'active', 'new_password' => 'S3cure-P@ssw0rd-9x',
                                    'memberships' => $this->ms([$org => 'user'])]);

        $this->assertFalse($res->data['invited']);
        $this->assertCount(0, self::$mail->sent);
    }

    // ---- edit --------------------------------------------------------------------------------------

    #[Test]
    public function an_edit_that_does_not_send_memberships_leaves_them_alone(): void
    {
        $this->loginAs('admin');
        $org = $this->org();
        $id  = (new Tiger_Model_User())->insert(['email' => 'umt-keep@w3ctest.com', 'status' => 'active']);
        (new Tiger_Model_OrgUser())->assign($org, $id, 'manager');
        $this->db->commit();

        $res = $this->call('save', ['user_id' => $id, 'email' => 'umt-keep2@w3ctest.com', 'status' => 'active']);

        $this->assertSame(1, (int) $res->result);
        $this->assertSame([$org => 'manager'], $this->rolesOf($id), 'absence never means "remove them all"');
    }

    #[Test]
    public function an_edit_changes_roles_adds_and_removes_orgs(): void
    {
        $this->loginAs('admin');
        [$a, $b, $c] = [$this->org('A'), $this->org('B'), $this->org('C')];
        $id = (new Tiger_Model_User())->insert(['email' => 'umt-mover@w3ctest.com', 'status' => 'active']);
        (new Tiger_Model_OrgUser())->assign($a, $id, 'user');
        (new Tiger_Model_OrgUser())->assign($b, $id, 'user');
        $this->db->commit();

        $res = $this->call('save', ['user_id' => $id, 'email' => 'umt-mover@w3ctest.com', 'status' => 'active',
                                    'memberships' => $this->ms([$a => 'manager', $c => 'user'])]);

        $this->assertSame(1, (int) $res->result, json_encode($res->messages));
        $expected = [$a => 'manager', $c => 'user']; ksort($expected);
        $this->assertSame($expected, $this->rolesOf($id));

        // Re-adding the removed org reactivates its row (org_user's UNIQUE covers deleted rows too).
        $res = $this->call('save', ['user_id' => $id, 'email' => 'umt-mover@w3ctest.com', 'status' => 'active',
                                    'memberships' => $this->ms([$a => 'manager', $b => 'user', $c => 'user'])]);
        $this->assertSame(1, (int) $res->result, json_encode($res->messages));
        $this->assertSame(1, (int) $this->db->fetchOne('SELECT COUNT(*) FROM org_user WHERE org_id = ? AND user_id = ?', [$b, $id]));
        $this->assertArrayHasKey($b, $this->rolesOf($id));
    }

    #[Test]
    public function an_existing_user_cannot_be_left_with_no_membership(): void
    {
        $this->loginAs('admin');
        $org = $this->org();
        $id  = (new Tiger_Model_User())->insert(['email' => 'umt-last@w3ctest.com', 'status' => 'active']);
        (new Tiger_Model_OrgUser())->assign($org, $id, 'user');
        $this->db->commit();

        $res = $this->call('save', ['user_id' => $id, 'email' => 'umt-last@w3ctest.com', 'status' => 'active', 'memberships' => '[]']);

        $this->assertSame(0, (int) $res->result);
        $this->assertSame([$org => 'user'], $this->rolesOf($id));
    }

    // ---- self-lockout ------------------------------------------------------------------------------

    #[Test]
    public function an_admin_cannot_delete_suspend_or_re_member_themselves(): void
    {
        $org = $this->org();
        $me  = (new Tiger_Model_User())->insert(['email' => 'umt-me@w3ctest.com', 'status' => 'active']);
        (new Tiger_Model_OrgUser())->assign($org, $me, 'admin');
        $this->login($me, $org, 'admin');
        $this->db->commit();
        $base = ['user_id' => $me, 'email' => 'umt-me@w3ctest.com', 'status' => 'active'];

        $this->assertStringContainsString('no_self_delete', json_encode($this->call('save', $base + ['deleted' => '1'])->messages));
        $this->assertStringContainsString('no_self_suspend', json_encode($this->call('save', ['status' => 'suspended'] + $base)->messages));
        $other = $this->org('Other');
        $this->assertStringContainsString('no_self_memberships',
            json_encode($this->call('save', $base + ['memberships' => $this->ms([$org => 'admin', $other => 'user'])])->messages));

        $row = (new Tiger_Model_User())->findById($me);
        $this->assertNotNull($row);
        $this->assertSame('active', $row->status);
        $this->assertSame([$org => 'admin'], $this->rolesOf($me));
        // ...but an edit of their own email, with memberships unchanged, still works.
        $this->assertSame(1, (int) $this->call('save', ['email' => 'umt-me2@w3ctest.com'] + $base + ['memberships' => $this->ms([$org => 'admin'])])->result);
    }

    // ---- deleted -----------------------------------------------------------------------------------

    #[Test]
    public function the_deleted_switch_deletes_lists_opens_and_restores(): void
    {
        $this->loginAs('admin');
        $org = $this->org();
        $id  = (new Tiger_Model_User())->insert(['email' => 'umt-ghost@w3ctest.com', 'status' => 'active']);
        (new Tiger_Model_OrgUser())->assign($org, $id, 'user');
        $this->db->commit();
        $base = ['user_id' => $id, 'email' => 'umt-ghost@w3ctest.com', 'status' => 'active'];

        $this->assertSame(1, (int) $this->call('save', $base + ['deleted' => '1'])->result);
        $this->assertNull((new Tiger_Model_User())->findById($id), 'reads exclude the deleted user');

        $list = $this->call('datatable', ['draw' => 1, 'start' => 0, 'length' => 50, 'status' => 'deleted'])->data['data'];
        $this->assertSame([$id], array_column($list, 'user_id'), 'the Deleted filter lists only deleted users');
        $this->assertTrue($list[0]['deleted']);
        $this->assertFalse($list[0]['can_delete'], 'not deletable twice');

        $got = $this->call('get', ['user_id' => $id]);
        $this->assertSame(1, (int) $got->result, 'a deleted user can still be opened');
        $this->assertTrue($got->data['deleted']);

        $this->assertSame(1, (int) $this->call('save', $base + ['deleted' => '0'])->result);
        $this->assertNotNull((new Tiger_Model_User())->findById($id), 'restored');
        $this->assertSame([$org => 'user'], $this->rolesOf($id), 'memberships survive delete + restore');
    }

    #[Test]
    public function a_deleted_users_email_points_to_restore_not_a_twin(): void
    {
        $this->loginAs('admin');
        $org = $this->org();
        $id  = (new Tiger_Model_User())->insert(['email' => 'umt-twin@w3ctest.com', 'status' => 'active']);
        (new Tiger_Model_User())->softDelete(['user_id = ?' => $id]);
        $this->db->commit();

        $res = $this->call('save', ['email' => 'umt-twin@w3ctest.com', 'status' => 'active', 'memberships' => $this->ms([$org => 'user'])]);

        $this->assertSame(0, (int) $res->result);
        $this->assertStringContainsString('email_deleted', json_encode($res->messages));
    }

    // ---- modal reads -------------------------------------------------------------------------------

    #[Test]
    public function options_offer_only_roles_the_caller_may_grant(): void
    {
        $this->loginAs('admin');
        $roles = $this->call('options')->data['roles'];

        $this->assertContains('user', $roles);
        $this->assertContains('admin', $roles);
        $this->assertNotContains('superadmin', $roles, 'above an admin');
        $this->assertNotContains('guest', $roles);
    }

    #[Test]
    public function org_search_finds_by_name(): void
    {
        $this->loginAs('admin');
        $this->org('Zebra Unique Holdings');
        $orgs = $this->call('orgSearch', ['term' => 'Zebra Unique'])->data['orgs'];

        $this->assertSame(['Zebra Unique Holdings'], array_column($orgs, 'name'));
    }
}

/** Collects every message Tiger_Mail sends, so a test can read the welcome link. */
final class CaptureTransport extends Zend_Mail_Transport_Abstract
{
    /** @var array<int,array{to:string,body:string}> */
    public array $sent = [];

    protected function _sendMail()
    {
        $this->sent[] = ['to' => (string) $this->recipients, 'body' => quoted_printable_decode((string) $this->body)];
    }
}
