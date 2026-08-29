<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Comment;

use Comment_Service_Comment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Comment;
use Tiger_Model_Comment;
use Tiger_Model_CommentAggregate;
use Tiger_Model_Config;

/**
 * The comment `/api` service against a real database — the policy that decides whether this feature
 * is safe to expose: the off-by-default gate, the moderation posture, one-rating-per-user, the
 * self-review refusal, the honeypot, and the rollup staying in step with the thread.
 */
#[CoversClass(Comment_Service_Comment::class)]
final class CommentServiceTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // CSRF is a cookie-mode defence; a CLI test has no session, so run these the way a Bearer-token
        // API call does — the same seam the other service integration tests use.
        \Zend_Registry::set('tiger.auth.stateless', true);

        Tiger_Comment::reset();
        Tiger_Comment::registerSubject([
            'key'     => 'test.thing',
            'label'   => 'Thing',
            'resolve' => static fn ($id) => ['title' => 'A Thing', 'url' => '/thing/' . $id, 'exists' => $id !== 'gone'],
            'ratings' => true,
            'owns'    => static fn ($id, $uid) => $uid === 'owner-user',
        ]);
        $this->enable(true);
    }

    protected function tearDown(): void
    {
        $reg = \Zend_Registry::getInstance();
        if ($reg->offsetExists('tiger.auth.stateless')) { $reg->offsetUnset('tiger.auth.stateless'); }
        Tiger_Comment::reset();
        parent::tearDown();
    }

    /**
     * Flip the feature flag.
     *
     * `Tiger_Comment::isEnabled()` reads the merged `Zend_Config` the bootstrap publishes, and the
     * integration harness boots no front controller — so the registry entry may not exist at all.
     * Seed it when absent rather than assuming a booted app.
     */
    private function enable(bool $on): void
    {
        (new Tiger_Model_Config())->set('global', '', Tiger_Comment::CONFIG_ENABLED, $on ? '1' : '0');

        $flag = ['tiger' => ['comment' => ['enabled' => $on ? '1' : '0']]];

        // A config published by a real bootstrap is READ-ONLY, and another test in the same run may
        // have registered one — so merge onto a modifiable COPY rather than mutating in place, which
        // throws "Zend_Config is read only" only when the full suite runs. Isolation-passing,
        // suite-failing is exactly the bug this avoids.
        if (\Zend_Registry::isRegistered('Zend_Config')) {
            $existing = \Zend_Registry::get('Zend_Config');
            $merged   = new \Zend_Config($existing->toArray(), true);
            $merged->merge(new \Zend_Config($flag, true));
            \Zend_Registry::set('Zend_Config', $merged);
        } else {
            \Zend_Registry::set('Zend_Config', new \Zend_Config($flag, true));
        }
    }

    private function post(array $params): array
    {
        $svc = new Comment_Service_Comment();
        $svc->post($params + ['subject' => 'test.thing:t1', 'body' => 'Nice one', '_t' => time() - 10]);
        $res = $svc->getResponse();
        return ['result' => (int) $res->result, 'data' => (array) $res->data, 'messages' => $res->messages];
    }

    #[Test]
    public function a_disabled_install_refuses_every_call(): void
    {
        $this->enable(false);

        $out = $this->post([]);

        $this->assertSame(0, $out['result'], 'comments are off by default and must behave as if absent');
    }

    #[Test]
    public function an_unregistered_subject_is_refused(): void
    {
        $svc = new Comment_Service_Comment();
        $svc->post(['subject' => 'not.registered:1', 'body' => 'hi', '_t' => time() - 10]);

        $this->assertSame(0, (int) $svc->getResponse()->result);
    }

    #[Test]
    public function a_subject_the_provider_says_is_gone_takes_no_comments(): void
    {
        $svc = new Comment_Service_Comment();
        $svc->post(['subject' => 'test.thing:gone', 'body' => 'hi', '_t' => time() - 10]);

        $this->assertSame(0, (int) $svc->getResponse()->result);
    }

    #[Test]
    public function the_honeypot_rejects_a_bot(): void
    {
        $out = $this->post(['_hp' => 'http://spam.example']);

        $this->assertSame(0, $out['result'], 'a field no human can see was filled');
    }

    #[Test]
    public function an_instant_submission_is_rejected(): void
    {
        $out = $this->post(['_t' => time()]);

        $this->assertSame(0, $out['result'], 'rendered and submitted in under a second');
    }

    #[Test]
    public function an_empty_comment_with_no_rating_is_refused(): void
    {
        $out = $this->post(['body' => '   ']);

        $this->assertSame(0, $out['result']);
    }

    #[Test]
    public function a_star_only_rating_is_accepted(): void
    {
        $this->loginAs('user');
        $out = $this->post(['body' => '', 'rating' => 5]);

        $this->assertSame(1, $out['result'], 'a rating with no words is a legitimate review');
    }

    #[Test]
    public function a_new_comment_is_held_for_moderation_by_default(): void
    {
        $this->loginAs('user');
        $out = $this->post([]);

        $this->assertSame(1, $out['result']);
        $this->assertSame(Tiger_Model_Comment::STATUS_PENDING, $out['data']['status']);
    }

    /** A pending comment must not move a public average — otherwise posting alone shifts a score. */
    #[Test]
    public function a_pending_rating_does_not_move_the_average(): void
    {
        $this->loginAs('user');
        $this->post(['rating' => 5]);

        $agg = (new Tiger_Model_CommentAggregate())->forSubject('test.thing', 't1');
        $this->assertSame(0, $agg['rating_count']);
        $this->assertSame(0.0, $agg['rating_avg']);
    }

    #[Test]
    public function approving_a_rating_updates_the_rollup(): void
    {
        $this->loginAs('user');
        $this->post(['rating' => 4]);

        $row = (new Tiger_Model_Comment())->byStatus(Tiger_Model_Comment::STATUS_PENDING, 5)[0];

        $svc = new Comment_Service_Comment();
        $svc->moderate(['comment_id' => $row['comment_id'], 'status' => Tiger_Model_Comment::STATUS_APPROVED]);

        $agg = (new Tiger_Model_CommentAggregate())->forSubject('test.thing', 't1');
        $this->assertSame(1, $agg['rating_count']);
        $this->assertSame(4.0, $agg['rating_avg']);
        $this->assertSame(1, $agg['stars'][4]);
    }

    #[Test]
    public function a_second_rating_edits_the_first_rather_than_stacking(): void
    {
        $this->loginAs('user');
        $this->post(['rating' => 2]);
        $this->post(['rating' => 5]);

        $rows = (new Tiger_Model_Comment())->byStatus(Tiger_Model_Comment::STATUS_PENDING, 10);
        $rated = array_filter($rows, static fn ($r) => $r['rating'] !== null);

        $this->assertCount(1, $rated, 'one rating per user per subject');
        $this->assertSame(5, (int) reset($rated)['rating']);
    }

    #[Test]
    public function the_subject_owner_cannot_rate_their_own_thing(): void
    {
        $identity = $this->loginAs('user');
        $identity->user_id = 'owner-user';   // the provider says this user owns the subject

        $svc = new Comment_Service_Comment();
        $svc->post(['subject' => 'test.thing:t1', 'body' => 'Mine is great', 'rating' => 5, '_t' => time() - 10]);

        $this->assertSame(0, (int) $svc->getResponse()->result, 'self-review is the obvious way to poison ratings');
    }

    #[Test]
    public function a_comment_without_a_rating_is_fine_from_the_owner(): void
    {
        $identity = $this->loginAs('user');
        $identity->user_id = 'owner-user';

        $svc = new Comment_Service_Comment();
        $svc->post(['subject' => 'test.thing:t1', 'body' => 'Thanks for the feedback', '_t' => time() - 10]);

        $this->assertSame(1, (int) $svc->getResponse()->result, 'a vendor may reply, just not rate');
    }

    #[Test]
    public function a_guest_is_refused_unless_guests_are_allowed(): void
    {
        $out = $this->post([]);   // not signed in

        $this->assertSame(0, $out['result']);
    }

    #[Test]
    public function the_public_thread_shows_only_approved_comments(): void
    {
        $this->loginAs('user');
        $this->post(['body' => 'held back']);

        $svc = new Comment_Service_Comment();
        $svc->list(['subject' => 'test.thing:t1']);
        $data = (array) $svc->getResponse()->data;

        $this->assertSame([], $data['comments']);
        $this->assertTrue($data['ratings'], 'the subject accepts stars');
    }

    #[Test]
    public function a_public_projection_never_leaks_an_email_or_ip(): void
    {
        $this->loginAs('user');
        $this->post([]);
        $row = (new Tiger_Model_Comment())->byStatus(Tiger_Model_Comment::STATUS_PENDING, 5)[0];

        $svc = new Comment_Service_Comment();
        $svc->moderate(['comment_id' => $row['comment_id'], 'status' => Tiger_Model_Comment::STATUS_APPROVED]);

        $svc = new Comment_Service_Comment();
        $svc->list(['subject' => 'test.thing:t1']);
        $comment = ((array) $svc->getResponse()->data)['comments'][0];

        $this->assertArrayNotHasKey('author_email', $comment);
        $this->assertArrayNotHasKey('ip', $comment);
        $this->assertArrayNotHasKey('user_agent', $comment);
    }

    #[Test]
    public function deleting_a_comment_updates_the_rollup(): void
    {
        $this->loginAs('user');
        $this->post(['rating' => 5]);
        $row = (new Tiger_Model_Comment())->byStatus(Tiger_Model_Comment::STATUS_PENDING, 5)[0];

        $svc = new Comment_Service_Comment();
        $svc->moderate(['comment_id' => $row['comment_id'], 'status' => Tiger_Model_Comment::STATUS_APPROVED]);
        $this->assertSame(1, (new Tiger_Model_CommentAggregate())->forSubject('test.thing', 't1')['rating_count']);

        $svc = new Comment_Service_Comment();
        $svc->delete(['comment_id' => $row['comment_id']]);

        $agg = (new Tiger_Model_CommentAggregate())->forSubject('test.thing', 't1');
        $this->assertSame(0, $agg['rating_count'], 'the rollup can never outlive the thread it summarizes');
        $this->assertSame(0.0, $agg['rating_avg']);
    }
}
