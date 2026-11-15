<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Comment;

use Comment_Service_Comment;
use Comment_Service_Subjects;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Comment;
use Tiger_Model_Config;
use Tiger_Model_Org;
use Tiger_Model_Page;
use Tiger_Uuid;
use Zend_Config;
use Zend_Registry;

/**
 * TIGER-511 — the comment READ gate and the subject visibility gate.
 *
 * 1. `_mayRead()` read a `Tiger_Acl` registry key that is never set (the ACL is registered as
 *    `Zend_Acl`) and answered TRUE when it was missing — so a subject's ACL resource never blocked
 *    anyone. It now asks the one primitive and fails closed.
 * 2. The built-in `page` / `blog.post` resolvers reported only `exists`, so a thread could be read or
 *    posted on a DRAFT, or on ANOTHER ORG's page, by anyone holding its id. They now report `visible`:
 *    published, schedule arrived, and owned by the site's org or the shared tier.
 */
#[CoversClass(Comment_Service_Comment::class)]
#[CoversClass(Comment_Service_Subjects::class)]
#[CoversClass(Tiger_Comment::class)]
final class CommentSubjectGateTest extends IntegrationTestCase
{
    private $configBefore = null;
    private $siteOrgBefore = null;
    private string $siteOrg = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->configBefore = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        Zend_Registry::set('tiger.auth.stateless', true);
        \Tiger_Comment_Spam::reset();
        \Tiger_Comment_Spam::setTransport(null);
        Tiger_Comment::reset();

        (new Tiger_Model_Config())->set('global', '', Tiger_Comment::CONFIG_ENABLED, '1');
        $base = $this->configBefore ? $this->configBefore->toArray() : [];
        $cfg  = new Zend_Config($base, true);
        $cfg->merge(new Zend_Config(['tiger' => ['comment' => ['enabled' => '1', 'allow_guests' => '1']]], true));
        Zend_Registry::set('Zend_Config', $cfg);

        $prop = new ReflectionProperty(Tiger_Model_Org::class, '_siteOrgId');
        $this->siteOrgBefore = $prop->getValue();
        $this->siteOrg = Tiger_Uuid::v7();
        Tiger_Model_Org::setSiteOrgId($this->siteOrg);

        Tiger_Comment::registerSubject([
            'key'       => 'page',
            'label'     => 'Page',
            'resolve'   => [Comment_Service_Subjects::class, 'page'],
            'resource'  => 'PageController',
            'ratings'   => false,
            'threading' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        (new ReflectionProperty(Tiger_Model_Org::class, '_siteOrgId'))->setValue(null, $this->siteOrgBefore);
        $reg = Zend_Registry::getInstance();
        if ($reg->offsetExists('tiger.auth.stateless')) { $reg->offsetUnset('tiger.auth.stateless'); }
        Tiger_Comment::reset();
        \Tiger_Comment_Spam::reset();
        if ($this->configBefore !== null) {
            Zend_Registry::set('Zend_Config', $this->configBefore);
        } elseif ($reg->offsetExists('Zend_Config')) {
            $reg->offsetUnset('Zend_Config');
        }
        parent::tearDown();
    }

    private function page(array $overrides): string
    {
        return (new Tiger_Model_Page())->insert(array_merge([
            'type'         => Tiger_Model_Page::TYPE_PAGE,
            'org_id'       => $this->siteOrg,
            'locale'       => 'en',
            'slug'         => 'p-' . bin2hex(random_bytes(4)),
            'page_key'     => 'k-' . bin2hex(random_bytes(4)),
            'title'        => 'A page',
            'body'         => '',
            'format'       => Tiger_Model_Page::FORMAT_HTML,
            'status'       => Tiger_Model_Page::STATUS_PUBLISHED,
            'published_at' => '2023-01-01 00:00:00',
        ], $overrides));
    }

    private function listThread(string $subject): object
    {
        $svc = new Comment_Service_Comment();
        $svc->list(['subject' => $subject]);
        return $svc->getResponse();
    }

    private function postTo(string $subject): object
    {
        $svc = new Comment_Service_Comment();
        $svc->post(['subject' => $subject, 'body' => 'Hello there', '_t' => time() - 10]);
        return $svc->getResponse();
    }

    #[Test]
    public function the_subject_acl_resource_actually_gates_reading(): void
    {
        // A subject whose READ resource is admin-only: a guest must be refused — before the fix the gate
        // read a registry key that never exists and waved everyone through.
        Tiger_Comment::registerSubject([
            'key'      => 'staff.note',
            'label'    => 'Staff note',
            'resolve'  => static fn ($id) => ['title' => 'Note', 'url' => '/n', 'exists' => true],
            'resource' => 'Comment_AdminController',
        ]);

        $this->login('anon', '', 'guest');
        $res = $this->listThread('staff.note:1');
        $this->assertSame(0, (int) $res->result, 'a guest cannot read a thread on an admin-only subject');
        $this->assertStringContainsString('not_allowed', json_encode($res->messages));

        $this->loginAs('admin');
        $this->assertSame(1, (int) $this->listThread('staff.note:1')->result, 'an admin can');
    }

    #[Test]
    public function the_read_gate_fails_closed_with_no_acl_registered(): void
    {
        $this->login('anon', '', 'guest');
        $acl = Zend_Registry::get('Zend_Acl');
        Zend_Registry::getInstance()->offsetUnset('Zend_Acl');
        try {
            $this->assertSame(0, (int) $this->listThread('page:' . $this->page([]))->result, 'no ACL → no thread');
        } finally {
            Zend_Registry::set('Zend_Acl', $acl);   // never leave the process without its ACL
        }
    }

    #[Test]
    public function a_published_page_on_this_site_is_readable(): void
    {
        $this->login('anon', '', 'guest');
        $res = $this->listThread('page:' . $this->page([]));
        $this->assertSame(1, (int) $res->result, json_encode($res->messages));
    }

    #[Test]
    public function another_orgs_page_is_refused_for_thread_and_post(): void
    {
        $foreign = $this->page(['org_id' => Tiger_Uuid::v7()]);
        $this->login('anon', '', 'guest');

        $this->assertSame(0, (int) $this->listThread('page:' . $foreign)->result, 'no thread on another org\'s page');
        $this->assertSame(0, (int) $this->postTo('page:' . $foreign)->result, 'no post on another org\'s page');
        $this->assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM comment WHERE subject_id = ?', [$foreign]));
    }

    #[Test]
    public function an_unpublished_page_is_refused_for_thread_and_post(): void
    {
        $draft  = $this->page(['status' => Tiger_Model_Page::STATUS_DRAFT]);
        $future = $this->page(['published_at' => '2099-01-01 00:00:00']);
        $this->login('anon', '', 'guest');

        foreach (['draft' => $draft, 'scheduled' => $future] as $what => $id) {
            $this->assertSame(0, (int) $this->listThread('page:' . $id)->result, "no thread on a {$what} page");
            $this->assertSame(0, (int) $this->postTo('page:' . $id)->result, "no post on a {$what} page");
        }
        $this->assertSame(0, (int) $this->db->fetchOne('SELECT COUNT(*) FROM comment WHERE subject_id IN (?, ?)', [$draft, $future]));
    }

    #[Test]
    public function a_shared_tier_page_is_in_every_sites_scope(): void
    {
        $this->login('anon', '', 'guest');
        $this->assertSame(1, (int) $this->listThread('page:' . $this->page(['org_id' => '']))->result);
    }

    #[Test]
    public function a_third_party_resolver_without_visible_follows_exists(): void
    {
        $out = Tiger_Comment::resolve('page', 'no-such-page');
        $this->assertFalse($out['visible'], 'a missing subject is never visible');

        Tiger_Comment::registerSubject([
            'key'     => 'legacy.thing',
            'label'   => 'Legacy',
            'resolve' => static fn ($id) => ['title' => 'x', 'url' => '/x', 'exists' => true],
        ]);
        $this->assertTrue(Tiger_Comment::resolve('legacy.thing', '1')['visible'], 'no draft/tenant concept → visible when it exists');
    }
}
