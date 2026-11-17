<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Agent;

use Mcp_ServerController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\ControllerTestCase;
use Tiger_Agent_Contract;
use Tiger_Agent_Scout;
use Tiger_Agent_Skills;
use Tiger_Agent_Tools;
use Tiger_Mcp_Server;
use Tiger_Model_Config;
use Zend_Config;
use Zend_Registry;

/**
 * TIGER-532 — the progressive-disclosure skills loader, for BOTH agents: a one-line menu of the org's ACTIVE
 * skills up front (the in-app system prompt / the MCP `initialize` instructions), and the full skill loaded on
 * demand (`read.skill` / `agent__skills__load`). Pins: the active list is per-org with an install-wide fallback;
 * only installed AND active skills appear or load; a skill's files come back as text and are never executed;
 * loading needs no admin role and no `agent` token scope.
 */
#[CoversClass(Tiger_Agent_Skills::class)]
#[CoversClass(Tiger_Agent_Scout::class)]
#[CoversClass(Tiger_Mcp_Server::class)]
#[CoversClass(Mcp_ServerController::class)]
final class SkillsLoaderTest extends ControllerTestCase
{
    private const ORG_A = 'org-skills-a';
    private const ORG_B = 'org-skills-b';
    private $origConfig;

    protected function setUp(): void
    {
        parent::setUp();
        $this->origConfig = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        $arr = $this->origConfig ? $this->origConfig->toArray() : [];
        $arr['tiger']['log']['writer'] = 'null';
        Zend_Registry::set('Zend_Config', new Zend_Config($arr, true));
        \Tiger_Log::reset();

        $this->seed('alpha', "---\nname: alpha\ndescription: Use when writing alpha things.\n---\nAlpha steps.", [
            'reference/notes.md' => 'Alpha reference notes.',
            // If anything ever executed a bundled file, this would define a constant and drop a marker file.
            'scripts/run.php'    => "<?php define('TIGER532_EXECUTED', 1); file_put_contents(sys_get_temp_dir() . '/tiger532-ran', 'x');",
            'logo.bin'           => "\x00\x01\x02binary",
        ]);
        $this->seed('beta', "---\nname: beta\ndescription: Use when writing beta things.\n---\nBeta steps.");
        @unlink(sys_get_temp_dir() . '/tiger532-ran');
        Tiger_Model_Config::resetCache();
    }

    protected function tearDown(): void
    {
        $this->rrmdir(Tiger_Agent_Skills::dir());
        if ($this->origConfig !== null) { Zend_Registry::set('Zend_Config', $this->origConfig); }
        \Tiger_Log::reset();
        Tiger_Model_Config::resetCache();
        parent::tearDown();
    }

    private function seed(string $key, string $md, array $files = []): void
    {
        $dir = Tiger_Agent_Skills::dir() . '/' . $key;
        @mkdir($dir, 0775, true);
        file_put_contents($dir . '/SKILL.md', $md);
        foreach ($files as $rel => $content) {
            @mkdir(dirname($dir . '/' . $rel), 0775, true);
            file_put_contents($dir . '/' . $rel, $content);
        }
    }

    private function rrmdir(string $dir): void
    {
        if ($dir === '' || !is_dir($dir)) { return; }
        foreach (scandir($dir) ?: [] as $f) {
            if ($f === '.' || $f === '..') { continue; }
            $p = $dir . '/' . $f;
            is_dir($p) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    private function setList(string $scope, string $scopeId, string $keys): void
    {
        (new Tiger_Model_Config())->set($scope, $scopeId, Tiger_Agent_Skills::ACTIVE_KEY, $keys);
    }

    #[Test]
    public function the_active_list_is_per_org_with_an_install_wide_fallback(): void
    {
        $this->setList('global', '', 'alpha');
        $this->setList('org', self::ORG_A, 'beta');

        $this->assertSame(['beta'], Tiger_Agent_Skills::active(self::ORG_A), 'an org with its own list uses it');
        $this->assertSame(['alpha'], Tiger_Agent_Skills::active(self::ORG_B), 'an org with none inherits the install list');

        $this->setList('org', self::ORG_A, '');
        $this->assertSame([], Tiger_Agent_Skills::active(self::ORG_A), 'switching everything off is a choice, not "inherit"');

        Tiger_Agent_Skills::setActive('alpha', true, self::ORG_B);
        $this->assertSame('alpha', (new Tiger_Model_Config())->get('org', self::ORG_B, Tiger_Agent_Skills::ACTIVE_KEY),
            'a toggle writes the org\'s own row');
        $this->assertSame('alpha', (new Tiger_Model_Config())->get('global', '', Tiger_Agent_Skills::ACTIVE_KEY),
            'and leaves the install-wide list alone');
    }

    #[Test]
    public function the_menu_lists_only_installed_active_skills(): void
    {
        $this->setList('org', self::ORG_A, 'alpha,ghost');   // ghost: active but not installed
        $menu = Tiger_Agent_Skills::menu(self::ORG_A);
        $this->assertSame(['alpha'], array_column($menu, 'key'), 'beta is installed but inactive; ghost is not installed');
        $this->assertSame('Use when writing alpha things.', $menu[0]['description']);
        $this->assertStringContainsString('alpha — alpha: Use when writing alpha things.', Tiger_Agent_Skills::menuText(self::ORG_A));
        $this->assertSame('', Tiger_Agent_Skills::menuText(self::ORG_B . '-none'), 'no active skills → no section at all');
    }

    #[Test]
    public function load_returns_text_and_never_executes_a_bundled_file(): void
    {
        $this->setList('org', self::ORG_A, 'alpha');
        $s = Tiger_Agent_Skills::load('alpha', self::ORG_A);

        $this->assertNotNull($s);
        $this->assertStringContainsString('Alpha steps.', $s['body']);
        $paths = array_column($s['files'], 'path');
        $this->assertSame(['reference/notes.md', 'scripts/run.php'], $paths, 'text files listed; the binary and source.json are not');
        $this->assertArrayNotHasKey('content', $s['files'][0], 'the first load is a manifest — no file contents in context');
        $this->assertStringContainsString('scripts/run.php (', Tiger_Agent_Skills::toText($s));

        $f = Tiger_Agent_Skills::load('alpha', self::ORG_A, 'scripts/run.php');
        $this->assertStringContainsString("define('TIGER532_EXECUTED'", $f['content'], 'a script comes back as text to read');
        $this->assertFalse(defined('TIGER532_EXECUTED'), 'loading a skill never runs its files');
        $this->assertFileDoesNotExist(sys_get_temp_dir() . '/tiger532-ran');
        $this->assertStringContainsString('Tiger never runs it', Tiger_Agent_Skills::toText($f));

        foreach (['logo.bin', 'source.json', '../beta/SKILL.md', '/etc/passwd', 'reference/../scripts/run.php'] as $bad) {
            $this->assertNull(Tiger_Agent_Skills::load('alpha', self::ORG_A, $bad), "$bad is not in the file list");
        }
    }

    #[Test]
    public function load_refuses_an_inactive_or_uninstalled_skill(): void
    {
        $this->setList('org', self::ORG_A, 'alpha,ghost');
        $this->assertNull(Tiger_Agent_Skills::load('beta', self::ORG_A), 'installed but not active for this org');
        $this->assertNull(Tiger_Agent_Skills::load('ghost', self::ORG_A), 'active but not installed');
        $this->assertNull(Tiger_Agent_Skills::load('../alpha', self::ORG_A), 'a path is not a key');
    }

    #[Test]
    public function the_in_app_agent_sees_the_menu_and_a_user_can_read_a_skill(): void
    {
        $this->login('u-532', self::ORG_A, 'user');
        $this->setList('org', self::ORG_A, 'alpha');

        $prompt = Tiger_Agent_Tools::systemPrompt('user', ['api' => true], [], 'ask');
        $this->assertStringContainsString('SKILLS', $prompt);
        $this->assertStringContainsString('alpha — alpha: Use when writing alpha things.', $prompt);
        $this->assertStringContainsString('"type":"read.skill"', $prompt);

        $action = Tiger_Agent_Contract::normalizeAction(['type' => 'read.skill', 'skill' => 'alpha', 'reason' => 'x']);
        $this->assertTrue(Tiger_Agent_Contract::isRead($action['type']), 'read.skill is a Scout read (auto-runs)');
        $entry = (new Tiger_Agent_Scout('user'))->execute($action);
        $this->assertSame('done', $entry['status'], 'a plain user may load an active skill');
        $this->assertStringContainsString('Alpha steps.', $entry['feedback']);

        $refused = (new Tiger_Agent_Scout('user'))->execute(['type' => 'read.skill', 'skill' => 'beta', 'reason' => 'x']);
        $this->assertSame('error', $refused['status'], 'an inactive skill is refused');
    }

    #[Test]
    public function mcp_initialize_lists_the_orgs_active_skills_and_tools_list_offers_the_loader(): void
    {
        $this->setList('org', self::ORG_A, 'alpha');

        $init = Tiger_Mcp_Server::handle(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize'], 'user', fn() => null, ['cms'], self::ORG_A);
        $this->assertStringContainsString('alpha — alpha: Use when writing alpha things.', $init['result']['instructions']);
        $this->assertStringContainsString(Tiger_Mcp_Server::SKILL_TOOL, $init['result']['instructions']);

        $list   = Tiger_Mcp_Server::handle(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], 'user', fn() => null, ['cms'], self::ORG_A);
        $byName = array_column($list['result']['tools'], null, 'name');
        $this->assertArrayHasKey(Tiger_Mcp_Server::SKILL_TOOL, $byName, 'listed for a user-role token scoped only to cms');
        $this->assertSame(['alpha'], $byName[Tiger_Mcp_Server::SKILL_TOOL]['inputSchema']['properties']['skill']['enum']);
        $this->assertTrue($byName[Tiger_Mcp_Server::SKILL_TOOL]['annotations']['readOnlyHint']);

        $none = Tiger_Mcp_Server::handle(['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/list'], 'user', fn() => null, ['cms'], self::ORG_B . '-none');
        $this->assertNotContains(Tiger_Mcp_Server::SKILL_TOOL, array_column($none['result']['tools'], 'name'), 'nothing active → no loader tool');
        $plain = Tiger_Mcp_Server::handle(['jsonrpc' => '2.0', 'id' => 4, 'method' => 'initialize'], 'user', fn() => null, ['cms'], self::ORG_B . '-none');
        $this->assertStringNotContainsString('SKILLS', $plain['result']['instructions']);
    }

    #[Test]
    public function a_user_role_token_loads_an_active_skill_over_mcp_and_is_refused_an_inactive_one(): void
    {
        $this->setList('org', self::ORG_A, 'alpha');
        $ctl  = new SkillsMcpController(new \Zend_Controller_Request_Http(), new \Zend_Controller_Response_Http());
        $conf = ['modules' => ['cms'], 'read_only' => true];   // a curated, read-only token: no `agent` scope

        $ok = $ctl->runTool('agent', 'skills', 'load', ['skill' => 'alpha'], $conf, '', null, 'user', self::ORG_A);
        $this->assertSame(1, (int) $ok->result);
        $this->assertStringContainsString('Alpha steps.', $ok->data['text']);
        $file = $ctl->runTool('agent', 'skills', 'load', ['skill' => 'alpha', 'file' => 'reference/notes.md'], $conf, '', null, 'user', self::ORG_A);
        $this->assertStringContainsString('Alpha reference notes.', $file->data['text'], 'one bundled file on request');

        $no = $ctl->runTool('agent', 'skills', 'load', ['skill' => 'beta'], $conf, '', null, 'user', self::ORG_A);
        $this->assertSame(0, (int) $no->result, 'an inactive skill is refused');
        $other = $ctl->runTool('agent', 'skills', 'load', ['skill' => 'alpha'], $conf, '', null, 'user', self::ORG_B . '-none');
        $this->assertSame(0, (int) $other->result, 'another org\'s curation does not leak');
    }
}

/** Exposes the MCP controller's tool dispatch for the test. */
class SkillsMcpController extends Mcp_ServerController
{
    public function runTool($module, $service, $method, array $args, $config, $prefix, $identity, $role, $org)
    {
        return $this->_dispatchTool($module, $service, $method, $args, $config, $prefix, $identity, $role, $org);
    }
}
