<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Message;

use Message_Service_Message;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Message;
use Tiger_Model_Message;
use Tiger_Model_MessageBlock;
use Tiger_Model_MessageRecipient;
use Zend_Config;
use Zend_Registry;

/**
 * The messaging rules, enforced in the service against a real schema (TIGER-114).
 *
 * What is pinned here is POLICY, not plumbing: who may send, who may be blocked, that a block is
 * silent, that deleting touches only your own copy, that a system message ignores blocks, and that
 * the tenant boundary holds. Each of those is a rule a screen cannot be trusted to enforce alone,
 * because /api is the same surface an agent drives.
 */
#[CoversClass(Message_Service_Message::class)]
#[CoversClass(Tiger_Message::class)]
#[CoversClass(Tiger_Model_Message::class)]
#[CoversClass(Tiger_Model_MessageRecipient::class)]
#[CoversClass(Tiger_Model_MessageBlock::class)]
final class MessageServiceTest extends IntegrationTestCase
{
    private const ORG   = 'org-msg';
    private const OTHER = 'org-other';

    protected function setUp(): void
    {
        parent::setUp();
        // CSRF is a cookie-mode defence; a CLI test has no session, so run these the way a Bearer-token
        // API call does — the same seam the other service integration tests use.
        Zend_Registry::set('tiger.auth.stateless', true);
        // Four people in one org, one outsider. Roles live on the MEMBERSHIP.
        $this->seedIdentityRows('u-admin',  self::ORG,   'admin');
        $this->seedIdentityRows('u-alice',  self::ORG,   'user');
        $this->seedIdentityRows('u-bob',    self::ORG,   'user');
        $this->seedIdentityRows('u-mgr',    self::ORG,   'manager');
        $this->seedIdentityRows('u-out',    self::OTHER, 'admin');
        $this->userToUser(false);
    }

    private function userToUser(bool $on): void
    {
        Zend_Registry::set('Zend_Config', new Zend_Config(['tiger' => ['message' => ['user_to_user' => $on ? '1' : '0']]]));
    }

    /** Run one service method as the current identity and return the envelope as plain arrays. */
    private function call(string $method, array $params = []): array
    {
        $svc = new Message_Service_Message();
        $svc->$method($params);
        $res  = $svc->getResponse();
        $msgs = [];
        foreach ((array) $res->messages as $m) { $msgs[] = ['message' => (string) $m->message, 'class' => (string) $m->class]; }
        return ['result' => (int) $res->result, 'data' => json_decode(json_encode($res->data), true) ?: [], 'messages' => $msgs];
    }

    /* ---- app → admins ----------------------------------------------------------------------- */

    #[Test]
    public function the_app_can_reach_every_admin_and_only_admins(): void
    {
        $id = Tiger_Message::toAdmins(self::ORG, 'Backup failed', 'The 02:00 backup did not complete.');
        $this->assertNotNull($id);

        $r = new Tiger_Model_MessageRecipient();
        $this->assertNotNull($r->getCopy($id, 'u-admin'), 'the admin received it');
        $this->assertNull($r->getCopy($id, 'u-alice'), 'a plain user did not');
        $this->assertNull($r->getCopy($id, 'u-mgr'),   'a manager is below admin and did not');
        $this->assertNull($r->getCopy($id, 'u-out'),   'an admin of ANOTHER org did not — tenancy');
        $this->assertSame(Tiger_Model_Message::KIND_SYSTEM, (new Tiger_Model_Message())->getMessage($id)['kind']);
    }

    #[Test]
    public function a_system_message_ignores_blocks(): void
    {
        // The admin has "blocked" the app — which is not a thing, and must not be.
        (new Tiger_Model_MessageBlock())->insert(['org_id' => self::ORG, 'user_id' => 'u-admin', 'blocked_user_id' => '']);
        $id = Tiger_Message::toAdmins(self::ORG, 'Still delivered', 'Operators cannot mute the platform.');
        $this->assertNotNull((new Tiger_Model_MessageRecipient())->getCopy($id, 'u-admin'));
    }

    #[Test]
    public function the_facade_is_fail_soft(): void
    {
        $this->assertNull(Tiger_Message::toAdmins('org-with-no-admins', 'x', 'y'), 'no admins → null, never a throw');
        $this->assertNull(Tiger_Message::toUser(self::ORG, '', 'x', 'y'));
    }

    /* ---- who may send ---------------------------------------------------------------------- */

    #[Test]
    public function a_plain_user_cannot_send_until_the_install_enables_it(): void
    {
        $this->login('u-alice', self::ORG, 'user');
        $res = $this->call('send', ['recipients' => ['u-bob'], 'subject' => 'hi', 'body' => 'there']);
        $this->assertSame(0, $res['result']);
        $this->assertSame('message.error.sending_disabled', $res['messages'][0]['message']);

        $this->userToUser(true);
        $res = $this->call('send', ['recipients' => ['u-bob'], 'subject' => 'hi', 'body' => 'there']);
        $this->assertSame(1, $res['result'], 'with the flag on, a member can message a member');
    }

    #[Test]
    public function an_admin_can_always_send(): void
    {
        $this->login('u-admin', self::ORG, 'admin');
        $res = $this->call('send', ['recipients' => ['u-alice'], 'subject' => 'Welcome', 'body' => 'Glad you are here.']);
        $this->assertSame(1, $res['result'], 'user-to-user is OFF and the admin can still reach a member');
        $this->assertNotNull((new Tiger_Model_MessageRecipient())->getCopy($res['data']['message_id'], 'u-alice'));
    }

    #[Test]
    public function you_cannot_send_across_the_tenant_boundary(): void
    {
        $this->login('u-admin', self::ORG, 'admin');
        $res = $this->call('send', ['recipients' => ['u-out'], 'subject' => 'x', 'body' => 'y']);
        $this->assertSame(0, $res['result']);
        $this->assertSame('message.error.unknown_recipient', $res['messages'][0]['message'],
            'a member of another org is "unknown", not "forbidden" — existence is private too');
    }

    #[Test]
    public function you_are_never_your_own_recipient(): void
    {
        $this->login('u-admin', self::ORG, 'admin');
        $res = $this->call('send', ['recipients' => ['u-admin', 'u-alice'], 'subject' => 'x', 'body' => 'y']);
        $this->assertSame(1, $res['result']);
        $this->assertNull((new Tiger_Model_MessageRecipient())->getCopy($res['data']['message_id'], 'u-admin'));
    }

    /* ---- blocking ---------------------------------------------------------------------------- */

    #[Test]
    public function a_block_is_silent_and_the_sender_learns_nothing(): void
    {
        $this->userToUser(true);
        $this->login('u-bob', self::ORG, 'user');
        $this->assertSame(1, $this->call('block', ['user_id' => 'u-alice'])['result']);

        $this->login('u-alice', self::ORG, 'user');
        $res = $this->call('send', ['recipients' => ['u-bob'], 'subject' => 'hello?', 'body' => '…']);
        $this->assertSame(1, $res['result'], 'the send REPORTS success');
        $this->assertSame('message.sent', $res['messages'][0]['message']);
        $this->assertNull($res['data']['message_id'], 'but nothing was written — every recipient was blocked');

        $this->login('u-bob', self::ORG, 'user');
        $this->assertSame(0, $this->call('unreadCount')['data']['unread'], 'and bob has nothing');
    }

    #[Test]
    public function a_block_drops_only_the_blocked_recipient(): void
    {
        $this->userToUser(true);
        $this->login('u-bob', self::ORG, 'user');
        $this->call('block', ['user_id' => 'u-alice']);

        $this->login('u-alice', self::ORG, 'user');
        $res = $this->call('send', ['recipients' => ['u-bob', 'u-mgr'], 'subject' => 'x', 'body' => 'y']);
        $id  = $res['data']['message_id'];
        $r   = new Tiger_Model_MessageRecipient();
        $this->assertNull($r->getCopy($id, 'u-bob'),     'blocked: dropped');
        $this->assertNotNull($r->getCopy($id, 'u-mgr'),  'not blocked: delivered');
    }

    #[Test]
    public function an_admin_or_higher_cannot_be_blocked(): void
    {
        $this->login('u-alice', self::ORG, 'user');
        $res = $this->call('block', ['user_id' => 'u-admin']);
        $this->assertSame(0, $res['result']);
        $this->assertSame('message.error.cannot_block_admin', $res['messages'][0]['message']);

        // A manager is BELOW admin in the chain and can be blocked.
        $this->assertSame(1, $this->call('block', ['user_id' => 'u-mgr'])['result']);
    }

    #[Test]
    public function the_admin_check_is_about_the_role_in_this_org(): void
    {
        // u-out is an admin — of ANOTHER org. In this org they are nobody, and the answer is "unknown".
        $this->login('u-alice', self::ORG, 'user');
        $res = $this->call('block', ['user_id' => 'u-out']);
        $this->assertSame('message.error.unknown_recipient', $res['messages'][0]['message']);
    }

    #[Test]
    public function you_cannot_block_yourself(): void
    {
        $this->login('u-alice', self::ORG, 'user');
        $this->assertSame('message.error.cannot_block', $this->call('block', ['user_id' => 'u-alice'])['messages'][0]['message']);
    }

    #[Test]
    public function unblocking_restores_delivery(): void
    {
        $this->userToUser(true);
        $this->login('u-bob', self::ORG, 'user');
        $this->call('block', ['user_id' => 'u-alice']);
        $this->assertCount(1, $this->call('blocked')['data']['blocked']);
        $this->call('unblock', ['user_id' => 'u-alice']);
        $this->assertCount(0, $this->call('blocked')['data']['blocked']);

        $this->login('u-alice', self::ORG, 'user');
        $id = $this->call('send', ['recipients' => ['u-bob'], 'subject' => 'x', 'body' => 'y'])['data']['message_id'];
        $this->assertNotNull((new Tiger_Model_MessageRecipient())->getCopy($id, 'u-bob'));
    }

    /* ---- my copy ------------------------------------------------------------------------------ */

    #[Test]
    public function opening_a_message_marks_my_copy_read_and_the_count_drops(): void
    {
        Tiger_Message::toAdmins(self::ORG, 'One', 'a');
        $id = Tiger_Message::toAdmins(self::ORG, 'Two', 'b');

        $this->login('u-admin', self::ORG, 'admin');
        $this->assertSame(2, $this->call('unreadCount')['data']['unread']);
        $res = $this->call('get', ['message_id' => $id]);
        $this->assertSame(1, $res['result']);
        $this->assertSame('b', $res['data']['message']['body']);
        $this->assertSame(1, $this->call('unreadCount')['data']['unread'], 'opening one read it');
    }

    #[Test]
    public function only_a_recipient_or_the_sender_may_read_a_message(): void
    {
        $id = Tiger_Message::toUser(self::ORG, 'u-alice', 'Private', 'for alice');
        $this->login('u-bob', self::ORG, 'user');
        $res = $this->call('get', ['message_id' => $id]);
        $this->assertSame(0, $res['result']);
        $this->assertSame('message.error.not_found', $res['messages'][0]['message'],
            'same org is not enough, and the answer is "not found" so existence stays private');

        $this->login('u-admin', self::ORG, 'admin');
        $this->assertSame(0, $this->call('get', ['message_id' => $id])['result'], 'an admin cannot read a message not addressed to them');
    }

    #[Test]
    public function deleting_removes_only_my_copy(): void
    {
        $id = Tiger_Message::toAdmins(self::ORG, 'Shared', 'to all admins');
        $this->seedIdentityRows('u-admin2', self::ORG, 'admin');
        $id2 = Tiger_Message::toAdmins(self::ORG, 'Shared again', 'now to two admins');

        $this->login('u-admin', self::ORG, 'admin');
        $this->assertSame(1, $this->call('delete', ['message_id' => $id2])['result']);
        $this->assertCount(1, $this->call('list')['data']['messages'], 'gone from MY inbox');

        $r = new Tiger_Model_MessageRecipient();
        $this->assertNotNull($r->getCopy($id2, 'u-admin2'), 'the other admin still has theirs');
        $this->assertNotNull((new Tiger_Model_Message())->getMessage($id2), 'and the message row itself is untouched');
    }

    #[Test]
    public function archive_moves_between_views_without_changing_read_state(): void
    {
        $id = Tiger_Message::toAdmins(self::ORG, 'File me', 'x');
        $this->login('u-admin', self::ORG, 'admin');
        $this->call('archive', ['message_id' => $id]);
        $this->assertCount(0, $this->call('list', ['view' => 'inbox'])['data']['messages']);
        $this->assertCount(1, $this->call('list', ['view' => 'archived'])['data']['messages']);
        $this->assertSame(1, $this->call('unreadCount')['data']['unread'], 'archiving is filing, not reading');
    }

    #[Test]
    public function a_reply_must_be_to_a_message_you_received_or_sent(): void
    {
        $this->userToUser(true);
        $id = Tiger_Message::toUser(self::ORG, 'u-alice', 'Q', '?');
        $this->login('u-bob', self::ORG, 'user');
        $res = $this->call('send', ['recipients' => ['u-alice'], 'subject' => 'Re', 'body' => '!', 'parent_id' => $id]);
        $this->assertSame('message.error.not_found', $res['messages'][0]['message'], 'bob never saw that message');
    }

    #[Test]
    public function the_recipient_picker_never_offers_another_org_or_yourself(): void
    {
        $this->login('u-admin', self::ORG, 'admin');
        $ids = array_column($this->call('recipients', ['q' => ''])['data']['recipients'], 'user_id');
        $this->assertContains('u-alice', $ids);
        $this->assertNotContains('u-out', $ids, 'a picker that completes across tenants is a directory leak');
        $this->assertNotContains('u-admin', $ids);
    }

    #[Test]
    public function signed_out_gets_nothing(): void
    {
        $this->login('', '', 'guest');
        $this->assertSame(0, $this->call('list')['result']);
        $this->assertSame(0, $this->call('unreadCount')['result']);
    }
}
