<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Newsletter;

use Newsletter_Model_Subscriber;
use Newsletter_Service_Audience;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Audience;

/**
 * Newsletter_Service_Audience — the subscribers exposed as an email audience segment. Proves the
 * segment/count, that ONLY confirmed opt-ins resolve (pending + unsubscribed are excluded), and that
 * the provider is reachable through the core Tiger_Audience registry a consumer (TigerList) reads.
 */
#[CoversClass(Newsletter_Service_Audience::class)]
final class AudienceTest extends IntegrationTestCase
{
    protected function tearDown(): void
    {
        Tiger_Audience::_reset();
        parent::tearDown();
    }

    private function seed(string $email, string $status, ?string $name = null): void
    {
        (new Newsletter_Model_Subscriber())->insert([
            'org_id' => '',
            'email'  => $email,
            'name'   => $name,
            'status' => $status,
            'token'  => \Tiger_Uuid::v4(),
        ]);
    }

    private function seedFixture(): void
    {
        $this->seed('confirmed-a@example.com', Newsletter_Model_Subscriber::STATUS_CONFIRMED, 'Ada Lovelace');
        $this->seed('confirmed-b@example.com', Newsletter_Model_Subscriber::STATUS_CONFIRMED);
        $this->seed('pending@example.com',      Newsletter_Model_Subscriber::STATUS_PENDING);
        $this->seed('gone@example.com',         Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED);
    }

    #[Test]
    public function the_segment_counts_only_confirmed_subscribers(): void
    {
        $this->seedFixture();

        $segs = Newsletter_Service_Audience::listSegments('');
        $this->assertCount(1, $segs);
        $this->assertSame(Newsletter_Service_Audience::SEGMENT, $segs[0]['key']);
        $this->assertSame(2, $segs[0]['count'], 'pending and unsubscribed are not part of the audience');
    }

    #[Test]
    public function resolving_returns_only_confirmed_opt_ins(): void
    {
        $this->seedFixture();

        $members = Newsletter_Service_Audience::resolveMembers('', Newsletter_Service_Audience::SEGMENT);
        $emails  = array_column($members, 'email');
        sort($emails);

        $this->assertSame(['confirmed-a@example.com', 'confirmed-b@example.com'], $emails);
        $this->assertSame('Ada Lovelace', $members[array_search('confirmed-a@example.com', array_column($members, 'email'), true)]['name']);
    }

    #[Test]
    public function an_unknown_segment_key_resolves_to_nothing(): void
    {
        $this->seedFixture();

        $this->assertSame([], Newsletter_Service_Audience::resolveMembers('', 'newsletter:bogus'));
    }

    /** The provider must be reachable through the registry a consumer (TigerList) actually reads. */
    #[Test]
    public function the_provider_is_reachable_through_the_audience_registry(): void
    {
        $this->seedFixture();

        Tiger_Audience::register('newsletter', [
            'label'    => 'Newsletter',
            'segments' => [Newsletter_Service_Audience::class, 'listSegments'],
            'resolve'  => [Newsletter_Service_Audience::class, 'resolveMembers'],
        ]);

        $flat = Tiger_Audience::segments('');
        $keys = array_column($flat, 'key');
        $this->assertContains(Newsletter_Service_Audience::SEGMENT, $keys, 'the segment shows up in the flattened registry');

        $resolved = Tiger_Audience::resolve('newsletter', '', Newsletter_Service_Audience::SEGMENT);
        $this->assertCount(2, $resolved, 'the consumer resolves the segment to confirmed opt-ins only');
    }
}
