<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Newsletter;

use Newsletter_Model_Subscriber;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Zend_Config;
use Zend_Registry;

/**
 * Migration 0067 (TIGER-511) — legacy `org_id = ''` newsletter subscribers move to the install org.
 *
 * Runs the migration's own callable against seeded rows inside the harness transaction: a plain ''
 * row moves; a '' row whose address the install org already holds is NOT moved (email is unique per
 * org) — its confirmed consent is carried onto a still-pending org row, never over an unsubscribe —
 * and it is soft-deleted. A second run changes nothing.
 */
final class SubscriberOrgBackfillTest extends IntegrationTestCase
{
    private const INSTALL_ORG = 'install-org-0067';
    private $configBefore = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->configBefore = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        Zend_Registry::set('Zend_Config', new Zend_Config(['tiger' => ['site' => ['org_id' => self::INSTALL_ORG]]]));
    }

    protected function tearDown(): void
    {
        if ($this->configBefore !== null) {
            Zend_Registry::set('Zend_Config', $this->configBefore);
        } else {
            Zend_Registry::getInstance()->offsetUnset('Zend_Config');
        }
        parent::tearDown();
    }

    private function seed(string $orgId, string $email, string $status): string
    {
        return (new Newsletter_Model_Subscriber())->insert([
            'org_id'     => $orgId,
            'email'      => $email,
            'status'     => $status,
            'token'      => \Tiger_Uuid::v4(),
            'consent_at' => $status === 'confirmed' ? '2026-01-02 03:04:05' : null,
        ]);
    }

    private function runMigration(): void
    {
        $spec = require dirname(__DIR__, 3) . '/migrations/0067_newsletter_subscriber_org_backfill.php';
        foreach ($spec['up'] as $step) { $step($this->db); }
    }

    private function row(string $id): array
    {
        return $this->db->fetchRow('SELECT * FROM newsletter_subscriber WHERE newsletter_subscriber_id = ?', [$id]);
    }

    #[Test]
    public function legacy_rows_move_to_the_install_org_without_losing_consent(): void
    {
        $plain     = $this->seed('', 'plain@example.com', 'confirmed');
        $dupLegacy = $this->seed('', 'dup@example.com', 'confirmed');
        $dupOrg    = $this->seed(self::INSTALL_ORG, 'dup@example.com', 'pending');
        $optLegacy = $this->seed('', 'out@example.com', 'confirmed');
        $optOrg    = $this->seed(self::INSTALL_ORG, 'out@example.com', 'unsubscribed');
        $other     = $this->seed('some-other-org', 'other@example.com', 'confirmed');

        $this->runMigration();

        $this->assertSame(self::INSTALL_ORG, $this->row($plain)['org_id'], 'a plain legacy row is attributed');
        $this->assertSame('confirmed', $this->row($plain)['status']);

        $this->assertSame('confirmed', $this->row($dupOrg)['status'], 'confirmed consent carried onto a pending org row');
        $this->assertSame(1, (int) $this->row($dupLegacy)['deleted'], 'the duplicate legacy row is retired');

        $this->assertSame('unsubscribed', $this->row($optOrg)['status'], 'an opt-out is never overwritten');
        $this->assertSame(1, (int) $this->row($optLegacy)['deleted']);

        $this->assertSame('some-other-org', $this->row($other)['org_id'], 'rows already owned are untouched');
        $this->assertSame(0, (int) $this->db->fetchOne("SELECT COUNT(*) FROM newsletter_subscriber WHERE org_id = '' AND deleted = 0"));

        $before = $this->db->fetchAll('SELECT newsletter_subscriber_id, org_id, status, deleted FROM newsletter_subscriber ORDER BY newsletter_subscriber_id');
        $this->runMigration();
        $after  = $this->db->fetchAll('SELECT newsletter_subscriber_id, org_id, status, deleted FROM newsletter_subscriber ORDER BY newsletter_subscriber_id');
        $this->assertSame($before, $after, 'idempotent: a re-run changes nothing');
    }
}
