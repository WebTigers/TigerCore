<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_MicroId;

/**
 * Tiger_MicroId — the human-friendly receipt/order/ticket number codec (micro timestamp → base36, grouped).
 * The load-bearing contracts: a lossless timestamp round-trip, a deterministic encoding for a fixed stamp,
 * configurable dash grouping that never changes the decoded value, and a separator-/case-tolerant decode.
 */
#[CoversClass(Tiger_MicroId::class)]
final class MicroIdTest extends UnitTestCase
{
    private const TS = '1760000000.699022';   // a fixed micro timestamp for deterministic assertions

    #[Test]
    public function a_fixed_timestamp_encodes_deterministically(): void
    {
        // Pinned so a codec regression is caught (not just "it round-trips").
        $this->assertSame('6E9K6U8VBH', Tiger_MicroId::getReceiptNumberFromTimestamp(self::TS, 0));
    }

    #[Test]
    public function the_timestamp_round_trips_losslessly(): void
    {
        $receipt = Tiger_MicroId::getReceiptNumberFromTimestamp(self::TS);
        $this->assertSame(self::TS, Tiger_MicroId::getTimestampFromReceiptNumber($receipt));
    }

    #[Test]
    public function dash_grouping_is_configurable_and_never_changes_the_value(): void
    {
        $this->assertSame('6E9K6U8VBH',       Tiger_MicroId::getReceiptNumberFromTimestamp(self::TS, 0), 'no dashes');
        $this->assertSame('6E-9K-6U-8V-BH',   Tiger_MicroId::getReceiptNumberFromTimestamp(self::TS, 2), 'every 2');
        $this->assertSame('6E9-K6U-8VB-H',    Tiger_MicroId::getReceiptNumberFromTimestamp(self::TS, 3), 'every 3');
        $this->assertSame('6E9K-6U8V-BH',     Tiger_MicroId::getReceiptNumberFromTimestamp(self::TS, 4), 'every 4');

        // Whatever the grouping, it decodes to the same timestamp.
        foreach ([0, 2, 3, 4] as $g) {
            $this->assertSame(self::TS, Tiger_MicroId::getTimestampFromReceiptNumber(
                Tiger_MicroId::getReceiptNumberFromTimestamp(self::TS, $g)), "group $g must decode back");
        }
    }

    #[Test]
    public function group_defaults_to_two(): void
    {
        $this->assertSame(
            Tiger_MicroId::getReceiptNumberFromTimestamp(self::TS, 2),
            Tiger_MicroId::getReceiptNumberFromTimestamp(self::TS),
            'the default grouping is every 2 characters'
        );
    }

    #[Test]
    public function decode_is_separator_and_case_insensitive(): void
    {
        // The same code with its dashes stripped, or lower-cased, decodes identically.
        $this->assertSame(self::TS, Tiger_MicroId::getTimestampFromReceiptNumber('6E9K6U8VBH'));
        $this->assertSame(self::TS, Tiger_MicroId::getTimestampFromReceiptNumber('6e-9k-6u-8v-bh'));
        $this->assertSame(self::TS, Tiger_MicroId::getTimestampFromReceiptNumber('6E9K 6U8V BH'));
    }

    #[Test]
    public function the_plain_integer_codec_round_trips(): void
    {
        $this->assertSame('9V3I12', Tiger_MicroId::encode('123456789'));
        $this->assertSame('123456789', Tiger_MicroId::decode('9V3I12'));
    }

    #[Test]
    public function get_receipt_mints_a_grouped_code_for_now(): void
    {
        $before  = microtime(true);
        $receipt = Tiger_MicroId::getReceipt();
        $after   = microtime(true);

        $this->assertMatchesRegularExpression('/^[0-9A-Z]{2}(-[0-9A-Z]{1,2})+$/', $receipt, 'default group-of-2 shape');

        $ts = (float) Tiger_MicroId::getTimestampFromReceiptNumber($receipt);
        $this->assertGreaterThanOrEqual($before - 1.0, $ts, 'decodes to about now');
        $this->assertLessThanOrEqual($after + 1.0, $ts);
    }
}
