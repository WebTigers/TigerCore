<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Model;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Model_SmsConsent;
use Tiger_Model_User;

/**
 * Tiger_Model_SmsConsent — the TCPA/10DLC consent + suppression ledger (TIGER-376).
 *
 * Covers: record() upserts one row per user (a re-consent revives, never duplicates), the consented
 * scopes + audit snapshot persist, STOP (optOutByPhone) flips isSuppressed and START (optInByPhone)
 * clears it, and removeForUser() soft-deletes.
 */
#[CoversClass(Tiger_Model_SmsConsent::class)]
final class SmsConsentTest extends IntegrationTestCase
{
    private string $userId;
    private string $phone = '+14075550199';

    protected function setUp(): void
    {
        parent::setUp();
        $this->userId = (new Tiger_Model_User())->insert(['email' => 'consent@w376.test', 'status' => 'active']);
    }

    #[Test]
    public function record_writes_the_consent_with_scopes_and_audit_fields(): void
    {
        $model = new Tiger_Model_SmsConsent();
        $id = $model->record(
            $this->userId,
            $this->phone,
            [Tiger_Model_SmsConsent::SCOPE_TWOFA, Tiger_Model_SmsConsent::SCOPE_ALERTS],
            'I agree to texts.',
            '203.0.113.7'
        );
        $this->assertNotSame('', $id);

        $row = $model->forUser($this->userId);
        $this->assertNotNull($row);
        $this->assertSame($this->phone, (string) $row->phone);
        $this->assertSame('twofa,alerts', (string) $row->scopes);
        $this->assertSame('203.0.113.7', (string) $row->consent_ip);
        $this->assertNotNull($row->consent_at);
        $this->assertNull($row->opt_out_at);
    }

    #[Test]
    public function re_consent_revives_the_same_row_rather_than_duplicating(): void
    {
        $model = new Tiger_Model_SmsConsent();
        $first = $model->record($this->userId, $this->phone, [Tiger_Model_SmsConsent::SCOPE_TWOFA], 'v1', null);
        $model->removeForUser($this->userId);                 // soft-delete
        $second = $model->record($this->userId, $this->phone, [Tiger_Model_SmsConsent::SCOPE_ALERTS], 'v2', null);

        $this->assertSame($first, $second, 'the one row is revived + overwritten (the (user_id) unique holds)');
        $row = $model->forUser($this->userId);
        $this->assertSame('alerts', (string) $row->scopes);
        $this->assertSame(0, (int) $row->deleted);
    }

    #[Test]
    public function stop_suppresses_and_start_restores_by_phone(): void
    {
        $model = new Tiger_Model_SmsConsent();
        $model->record($this->userId, $this->phone, [Tiger_Model_SmsConsent::SCOPE_ALERTS], 't', null);
        $this->assertFalse($model->isSuppressed($this->phone));

        $this->assertSame(1, $model->optOutByPhone($this->phone));
        $this->assertTrue($model->isSuppressed($this->phone), 'STOP suppresses the number');

        $this->assertSame(1, $model->optInByPhone($this->phone));
        $this->assertFalse($model->isSuppressed($this->phone), 'START lifts the suppression');
    }

    #[Test]
    public function a_number_with_no_record_is_not_suppressed(): void
    {
        $this->assertFalse((new Tiger_Model_SmsConsent())->isSuppressed('+14075550123'));
    }

    #[Test]
    public function remove_soft_deletes_the_record(): void
    {
        $model = new Tiger_Model_SmsConsent();
        $model->record($this->userId, $this->phone, [Tiger_Model_SmsConsent::SCOPE_TWOFA], 't', null);
        $this->assertTrue($model->removeForUser($this->userId));
        $this->assertNull($model->forUser($this->userId));
        $this->assertFalse($model->removeForUser($this->userId), 'removing again is a no-op');
    }
}
