<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Model_AgentMessage;
use Tiger_Model_AgentConversation;
use Tiger_Model_Media;
use Tiger_Model_Page;
use Tiger_Uuid;

/**
 * The finders extracted from five services that had been building their own predicates (TIGER-75).
 *
 * These pin the BEHAVIOUR the services relied on, so the query lives in one place and a caller can't
 * quietly re-derive it: the tenant boundary on a checksum lookup, the deliberate absence of `body` in a
 * summary read, the type filter, the GLOBAL (cross-locale, cross-tenant) reach of the page_key
 * uniqueness probe, and picking the assistant row out of a run's messages.
 */
#[CoversClass(Tiger_Model_Media::class)]
#[CoversClass(Tiger_Model_Page::class)]
#[CoversClass(Tiger_Model_AgentMessage::class)]
final class ServiceFinderTest extends IntegrationTestCase
{
    /** Seed a media row (stays in the harness txn). */
    private function seedMedia(array $overrides = []): string
    {
        return (new Tiger_Model_Media())->insert(array_merge([
            'storage_key' => 'images/' . bin2hex(random_bytes(6)) . '.jpg',
            'filename'    => 'file.jpg',
        ], $overrides));
    }

    /** Seed a page row (stays in the harness txn). */
    private function seedPage(array $overrides = []): string
    {
        return (new Tiger_Model_Page())->insert(array_merge([
            'type'   => Tiger_Model_Page::TYPE_PAGE,
            'locale' => 'en',
            'title'  => 'Seed',
            'body'   => '',
            'format' => Tiger_Model_Page::FORMAT_HTML,
            'status' => Tiger_Model_Page::STATUS_DRAFT,
        ], $overrides));
    }

    // ----- Tiger_Model_Media::getByChecksum ---------------------------------------------------------

    #[Test]
    public function a_checksum_lookup_is_scoped_to_the_asking_org(): void
    {
        $sum   = hash('sha256', 'identical-bytes');
        $orgA  = Tiger_Uuid::v7();
        $orgB  = Tiger_Uuid::v7();
        $mine  = $this->seedMedia(['org_id' => $orgA, 'checksum' => $sum, 'filename' => 'mine.jpg']);
        $this->seedMedia(['org_id' => $orgB, 'checksum' => $sum, 'filename' => 'theirs.jpg']);

        $media = new Tiger_Model_Media();

        // The positive control: my org's copy comes back...
        $hit = $media->getByChecksum($sum, $orgA);
        $this->assertNotNull($hit, 'my org\'s copy of these bytes is found');
        $this->assertSame($mine, $hit->media_id, 'and it is MY row, not the other org\'s');

        // ...and the same bytes in another org are invisible to a third org. This is the assertion that
        // matters: the dedupe shortcut must never hand one tenant a row belonging to another.
        $this->assertNull(
            $media->getByChecksum($sum, Tiger_Uuid::v7()),
            'the same bytes in someone else\'s library are not my library\'s hit'
        );
    }

    #[Test]
    public function a_checksum_lookup_ignores_unknown_blank_and_deleted(): void
    {
        $media = new Tiger_Model_Media();
        $org   = Tiger_Uuid::v7();
        $sum   = hash('sha256', 'gone');
        $id    = $this->seedMedia(['org_id' => $org, 'checksum' => $sum]);

        $this->assertNotNull($media->getByChecksum($sum, $org), 'control: it is found while active');

        $media->softDelete(['media_id = ?' => $id]);
        $this->assertNull($media->getByChecksum($sum, $org), 'a soft-deleted row is not a dedupe hit');
        $this->assertNull($media->getByChecksum(hash('sha256', 'never'), $org), 'an unknown checksum is null');
        $this->assertNull($media->getByChecksum('', $org), 'a blank checksum never matches a row');
    }

    // ----- Tiger_Model_Page::getSummaries -----------------------------------------------------------

    #[Test]
    public function summaries_carry_no_body_and_only_the_asked_for_type(): void
    {
        $this->seedPage(['slug' => 'zulu',  'title' => 'Zulu',  'body' => 'HEAVY BODY CONTENT']);
        $this->seedPage(['slug' => 'alpha', 'title' => 'Alpha', 'body' => 'HEAVY BODY CONTENT']);
        $this->seedPage(['slug' => 'a-layout', 'type' => Tiger_Model_Page::TYPE_LAYOUT]);

        $rows = (new Tiger_Model_Page())->getSummaries(Tiger_Model_Page::TYPE_PAGE);
        $bySlug = [];
        foreach ($rows as $r) { $bySlug[$r['slug']] = $r; }

        $this->assertArrayHasKey('alpha', $bySlug, 'pages are returned');
        $this->assertArrayHasKey('zulu', $bySlug);
        $this->assertArrayNotHasKey('a-layout', $bySlug, 'a layout is not a page');

        // The reason this finder selects columns instead of returning rows: a scanner walking every page
        // must not drag every page BODY into memory.
        $this->assertArrayNotHasKey('body', $bySlug['alpha'], 'body is deliberately not selected');
        foreach (['page_id', 'title', 'slug', 'format', 'locale'] as $col) {
            $this->assertArrayHasKey($col, $bySlug['alpha'], "the summary carries $col");
        }

        $slugs = array_column($rows, 'slug');
        $sorted = $slugs; sort($sorted);
        $this->assertSame($sorted, $slugs, 'summaries come back ordered by slug');
    }

    #[Test]
    public function summaries_exclude_deleted_pages(): void
    {
        $model = new Tiger_Model_Page();
        $id    = $this->seedPage(['slug' => 'doomed-' . bin2hex(random_bytes(3))]);
        $slug  = $model->findById($id)->slug;

        $this->assertContains($slug, array_column($model->getSummaries(), 'slug'), 'control: listed while active');
        $model->softDelete(['page_id = ?' => $id]);
        $this->assertNotContains($slug, array_column($model->getSummaries(), 'slug'), 'a deleted page drops out');
    }

    // ----- Tiger_Model_Page::getByTypes -------------------------------------------------------------

    #[Test]
    public function get_by_types_filters_to_the_requested_types(): void
    {
        $model   = new Tiger_Model_Page();
        $partial = $this->seedPage(['type' => Tiger_Model_Page::TYPE_PARTIAL, 'slug' => 'p-' . bin2hex(random_bytes(3))]);
        $block   = $this->seedPage(['type' => Tiger_Model_Page::TYPE_BLOCK,   'slug' => 'b-' . bin2hex(random_bytes(3))]);

        $ids = [];
        foreach ($model->getByTypes([Tiger_Model_Page::TYPE_PARTIAL]) as $r) { $ids[] = $r->page_id; }

        $this->assertContains($partial, $ids, 'the requested type is returned');
        $this->assertNotContains($block, $ids, 'a type that was not asked for is excluded');
    }

    #[Test]
    public function get_by_types_with_no_types_returns_nothing_rather_than_everything(): void
    {
        $this->seedPage(['slug' => 'present-' . bin2hex(random_bytes(3))]);

        // The guard that matters: an empty filter must not degenerate into "SELECT everything", which is
        // how an empty-input bug turns into a full-table read.
        $this->assertCount(0, (new Tiger_Model_Page())->getByTypes([]), 'no types means no rows');
        $this->assertCount(0, (new Tiger_Model_Page())->getByTypes(['', '  ']), 'blank type names are not types');
    }

    // ----- Tiger_Model_Page::getByPageKey -----------------------------------------------------------

    #[Test]
    public function the_page_key_probe_reaches_across_locale_and_tenant(): void
    {
        $model = new Tiger_Model_Page();
        $key   = 'shared-key-' . bin2hex(random_bytes(4));

        $this->assertNull($model->getByPageKey($key), 'control: the key is free before anyone takes it');

        // Taken by a row in ANOTHER locale and ANOTHER org. A key minted for uniqueness must still see it,
        // otherwise two scopes mint the same handle and collide later.
        $taken = $this->seedPage(['page_key' => $key, 'locale' => 'fr', 'org_id' => Tiger_Uuid::v7()]);

        $hit = $model->getByPageKey($key);
        $this->assertNotNull($hit, 'a key held in another locale/org still counts as taken');
        $this->assertSame($taken, $hit->page_id);

        $model->softDelete(['page_id = ?' => $taken]);
        $this->assertNull($model->getByPageKey($key), 'once deleted the key is free again');
    }

    // ----- Tiger_Model_AgentMessage::getAssistantForRun ---------------------------------------------

    #[Test]
    public function the_run_lookup_returns_the_assistant_row_not_the_user_turn(): void
    {
        $msg  = new Tiger_Model_AgentMessage();
        $cid  = (new Tiger_Model_AgentConversation())->start(Tiger_Uuid::v7(), Tiger_Uuid::v7(), 'T', 'anthropic', 'claude');
        $run  = Tiger_Uuid::v7();

        $msg->append($cid, Tiger_Model_AgentMessage::ROLE_USER, 'do the thing', null, $run);
        $asst = $msg->append($cid, Tiger_Model_AgentMessage::ROLE_ASSISTANT, 'done', ['actions' => []], $run);

        $row = $msg->getAssistantForRun($run);
        $this->assertNotNull($row, 'the run has an assistant message');
        $this->assertSame($asst, $row->message_id, 'it is the assistant row, not the user turn in the same run');

        $this->assertNull($msg->getAssistantForRun(Tiger_Uuid::v7()), 'an unknown run has none');
    }
}
