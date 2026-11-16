<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Agent;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Agent_Tools;

/**
 * Tiger_Agent_Tools::catalog — the model's tool catalog is not a hand-kept list: it IS the live,
 * role-filtered /api surface, discovered by reflection (Tiger_OpenApi_Generator) and filtered
 * through the REAL ACL. This drives the reflection + ACL-filter path that the pure prompt-assembly
 * unit test can't reach (that one runs with no ACL, so catalog() short-circuits to empty).
 */
#[CoversClass(Tiger_Agent_Tools::class)]
final class ToolsCatalogTest extends IntegrationTestCase
{
    #[Test]
    public function catalogReflectsTheRoleFilteredApiSurface(): void
    {
        $this->loginAs('developer');
        $catalog = Tiger_Agent_Tools::catalog('developer');

        $this->assertIsArray($catalog);
        // The agent never advertises its own plumbing service surface.
        $this->assertArrayNotHasKey('agent', $catalog);

        // Each group is a list of {service, method, summary} rows.
        foreach ($catalog as $module => $ops) {
            $this->assertIsString($module);
            $this->assertNotEmpty($ops);
            $this->assertArrayHasKey('service', $ops[0]);
            $this->assertArrayHasKey('method', $ops[0]);
            $this->assertArrayHasKey('summary', $ops[0]);
        }
    }

    #[Test]
    public function aModuleScopeIsAppliedBeforeTheCapSoLateModulesAreNotDropped(): void
    {
        // An MCP token scoped to `search` listed NO search tools: the module filter ran after the agent's
        // 160-op prompt cap, and the alphabetical walk stopped before reaching it (2026-11-16).
        $this->loginAs('developer');
        $all    = Tiger_Agent_Tools::catalog('developer', null, null);
        $capped = Tiger_Agent_Tools::catalog('developer');
        $this->assertGreaterThan(Tiger_Agent_Tools::MAX_OPERATIONS, $this->flatCount($all), 'the uncapped surface exceeds the cap');
        $this->assertLessThanOrEqual(Tiger_Agent_Tools::MAX_OPERATIONS, $this->flatCount($capped), 'the prompt catalog stays capped');

        $last = array_key_last($all);   // a module sorting past the cutoff
        $this->assertArrayNotHasKey($last, $capped, 'the fixture really needs a module the cap cuts off');
        $scoped = Tiger_Agent_Tools::catalog('developer', [$last]);
        $this->assertSame([$last], array_keys($scoped), 'a scoped catalog returns exactly that module, cap or not');
        $this->assertNotEmpty($scoped[$last]);
    }

    #[Test]
    public function aGuestSeesAtMostWhatADeveloperSees(): void
    {
        $this->loginAs('developer');
        $devCount = $this->flatCount(Tiger_Agent_Tools::catalog('developer'));

        $this->loginAs('guest');
        $guestCount = $this->flatCount(Tiger_Agent_Tools::catalog('guest'));

        // Deny-by-default: the guest's callable surface is never larger than the developer's.
        $this->assertLessThanOrEqual($devCount, $guestCount);
    }

    #[Test]
    public function aGuestSeesOnlyThePublicCommentMethodsNotTheAdminOnes(): void
    {
        // TIGER-142: the comment service used to be a BLANKET guest allow, so its admin methods
        // (moderate/datatable/delete) were advertised to anonymous callers in /api + MCP tools/list.
        // Now the ACL is privilege-scoped: guest gets list/post only.
        $this->loginAs('guest');
        $methods = array_column(Tiger_Agent_Tools::catalog('guest')['comment'] ?? [], 'method');

        $this->assertContains('list', $methods, 'a guest may read a thread');
        $this->assertContains('post', $methods, 'a guest may post a comment/rating');
        foreach (['moderate', 'datatable', 'delete', 'edit'] as $adminOrOwner) {
            $this->assertNotContains($adminOrOwner, $methods, "a guest must not be shown comment/$adminOrOwner");
        }
    }

    #[Test]
    public function systemPromptOverTheLiveAclProducesAUsablePrompt(): void
    {
        $this->loginAs('developer');
        $prompt = Tiger_Agent_Tools::systemPrompt('developer',
            ['inventory' => true, 'read' => true, 'api' => true, 'file' => true, 'module' => true], ['path' => '/'], 'auto');

        $this->assertStringContainsString('You are TigerAgent', $prompt);
        $this->assertStringContainsString('CALLABLE /api CATALOG', $prompt);
        $this->assertStringContainsString('AUTO —', $prompt);
    }

    /** Total callable operations across all module groups. */
    private function flatCount(array $catalog): int
    {
        $n = 0;
        foreach ($catalog as $ops) { $n += count($ops); }
        return $n;
    }
}
