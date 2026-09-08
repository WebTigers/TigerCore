<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Agent_Forge;
use Tiger_Ajax_ServiceFactory;

/**
 * Read-vs-write classification for the /api gateway (TIGER-70).
 *
 * The endpoint is deliberately verb-agnostic for READS, but that also let a state-changing call ride a
 * plain GET — the one shape SameSite=Lax does NOT stop, since Lax blocks cross-site POST while still
 * sending cookies on a top-level GET navigation. Services that validate a Tiger_Form get its CSRF
 * token; ones that do not had nothing.
 *
 * Classification is fail-CLOSED on purpose: a method nobody thought to classify is a write, so a newly
 * added service method is protected by default rather than by someone remembering.
 */
#[CoversClass(Tiger_Ajax_ServiceFactory::class)]
final class ApiMutationMethodTest extends UnitTestCase
{
    #[Test]
    public function known_read_verbs_are_not_mutations(): void
    {
        foreach (['get', 'list', 'datatable', 'search', 'view', 'count', 'preview'] as $verb) {
            $this->assertFalse(Tiger_Ajax_ServiceFactory::isMutation($verb), "$verb reads");
        }
    }

    #[Test]
    public function writes_are_mutations(): void
    {
        foreach (['save', 'delete', 'create', 'update', 'revoke', 'install', 'publish'] as $verb) {
            $this->assertTrue(Tiger_Ajax_ServiceFactory::isMutation($verb), "$verb writes");
        }
    }

    #[Test]
    public function an_unknown_verb_is_treated_as_a_write(): void
    {
        // The load-bearing property. A method added next year that nobody classified must be guarded
        // by default — the alternative is a silent hole that opens itself.
        $this->assertTrue(Tiger_Ajax_ServiceFactory::isMutation('frobnicateEverything'));
        $this->assertTrue(Tiger_Ajax_ServiceFactory::isMutation(''));
    }

    #[Test]
    public function classification_is_case_insensitive(): void
    {
        $this->assertFalse(Tiger_Ajax_ServiceFactory::isMutation('DataTable'));
        $this->assertTrue(Tiger_Ajax_ServiceFactory::isMutation('SAVE'));
    }

    #[Test]
    public function the_agent_and_the_gateway_cannot_disagree(): void
    {
        // Forge decides whether an agent action needs approval; the gateway decides whether a call may
        // arrive by GET. Two lists would eventually drift, and the drift would be a security hole.
        $this->assertSame(Tiger_Ajax_ServiceFactory::READ_VERBS, Tiger_Agent_Forge::READ_VERBS);
    }
}
