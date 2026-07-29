<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Register;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Register_Service_Status;

/**
 * Register_Service_Status — a read-only view of registration progress that GATES NOTHING. The load-bearing
 * property with no readable state is the fail-safe default: everything reads as "nothing done", so the widget
 * shows the register step and tsid() is empty. No DB / no network.
 */
#[CoversClass(Register_Service_Status::class)]
final class StatusTest extends UnitTestCase
{
    #[Test]
    public function it_reads_nothing_done_when_state_is_unreadable(): void
    {
        $this->assertFalse(Register_Service_Status::hasStarted());
        $this->assertFalse(Register_Service_Status::isDomainVerified());
        $this->assertFalse(Register_Service_Status::isEmailVerified());
        $this->assertFalse(Register_Service_Status::isVerified());
        $this->assertSame('', Register_Service_Status::tsid());
    }

    #[Test]
    public function state_snapshot_has_the_expected_shape(): void
    {
        $s = Register_Service_Status::state();
        foreach (['started', 'domain_verified', 'email_verified', 'verified', 'domain', 'email', 'tsid'] as $k) {
            $this->assertArrayHasKey($k, $s);
        }
        $this->assertFalse($s['verified']);
    }
}
