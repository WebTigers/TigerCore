<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Agent;

use Agent_Service_Skills;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Agent_Skills;

/**
 * The installed side of Agent Skills — Tiger_Agent_Skills (discover installed, the config active-set,
 * remove) + Agent_Service_Skills (ACL + toggle/remove/installed). A fake skill is seeded into the app-owned
 * store so the tests never hit GitHub (install/search's network path is exercised separately / live).
 */
#[CoversClass(Tiger_Agent_Skills::class)]
#[CoversClass(Agent_Service_Skills::class)]
final class SkillsTest extends IntegrationTestCase
{
    private string $skillDir = '';

    /** @var string[] seeded index caches to clean up */
    private array $indexCaches = [];

    protected function setUp(): void
    {
        parent::setUp();
        // Seed one installed skill in the app-owned store (APPLICATION_PATH/skills/<key>/).
        $this->skillDir = Tiger_Agent_Skills::dir() . '/anthropic-skills__demo';
        @mkdir($this->skillDir, 0775, true);
        file_put_contents($this->skillDir . '/SKILL.md', "---\nname: demo\ndescription: A demo skill.\n---\nDo the thing.");
        file_put_contents($this->skillDir . '/source.json', json_encode(['sourceLabel' => 'Anthropic Skills', 'repo' => 'anthropics/skills']));
        // Short-circuit every built-in source's network scan with a FRESH, empty cache (datatable calls all()).
        @mkdir(APPLICATION_ROOT . '/var/cache/skills', 0775, true);
        foreach (['webtigers-skills', 'anthropic-skills', 'composio-skills'] as $id) {
            $file = APPLICATION_ROOT . '/var/cache/skills/' . $id . '.json';
            file_put_contents($file, json_encode(['at' => time(), 'entries' => []]));
            $this->indexCaches[] = $file;
        }
    }

    protected function tearDown(): void
    {
        foreach (['/SKILL.md', '/source.json'] as $f) { @unlink($this->skillDir . $f); }
        @rmdir($this->skillDir);
        @rmdir(Tiger_Agent_Skills::dir());
        foreach ($this->indexCaches as $f) { @unlink($f); }
        parent::tearDown();
    }

    private function call(string $action, array $params = []): object
    {
        return (new Agent_Service_Skills(['action' => $action] + $params))->getResponse();
    }

    #[Test]
    public function discovers_an_installed_skill(): void
    {
        $rows = Tiger_Agent_Skills::installed();
        $demo = array_values(array_filter($rows, fn($s) => $s['key'] === 'anthropic-skills__demo'));
        $this->assertNotEmpty($demo, 'the seeded skill is discovered');
        $this->assertSame('demo', $demo[0]['name']);
        $this->assertSame('A demo skill.', $demo[0]['description']);
        $this->assertFalse($demo[0]['active'], 'install != activate — off by default');
        $this->assertTrue(Tiger_Agent_Skills::isInstalled('anthropic-skills__demo'));
    }

    #[Test]
    public function active_set_round_trips_through_config(): void
    {
        $this->assertFalse(Tiger_Agent_Skills::isActive('anthropic-skills__demo'));
        Tiger_Agent_Skills::setActive('anthropic-skills__demo', true);
        $this->assertTrue(Tiger_Agent_Skills::isActive('anthropic-skills__demo'), 'turning it on persists to config');
        $this->assertContains('anthropic-skills__demo', Tiger_Agent_Skills::active());
        Tiger_Agent_Skills::setActive('anthropic-skills__demo', false);
        $this->assertFalse(Tiger_Agent_Skills::isActive('anthropic-skills__demo'), 'and off again');
    }

    #[Test]
    public function remove_deletes_files_and_clears_active(): void
    {
        Tiger_Agent_Skills::setActive('anthropic-skills__demo', true);
        Tiger_Agent_Skills::remove('anthropic-skills__demo');
        $this->assertFalse(Tiger_Agent_Skills::isInstalled('anthropic-skills__demo'), 'files gone');
        $this->assertNotContains('anthropic-skills__demo', Tiger_Agent_Skills::active(), 'dropped from the active set');
    }

    // ----- service ---------------------------------------------------------------------------------

    #[Test]
    public function service_is_denied_for_a_guest(): void
    {
        $this->login('anon', 'org-test', 'guest');
        foreach (['search', 'installed', 'install', 'toggle', 'remove'] as $action) {
            $res = $this->call($action);
            $this->assertSame(0, (int) $res->result, "guest denied on {$action}");
            $this->assertStringContainsString('not_allowed', json_encode($res->messages));
        }
    }

    #[Test]
    public function service_lists_toggles_and_removes(): void
    {
        $this->loginAs('admin');

        $list = $this->call('installed');
        $this->assertSame(1, (int) $list->result);
        $this->assertNotEmpty(array_filter($list->data['skills'], fn($s) => $s['key'] === 'anthropic-skills__demo'));

        $on = $this->call('toggle', ['key' => 'anthropic-skills__demo', 'active' => 1]);
        $this->assertSame(1, (int) $on->result);
        $this->assertTrue($on->data['active']);
        $this->assertTrue(Tiger_Agent_Skills::isActive('anthropic-skills__demo'));

        $rm = $this->call('remove', ['key' => 'anthropic-skills__demo']);
        $this->assertSame(1, (int) $rm->result);
        $this->assertFalse(Tiger_Agent_Skills::isInstalled('anthropic-skills__demo'));
    }

    #[Test]
    public function toggle_and_remove_reject_an_unknown_key(): void
    {
        $this->loginAs('admin');
        $this->assertSame(0, (int) $this->call('toggle', ['key' => 'nope__nope', 'active' => 1])->result);
        $this->assertSame(0, (int) $this->call('remove', ['key' => 'nope__nope'])->result);
    }

    #[Test]
    public function datatable_merges_and_pins_installed_to_the_top(): void
    {
        $this->loginAs('admin');
        // The catalog is short-circuited to empty (seeded caches), so only the installed demo appears.
        $res = $this->call('datatable', ['draw' => 1, 'start' => 0, 'length' => 25]);
        $this->assertSame(1, (int) $res->result);
        $this->assertSame(1, (int) $res->data['draw']);

        $rows = $res->data['data'];
        $demo = array_values(array_filter($rows, fn($r) => $r['key'] === 'anthropic-skills__demo'));
        $this->assertNotEmpty($demo, 'the installed skill is a grid row');
        $this->assertTrue($demo[0]['installed'], 'status flags it installed');
        $this->assertFalse($demo[0]['active'], 'off by default');
        // Installed rows sort ahead of any not-installed row.
        $this->assertTrue((bool) $rows[0]['installed'], 'installed is pinned to the top');
    }

    #[Test]
    public function datatable_active_state_follows_the_config_set(): void
    {
        $this->loginAs('admin');
        Tiger_Agent_Skills::setActive('anthropic-skills__demo', true);
        $res = $this->call('datatable', ['draw' => 2, 'start' => 0, 'length' => 25]);
        $demo = array_values(array_filter($res->data['data'], fn($r) => $r['key'] === 'anthropic-skills__demo'));
        $this->assertTrue($demo[0]['active'], 'a turned-on skill reads active in the grid');
    }

    #[Test]
    public function datatable_is_denied_for_a_guest(): void
    {
        $this->login('anon', 'org-test', 'guest');
        $res = $this->call('datatable', ['draw' => 1, 'start' => 0, 'length' => 25]);
        $this->assertSame(0, (int) $res->result);
    }
}
