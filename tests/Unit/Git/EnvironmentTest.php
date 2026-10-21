<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Git;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Git;
use Tiger_Git_Environment;

/**
 * Tiger_Git_Environment — the capability detector that makes shared hosting work. The decision logic
 * is tested through the outbound-probe seam (no real network): no outbound ⇒ unusable with
 * remediation; outbound ⇒ a usable transport (ApiTransport needs only outbound). The shell-vs-api
 * split depends on the host's git binary, so we assert the invariant rather than a fixed value.
 */
#[CoversClass(Tiger_Git_Environment::class)]
final class EnvironmentTest extends UnitTestCase
{
    protected function tearDown(): void
    {
        Tiger_Git_Environment::setProbe(null);   // never leak the seam into another test
        parent::tearDown();
    }

    #[Test]
    public function no_outbound_is_unusable_and_explains_why(): void
    {
        Tiger_Git_Environment::setProbe(fn (string $h) => false);
        $env = Tiger_Git_Environment::detect();

        $this->assertFalse($env['outbound']);
        $this->assertFalse($env['usable']);
        $this->assertSame('', $env['transport']);
        $this->assertNotNull($env['remediation']);
        $this->assertStringContainsString('github.com', $env['remediation']);
    }

    #[Test]
    public function outbound_yields_a_usable_transport(): void
    {
        Tiger_Git_Environment::setProbe(fn (string $h) => true);
        $env = Tiger_Git_Environment::detect();

        $this->assertTrue($env['outbound']);
        $this->assertTrue($env['usable'], 'ApiTransport needs only outbound');
        $this->assertContains($env['transport'], [Tiger_Git::TRANSPORT_SHELL, Tiger_Git::TRANSPORT_API]);
        $this->assertNull($env['remediation']);
    }

    #[Test]
    public function the_probe_refuses_a_non_allowlisted_host(): void
    {
        Tiger_Git_Environment::setProbe(fn (string $h) => true);   // even a yes-probe can't reach off-list
        $this->assertFalse(Tiger_Git_Environment::outboundReachable('evil.example.com'));
        $this->assertTrue(Tiger_Git_Environment::outboundReachable('github.com'));
    }

    #[Test]
    public function a_throwing_probe_is_treated_as_unreachable(): void
    {
        Tiger_Git_Environment::setProbe(function (string $h): bool { throw new \RuntimeException('boom'); });
        $this->assertFalse(Tiger_Git_Environment::outboundReachable('github.com'));
    }
}
