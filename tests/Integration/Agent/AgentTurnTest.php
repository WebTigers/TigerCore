<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Agent;

use Agent_Service_Agent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Agent_Forge;
use Tiger_Agent_Loop;
use Tiger_Agent_Mcp;
use Tiger_Agent_Provider_Adapter;
use Tiger_Agent_Provider_Factory;
use Tiger_Crypto;
use Zend_Config;
use Zend_Db_Table_Abstract;
use Zend_Registry;

/**
 * The end-to-end proof that the internal agent can actually RUN THE APP — the whole spine the other
 * agent suites deliberately leave untested (they stop at "the turn itself is the live boundary" because
 * the provider call had no stub seam). With `Tiger_Agent_Provider_Factory::setAdapter()` we inject a
 * SCRIPTED model, so a full turn is driven deterministically: a scripted reply → `Tiger_Agent_Loop`
 * parses the contract → `Tiger_Agent_Forge` dispatches the named `/api` op AS THE IDENTITY (real ACL +
 * form-validate + transaction) → the DB actually changes.
 *
 * Three real behaviours: an auto-mode write that lands a row, a multi-step read→write turn (the loop
 * iterates on `done:false`), and the ask-mode approval gate (proposed → approve → the row appears).
 */
#[CoversClass(Tiger_Agent_Loop::class)]
#[CoversClass(Tiger_Agent_Forge::class)]
final class AgentTurnTest extends IntegrationTestCase
{
    private const CRYPTO_KEY = 'MTIzNDU2Nzg5MDEyMzQ1Njc4OTAxMjM0NTY3ODkwMTI='; // base64 of a 32-byte test key

    /** @var ScriptedAgentProvider */
    private $provider;

    protected function setUp(): void
    {
        parent::setUp();
        Zend_Registry::set('tiger.auth.stateless', true);   // Bearer/API path — CSRF-immune (no session in CLI)
        // Enable the agent + a crypto key that can decrypt the (fake) BYO provider key. mode_max=yolo so
        // nothing clamps the per-turn mode down; each test asks for the mode it wants.
        Zend_Registry::set('Zend_Config', new Zend_Config(['tiger' => ['crypto' => ['key' => self::CRYPTO_KEY]]], true));
        $enc = Tiger_Crypto::encrypt('sk-test-key');
        Zend_Registry::set('Zend_Config', new Zend_Config(['tiger' => [
            'crypto' => ['key' => self::CRYPTO_KEY],
            'agent'  => ['enabled' => '1', 'api_key_enc' => $enc, 'mode_max' => 'yolo'],
        ]], true));
    }

    protected function tearDown(): void
    {
        Tiger_Agent_Provider_Factory::setAdapter(null);   // never leak the double into another test
        parent::tearDown();
    }

    // ----- helpers -----------------------------------------------------------

    /** Queue the model's replies (each a contract-shaped payload) and inject the scripted provider. */
    private function script(array $replies): void
    {
        $this->provider = new ScriptedAgentProvider(array_map('json_encode', $replies));
        Tiger_Agent_Provider_Factory::setAdapter($this->provider);
    }

    private function call(string $action, array $params = []): object
    {
        return (new Agent_Service_Agent(['action' => $action] + $params))->getResponse();
    }

    /** A CMS page-create action for the model to "emit" (title is the only hard-required field). */
    private function pageWrite(string $title): array
    {
        return [
            'type' => 'api', 'module' => 'cms', 'service' => 'page', 'method' => 'save',
            'params' => ['title' => $title, 'body' => 'Written by the agent', 'format' => 'html', 'type' => 'page', 'status' => 'draft', 'locale' => 'en'],
            'reason' => 'the user asked for a page',
        ];
    }

    private function pageCount(string $title): int
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        return (int) $db->fetchOne('SELECT COUNT(*) FROM page WHERE title = ? AND deleted = 0', [$title]);
    }

    // ----- the turns ---------------------------------------------------------

    #[Test]
    public function auto_mode_drives_a_real_cms_write_end_to_end(): void
    {
        $this->loginAs('admin');
        $title = 'Agent E2E ' . substr(md5('auto'), 0, 8);

        $this->script([
            ['say' => 'Creating the page now.', 'actions' => [$this->pageWrite($title)], 'done' => true],
        ]);

        $res = $this->call('send', ['message' => 'make me a page called ' . $title, 'mode' => 'auto']);

        $this->assertSame(1, (int) $res->result, json_encode($res->messages));
        $statuses = array_column($res->data['actions'], 'status');
        $this->assertContains('done', $statuses, 'the /api write executed');
        $this->assertNotContains('proposed', $statuses, 'auto mode did not pause for approval');
        $this->assertSame(1, $this->pageCount($title), 'the CMS page the agent created is really in the store');
    }

    #[Test]
    public function the_loop_reads_then_writes_across_steps(): void
    {
        $this->loginAs('admin');
        $title = 'Agent Multi ' . substr(md5('multi'), 0, 8);

        $this->script([
            ['say' => 'Let me look around first.', 'actions' => [['type' => 'read.inventory']], 'done' => false],
            ['say' => 'Now I will create it.',    'actions' => [$this->pageWrite($title)],       'done' => true],
        ]);

        $res = $this->call('send', ['message' => 'survey then build', 'mode' => 'auto']);

        $this->assertSame(1, (int) $res->result, json_encode($res->messages));
        $this->assertSame(2, $this->provider->calls(), 'the loop iterated a second step after done:false');
        $this->assertSame(0, $this->provider->remaining(), 'both scripted replies were consumed');
        $this->assertSame(1, $this->pageCount($title), 'the write after the read landed');
    }

    #[Test]
    public function ask_mode_proposes_a_write_then_approve_runs_it(): void
    {
        $this->loginAs('admin');
        $title = 'Agent Ask ' . substr(md5('ask'), 0, 8);

        $this->script([
            ['say' => 'I would like to create a page — approve?', 'actions' => [$this->pageWrite($title)], 'done' => true],
            ['say' => 'Done — the page is created.', 'done' => true],   // the post-approve follow-up turn
        ]);

        $send = $this->call('send', ['message' => 'create a page', 'mode' => 'ask']);
        $this->assertSame(1, (int) $send->result, json_encode($send->messages));
        $this->assertContains('proposed', array_column($send->data['actions'], 'status'), 'ask mode paused for approval');
        $this->assertSame(0, $this->pageCount($title), 'nothing was written before approval');

        $appr = $this->call('approve', ['run_id' => $send->data['run_id'], 'all' => 1, 'mode' => 'ask']);
        $this->assertSame(1, (int) $appr->result, json_encode($appr->messages));
        $this->assertSame(1, $this->pageCount($title), 'approval executed the proposed write');
    }

    #[Test]
    public function the_agent_can_see_the_app_via_the_scout(): void
    {
        $this->loginAs('superadmin');   // read.file is a superadmin-tier Scout privilege
        $this->script([
            ['say' => 'Reading a file.', 'actions' => [['type' => 'read.file', 'path' => 'library/Tiger/Version.php']], 'done' => false],
            ['say' => 'Got it.', 'done' => true],
        ]);

        $res = $this->call('send', ['message' => 'show me the version file', 'mode' => 'ask']);

        $this->assertSame(1, (int) $res->result, json_encode($res->messages));
        $this->assertSame(2, $this->provider->calls(), 'a read auto-runs, then the loop continues to report');
        $read = array_values(array_filter($res->data['actions'], static fn($a) => ($a['type'] ?? '') === 'read.file'))[0] ?? [];
        $this->assertSame('done', $read['status'] ?? '', 'the Scout read executed (the agent can see the app)');
    }

    #[Test]
    public function an_api_read_auto_runs_even_in_ask_mode(): void
    {
        $this->loginAs('admin');
        // A read-verb method (datatable) is never approval-gated — reads flow, writes gate.
        $this->script([
            ['say' => 'Listing pages.', 'actions' => [[
                'type' => 'api', 'module' => 'cms', 'service' => 'page', 'method' => 'datatable',
                'params' => ['draw' => 1, 'start' => 0, 'length' => 10], 'reason' => 'inspect content',
            ]], 'done' => true],
        ]);

        $res = $this->call('send', ['message' => 'what pages exist', 'mode' => 'ask']);

        $this->assertSame(1, (int) $res->result, json_encode($res->messages));
        $statuses = array_column($res->data['actions'], 'status');
        $this->assertContains('done', $statuses, 'a read ran without approval, even in ask mode');
        $this->assertNotContains('proposed', $statuses, 'a read is never gated');
    }

    #[Test]
    public function the_acl_wall_holds_when_the_agent_tries_a_write_above_its_role(): void
    {
        $this->loginAs('manager');   // may chat, but cannot create users
        $this->script([
            ['say' => 'Creating a user.', 'actions' => [[
                'type' => 'api', 'module' => 'access', 'service' => 'user', 'method' => 'create',
                'params' => ['email' => 'evil@example.com', 'username' => 'evil'], 'reason' => 'overreach',
            ]], 'done' => true],
        ]);

        // yolo would auto-run the write — so the ONLY thing stopping it is the ACL, which is the point.
        $res = $this->call('send', ['message' => 'make an admin user', 'mode' => 'yolo']);

        $this->assertSame(1, (int) $res->result, json_encode($res->messages));
        $this->assertContains('denied', array_column($res->data['actions'], 'status'), 'the ACL refused the over-role write');
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        $this->assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM user WHERE email = ?', ['evil@example.com']), 'no user was created');
    }

    #[Test]
    public function an_mcp_tool_call_routes_through_a_turn(): void
    {
        $this->loginAs('admin');
        $connId = Tiger_Agent_Mcp::save(['label' => 'Remote', 'url' => 'http://127.0.0.1:9/mcp', 'enabled' => true]);
        $this->script([
            ['say' => 'I will call the remote tool.', 'actions' => [[
                'type' => 'mcp', 'connection' => $connId, 'tool' => 'do_thing', 'args' => ['x' => 1], 'reason' => 'external work',
            ]], 'done' => true],
        ]);

        // ask mode → the mcp write is proposed (routed, but no network hit) — proves the agent can target
        // an external MCP server's tools through the same approval gate as any other write.
        $res = $this->call('send', ['message' => 'use the remote tool', 'mode' => 'ask']);

        $this->assertSame(1, (int) $res->result, json_encode($res->messages));
        $this->assertContains('proposed', array_column($res->data['actions'], 'status'), 'the mcp action routed into the turn and gated for approval');
        @unlink(APPLICATION_ROOT . '/var/cache/agent-mcp/tools.json');
    }
}

/**
 * A provider adapter whose replies are scripted — the seam that makes a full agent turn deterministic.
 * Each `complete()` returns the next queued contract payload; when the queue drains it returns a terminal
 * "done" reply so a loop that steps more than scripted still halts cleanly.
 */
final class ScriptedAgentProvider implements Tiger_Agent_Provider_Adapter
{
    /** @var string[] */
    private $queue;
    private int $calls = 0;

    public function __construct(array $replies)
    {
        $this->queue = array_values($replies);
    }

    public function complete($system, array $messages, $model, $apiKey)
    {
        $this->calls++;
        $text = array_shift($this->queue);
        if ($text === null) { $text = json_encode(['say' => '', 'done' => true]); }
        return ['text' => $text, 'usage' => ['input' => 5, 'output' => 7]];
    }

    public function models($apiKey = '')
    {
        return ['test-model'];
    }

    public function calls(): int
    {
        return $this->calls;
    }

    public function remaining(): int
    {
        return count($this->queue);
    }
}
