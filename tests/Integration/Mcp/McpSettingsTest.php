<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Mcp;

use Mcp_Service_Settings;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Mcp;
use Tiger_Model_Config;

/**
 * Mcp_Service_Settings — the enable toggle writes tiger.mcp.enabled to the global config tier, admin-gated.
 */
#[CoversClass(Mcp_Service_Settings::class)]
final class McpSettingsTest extends IntegrationTestCase
{
    private function call(string $method, array $params = []): object
    {
        return (new Mcp_Service_Settings(['action' => $method] + $params))->getResponse();
    }

    #[Test]
    public function save_writes_the_enabled_flag_to_config(): void
    {
        $this->loginAs('admin');
        $cfg = new Tiger_Model_Config();

        $on = $this->call('save', ['enabled' => 1]);
        $this->assertSame(1, (int) $on->result);
        $this->assertTrue($on->data['enabled']);
        $this->assertSame('1', (string) $cfg->get(Tiger_Model_Config::SCOPE_GLOBAL, '', Tiger_Mcp::CONFIG_ENABLED));

        $off = $this->call('save', ['enabled' => 0]);
        $this->assertSame(1, (int) $off->result);
        $this->assertFalse($off->data['enabled']);
        $this->assertSame('0', (string) $cfg->get(Tiger_Model_Config::SCOPE_GLOBAL, '', Tiger_Mcp::CONFIG_ENABLED));
    }

    #[Test]
    public function save_is_denied_for_a_non_admin(): void
    {
        $this->login('u1', 'org-test', 'user');
        $res = $this->call('save', ['enabled' => 1]);
        $this->assertSame(0, (int) $res->result);
        $this->assertStringContainsString('not_allowed', json_encode($res->messages));
    }
}
