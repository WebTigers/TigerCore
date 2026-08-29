<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Comment;

use Comment_Service_Render;
use Comment_Service_Subjects;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Comment;
use Tiger_Model_CommentAggregate;
use Tiger_Model_Page;

/**
 * The shortcode renderers and core's own subject resolvers.
 *
 * The shape being asserted is the client/server split: the THREAD is a mount point the browser
 * fills from `/api` (so a cached CMS page never bakes in a stale thread), while `[stars]` and
 * `[rating_summary]` render server-side from one aggregate row — they're what a crawler should see
 * and they must not flash in after paint.
 */
#[CoversClass(Comment_Service_Render::class)]
#[CoversClass(Comment_Service_Subjects::class)]
#[CoversClass(Tiger_Model_CommentAggregate::class)]
final class CommentRenderTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Tiger_Comment::reset();
        Tiger_Comment::registerSubject([
            'key'     => 'test.thing',
            'label'   => 'Thing',
            'resolve' => static fn ($id) => ['title' => 'A Thing', 'url' => '/thing/' . $id, 'exists' => true],
            'ratings' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Tiger_Comment::reset();
        parent::tearDown();
    }

    #[Test]
    public function the_thread_renders_a_mount_point_not_the_comments(): void
    {
        $html = (new Comment_Service_Render())->thread('test.thing:t1');

        $this->assertStringContainsString('data-comment-subject="test.thing:t1"', $html);
        $this->assertStringContainsString('tiger-comments-list', $html);
        $this->assertStringContainsString('tiger-comments-form', $html);
    }

    #[Test]
    public function an_unregistered_subject_renders_nothing_at_all(): void
    {
        $render = new Comment_Service_Render();

        $this->assertSame('', $render->thread('not.registered:1'));
        $this->assertSame('', $render->stars('not.registered:1'));
        $this->assertSame('', $render->summary('not.registered:1'));
    }

    #[Test]
    public function a_malformed_subject_renders_nothing(): void
    {
        $this->assertSame('', (new Comment_Service_Render())->thread('no-colon-here'));
    }

    #[Test]
    public function stars_render_nothing_until_something_is_rated(): void
    {
        $this->assertSame('', (new Comment_Service_Render())->stars('test.thing:unrated'));
        $this->assertSame('', (new Comment_Service_Render())->summary('test.thing:unrated'));
    }

    #[Test]
    public function stars_and_summary_render_server_side_from_the_rollup(): void
    {
        $agg = new Tiger_Model_CommentAggregate();
        $agg->insert([
            'subject_type' => 'test.thing', 'subject_id' => 'rated',
            'comment_count' => 3, 'rating_count' => 3, 'rating_avg' => 4.33,
            'star_1' => 0, 'star_2' => 0, 'star_3' => 1, 'star_4' => 0, 'star_5' => 2,
        ]);

        $render = new Comment_Service_Render();

        $stars = $render->stars('test.thing:rated');
        $this->assertStringContainsString('aria-label="4.5 out of 5 stars', $stars, '4.33 snaps to 4.5');
        $this->assertStringContainsString('(3)', $stars);

        $summary = $render->summary('test.thing:rated');
        $this->assertStringContainsString('progress-bar', $summary, 'the histogram renders');
        $this->assertStringContainsString('aria-valuenow="67"', $summary, '2 of 3 ratings are 5-star');
    }

    /** The batch read is the whole reason the rollup table exists — one query for a grid of cards. */
    #[Test]
    public function rollups_can_be_read_in_batch(): void
    {
        $agg = new Tiger_Model_CommentAggregate();
        $agg->insert(['subject_type' => 'test.thing', 'subject_id' => 'a', 'rating_count' => 1, 'rating_avg' => 5.00]);
        $agg->insert(['subject_type' => 'test.thing', 'subject_id' => 'b', 'rating_count' => 2, 'rating_avg' => 3.50]);

        $out = $agg->forSubjects('test.thing', ['a', 'b', 'missing']);

        $this->assertCount(2, $out, 'a subject with no rollup is simply absent');
        $this->assertSame(5.0, $out['a']['rating_avg']);
        $this->assertSame(2, $out['b']['rating_count']);
    }

    #[Test]
    public function an_empty_batch_read_is_not_a_query(): void
    {
        $this->assertSame([], (new Tiger_Model_CommentAggregate())->forSubjects('test.thing', []));
    }

    #[Test]
    public function an_unknown_subject_has_the_zero_rollup(): void
    {
        $out = (new Tiger_Model_CommentAggregate())->forSubject('test.thing', 'never-seen');

        $this->assertSame(0, $out['rating_count']);
        $this->assertSame(0.0, $out['rating_avg']);
        $this->assertSame(0, $out['stars'][5]);
    }

    #[Test]
    public function the_page_resolver_finds_a_cms_page(): void
    {
        $pages = new Tiger_Model_Page();
        $id    = (string) $pages->insert([
            'page_key' => 'test-comment-page', 'slug' => 'test-comment-page',
            'title' => 'Commentable', 'type' => Tiger_Model_Page::TYPE_PAGE,
            'status' => Tiger_Model_Page::STATUS_PUBLISHED, 'format' => Tiger_Model_Page::FORMAT_HTML,
            'body' => '<p>hi</p>', 'locale' => 'en',
        ]);

        $out = Comment_Service_Subjects::page($id);

        $this->assertTrue($out['exists']);
        $this->assertSame('Commentable', $out['title']);
        $this->assertSame('/test-comment-page', $out['url']);
    }

    /** A deleted subject must resolve to "gone", not blow up the moderation queue. */
    #[Test]
    public function the_page_resolver_reports_a_missing_page_as_gone(): void
    {
        $out = Comment_Service_Subjects::page('00000000-0000-0000-0000-000000000000');

        $this->assertFalse($out['exists']);
        $this->assertSame('', $out['title']);
    }

    #[Test]
    public function the_blog_resolver_is_safe_when_the_module_is_absent(): void
    {
        $out = Comment_Service_Subjects::blogPost('whatever');

        $this->assertIsArray($out);
        $this->assertArrayHasKey('exists', $out);
    }
}
