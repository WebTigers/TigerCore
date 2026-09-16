<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Agent;

use Agent_Service_Agents;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Agent;
use Tiger_Crypto;
use Tiger_Model_Agent;
use Zend_Config;
use Zend_Registry;

/**
 * Agent_Service_Agents — the CRUD behind the multi-agent settings screen (TIGER-151, step 2).
 *
 * Covers the ACL gate (admin+ only), the create/update lifecycle, the write-only key convention
 * (a NEW key is encrypted and never returned; a BLANK field preserves the stored one), the
 * one-default-per-org invariant (first create is forced default; setting one moves it), and delete
 * (promotes a survivor when the default goes; the last delete returns to the legacy fallback).
 */
#[CoversClass(Agent_Service_Agents::class)]
final class AgentsServiceTest extends IntegrationTestCase
{
    private const CRYPTO_KEY = 'MTIzNDU2Nzg5MDEyMzQ1Njc4OTAxMjM0NTY3ODkwMTI=';

    protected function setUp(): void
    {
        parent::setUp();
        Zend_Registry::set('tiger.auth.stateless', true);
        Zend_Registry::set('Zend_Config', new Zend_Config(['tiger' => ['crypto' => ['key' => self::CRYPTO_KEY]]], true));
    }

    private function call(string $action, array $params = []): object
    {
        return (new Agent_Service_Agents(['action' => $action] + $params))->getResponse();
    }

    #[Test]
    public function crud_is_admin_only(): void
    {
        foreach (['guest', 'user', 'manager'] as $role) {
            $this->loginAs($role);
            foreach (['list', 'save', 'delete'] as $action) {
                $res = $this->call($action, ['name' => 'X']);
                $this->assertSame(0, (int) $res->result, "{$role} denied on {$action}");
                $this->assertStringContainsString('not_allowed', json_encode($res->messages));
            }
        }
    }

    #[Test]
    public function first_save_creates_the_forced_default_and_never_returns_the_key(): void
    {
        $this->loginAs('admin');
        $res = $this->call('save', [
            'name' => 'House', 'provider' => 'openai', 'model' => 'gpt-4.1', 'api_key' => 'sk-live', 'enabled' => '1',
            // note: is_default NOT passed — the first agent must still become default
        ]);
        $this->assertSame(1, (int) $res->result, json_encode($res->messages));
        $agent = $res->data['agent'];
        $this->assertNotSame('', $agent['agent_id']);
        $this->assertTrue($agent['is_default'], 'the first agent is forced default');
        $this->assertTrue($agent['connected'], 'a stored key reads as connected');
        $this->assertArrayNotHasKey('api_key', $agent, 'the key never round-trips');
        $this->assertArrayNotHasKey('api_key_enc', $agent);

        // The facade now resolves the row (not legacy config).
        Tiger_Agent::reset();
        $this->assertSame('openai', Tiger_Agent::provider());
        $this->assertSame('sk-live', Tiger_Agent::apiKey());
    }

    #[Test]
    public function setting_default_moves_it_and_blank_key_preserves_the_secret(): void
    {
        $this->loginAs('admin');
        $a = $this->call('save', ['name' => 'A', 'provider' => 'openai',    'api_key' => 'sk-a'])->data['agent'];
        $b = $this->call('save', ['name' => 'B', 'provider' => 'anthropic', 'api_key' => 'sk-b', 'is_default' => '1'])->data['agent'];

        $model = new Tiger_Model_Agent();
        $this->assertSame(0, (int) $model->findById($a['agent_id'])->is_default, 'A is no longer default');
        $this->assertSame(1, (int) $model->findById($b['agent_id'])->is_default, 'B is now the default');

        // Re-save A with a BLANK key — the stored secret survives.
        $this->call('save', ['agent_id' => $a['agent_id'], 'name' => 'A2', 'provider' => 'openai', 'api_key' => '']);
        $this->assertSame('sk-a', Tiger_Crypto::decrypt($model->findById($a['agent_id'])->api_key_enc), 'blank key kept the secret');
        $this->assertSame('A2', $model->findById($a['agent_id'])->name, 'other fields still update');
    }

    #[Test]
    public function deleting_the_default_promotes_a_survivor(): void
    {
        $this->loginAs('admin');
        $a = $this->call('save', ['name' => 'A', 'provider' => 'openai',    'api_key' => 'sk-a'])->data['agent']; // default
        $b = $this->call('save', ['name' => 'B', 'provider' => 'anthropic', 'api_key' => 'sk-b'])->data['agent'];

        $res = $this->call('delete', ['agent_id' => $a['agent_id']]);
        $this->assertSame(1, (int) $res->result, json_encode($res->messages));

        $model = new Tiger_Model_Agent();
        $this->assertNull($model->findById($a['agent_id']), 'A is soft-deleted (hidden)');
        $this->assertSame(1, (int) $model->findById($b['agent_id'])->is_default, 'B was promoted to default');
    }

    #[Test]
    public function deleting_the_last_agent_returns_to_the_legacy_fallback(): void
    {
        $this->loginAs('admin');
        // Legacy config present so the fallback is observable after the registry empties.
        Zend_Registry::set('Zend_Config', new Zend_Config(['tiger' => [
            'crypto' => ['key' => self::CRYPTO_KEY],
            'agent'  => ['enabled' => '1', 'provider' => 'anthropic', 'model' => 'claude-legacy'],
        ]], true));

        $a = $this->call('save', ['name' => 'Only', 'provider' => 'openai', 'model' => 'gpt-4.1'])->data['agent'];
        $this->call('delete', ['agent_id' => $a['agent_id']]);

        $this->assertCount(0, $this->call('list')->data['agents'], 'registry is empty again');
        Tiger_Agent::reset();
        $this->assertSame('anthropic', Tiger_Agent::provider(), 'facade falls back to legacy config');
        $this->assertSame('claude-legacy', Tiger_Agent::model());
    }

    #[Test]
    public function a_key_without_crypto_configured_is_refused(): void
    {
        $this->loginAs('admin');
        Zend_Registry::set('Zend_Config', new Zend_Config(['tiger' => []], true));   // no crypto key
        $res = $this->call('save', ['name' => 'NoCrypto', 'provider' => 'openai', 'api_key' => 'sk-x']);
        $this->assertSame(0, (int) $res->result);
        $this->assertStringContainsString('crypto', json_encode($res->messages));
    }
}
