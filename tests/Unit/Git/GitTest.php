<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Git;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Git;

/**
 * Tiger_Git facade — the small pure helpers every transport leans on: semver validation, the outbound
 * host allowlist, and owner/repo slug validation (the guard that keeps a slug from being bent into a
 * path segment). Pure functions, no DB, no network.
 */
#[CoversClass(Tiger_Git::class)]
final class GitTest extends UnitTestCase
{
    #[Test]
    public function is_semver_accepts_plain_and_v_prefixed_and_prerelease(): void
    {
        $this->assertTrue(Tiger_Git::isSemver('1.0.0'));
        $this->assertTrue(Tiger_Git::isSemver('v2.3.1'));
        $this->assertTrue(Tiger_Git::isSemver('1.0.0-beta.1'));
        $this->assertTrue(Tiger_Git::isSemver('v10.20.30+build.5'));
    }

    #[Test]
    public function is_semver_rejects_junk_and_partial_versions(): void
    {
        $this->assertFalse(Tiger_Git::isSemver('1.0'));
        $this->assertFalse(Tiger_Git::isSemver('latest'));
        $this->assertFalse(Tiger_Git::isSemver('01.0.0'));   // leading zero
        $this->assertFalse(Tiger_Git::isSemver(''));
    }

    #[Test]
    public function normalize_version_strips_only_a_leading_v(): void
    {
        $this->assertSame('1.2.3', Tiger_Git::normalizeVersion('v1.2.3'));
        $this->assertSame('1.2.3', Tiger_Git::normalizeVersion('1.2.3'));
        $this->assertSame('1.2.3-v2', Tiger_Git::normalizeVersion('v1.2.3-v2')); // only the leading v
    }

    #[Test]
    public function allowed_host_is_the_github_set_only(): void
    {
        $this->assertTrue(Tiger_Git::isAllowedHost('github.com'));
        $this->assertTrue(Tiger_Git::isAllowedHost('API.GITHUB.COM')); // case-insensitive
        $this->assertTrue(Tiger_Git::isAllowedHost('codeload.github.com'));
        $this->assertFalse(Tiger_Git::isAllowedHost('evil.example.com'));
        $this->assertFalse(Tiger_Git::isAllowedHost('github.com.evil.com'));
    }

    #[Test]
    public function repo_slug_validates_or_refuses(): void
    {
        $this->assertSame(['WebTigers', 'tiger-core'], Tiger_Git::repoSlug('WebTigers', 'tiger-core'));
        $this->assertSame(['a_b.c', 'd-e.f'], Tiger_Git::repoSlug('a_b.c', 'd-e.f'));
        $this->assertNull(Tiger_Git::repoSlug('owner/evil', 'repo'));   // a slash can't ride in
        $this->assertNull(Tiger_Git::repoSlug('..', 'repo'));
        $this->assertNull(Tiger_Git::repoSlug('owner', ''));
    }
}
