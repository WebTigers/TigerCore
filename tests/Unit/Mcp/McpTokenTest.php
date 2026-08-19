<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Mcp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Mcp_Token;

/**
 * Tiger_Mcp_Token — the pure policy decisions (scope + read-only). The config/meter stores hit the option
 * tier and are covered in the integration tests; this asserts denyReason()/allowsModule() in isolation.
 */
#[CoversClass(Tiger_Mcp_Token::class)]
final class McpTokenTest extends UnitTestCase
{
    private function cfg(array $o = []): array
    {
        return array_merge(['modules' => ['cms', 'media'], 'read_only' => false, 'org_scoped' => false], $o);
    }

    #[Test]
    public function allows_module_checks_the_scope(): void
    {
        $this->assertTrue(Tiger_Mcp_Token::allowsModule($this->cfg(), 'cms'));
        $this->assertFalse(Tiger_Mcp_Token::allowsModule($this->cfg(), 'access'));
    }

    #[Test]
    public function deny_reason_flags_an_out_of_scope_module(): void
    {
        $this->assertSame('out_of_scope', Tiger_Mcp_Token::denyReason($this->cfg(['modules' => ['cms']]), 'blog', 'save'));
    }

    #[Test]
    public function deny_reason_blocks_a_write_on_a_read_only_token_but_allows_a_read(): void
    {
        $ro = $this->cfg(['modules' => ['cms'], 'read_only' => true]);
        $this->assertSame('read_only', Tiger_Mcp_Token::denyReason($ro, 'cms', 'save'),   'save is a write');
        $this->assertSame('read_only', Tiger_Mcp_Token::denyReason($ro, 'cms', 'delete'), 'delete is a write');
        $this->assertNull(Tiger_Mcp_Token::denyReason($ro, 'cms', 'datatable'), 'datatable is a read → allowed');
        $this->assertNull(Tiger_Mcp_Token::denyReason($ro, 'cms', 'get'),       'get is a read → allowed');
    }

    #[Test]
    public function deny_reason_allows_an_in_scope_write_when_not_read_only(): void
    {
        $this->assertNull(Tiger_Mcp_Token::denyReason($this->cfg(['modules' => ['cms']]), 'cms', 'save'));
    }

    #[Test]
    public function default_modules_are_the_curated_set(): void
    {
        $this->assertContains('cms', Tiger_Mcp_Token::DEFAULT_MODULES);
        $this->assertContains('media', Tiger_Mcp_Token::DEFAULT_MODULES);
        $this->assertNotContains('access', Tiger_Mcp_Token::DEFAULT_MODULES, 'least-privilege: identity is opt-in');
    }
}
