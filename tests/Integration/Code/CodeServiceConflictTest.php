<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Code;

use Code_Service_Code;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Code_Modules;
use Tiger_Model_Code;
use Zend_Registry;

/**
 * Code_Service_Code — the compile-conflict self-heal rails CodeServiceExtraTest left uncovered: a
 * SAVE whose new active snippet redeclares a function another active snippet defines (the bundle
 * `php -l` fails → the row is flagged + the last-good set stays live → "Saved, but not activated"),
 * the same conflict on a local ACTIVATE, the module-snippet toggle conflict (config flag rolled back),
 * plus the restore-of-a-missing-id error arm and the footer auto-insert branch.
 *
 * Two UNCONDITIONAL top-level `function w7dup(){}` declarations in one assembled bundle are a
 * compile-time "Cannot redeclare" — exactly the cross-snippet conflict `php -l` catches (each snippet
 * lints fine alone; only the union fails). superadmin-gated; bundle files are scrubbed in tearDown.
 */
#[CoversClass(Code_Service_Code::class)]
final class CodeServiceConflictTest extends IntegrationTestCase
{
    private const SLUG = 'w7codemod';

    private string $moduleDir;

    protected function setUp(): void
    {
        parent::setUp();
        Zend_Registry::set('tiger.auth.stateless', true);

        // Two module snippets that BOTH define an unguarded top-level w7mdup() → activating both is a
        // redeclare conflict (used to exercise _toggleModule's roll-back arm).
        $this->moduleDir = APPLICATION_PATH . '/modules/' . self::SLUG;
        $snip = $this->moduleDir . '/snippets';
        @mkdir($snip, 0777, true);
        file_put_contents($snip . '/dupa.php', "<?php\n// tiger:snippet label=\"Dup A\" scope=\"global\"\nfunction w7mdup() { return 'a'; }\n");
        file_put_contents($snip . '/dupb.php', "<?php\n// tiger:snippet label=\"Dup B\" scope=\"global\"\nfunction w7mdup() { return 'b'; }\n");
    }

    protected function tearDown(): void
    {
        Zend_Registry::set('tiger.auth.stateless', false);
        $this->rmrf($this->moduleDir);
        $dir = APPLICATION_ROOT . '/storage/cache/code';
        foreach (glob($dir . '/global.*.php') ?: [] as $f) { @unlink($f); }
        foreach (glob($dir . '/inject.global.*.php') ?: [] as $f) { @unlink($f); }
        parent::tearDown();
    }

    private function rmrf(string $dir): void
    {
        if (!is_dir($dir)) { @unlink($dir); return; }
        foreach (scandir($dir) as $e) {
            if ($e === '.' || $e === '..') { continue; }
            $p = $dir . '/' . $e;
            is_dir($p) && !is_link($p) ? $this->rmrf($p) : @unlink($p);
        }
        @rmdir($dir);
    }

    private function call(string $action, array $params = []): object
    {
        return (new Code_Service_Code(['action' => $action] + $params))->getResponse();
    }

    /** Insert an ACTIVE local PHP snippet defining an unconditional top-level function. */
    private function seedActive(string $name, string $fn): string
    {
        return (new Tiger_Model_Code())->insert([
            'org_id'       => '',
            'name'         => $name,
            'language'     => Tiger_Model_Code::LANG_PHP,
            'code'         => "function {$fn}() { return 1; }",
            'run_location' => Tiger_Model_Code::LOC_GLOBAL,
            'active'       => 1,
            'status'       => Tiger_Model_Code::STATUS_ACTIVE,
        ]);
    }

    // ----- SAVE that conflicts with the running set ---------------------------------------------

    #[Test]
    public function saving_a_snippet_that_redeclares_an_active_function_is_stored_but_not_activated(): void
    {
        $this->loginAs('superadmin');
        $this->seedActive('First', 'w7dup');   // already active + in the bundle

        // A second active snippet redeclaring w7dup — lints fine alone, but the assembled bundle can't.
        $res = $this->call('save', [
            'name'     => 'Second',
            'language' => Tiger_Model_Code::LANG_PHP,
            'code'     => 'function w7dup() { return 2; }',
            'active'   => '1',
            'priority' => '100',
        ]);

        $this->assertSame(0, (int) $res->result, 'the conflicting save is not fully applied');
        $this->assertStringContainsString('Saved, but not activated', json_encode($res->messages));
        // The conflict path errors without a data payload — but the row WAS persisted (save ran before
        // the rebuild), and the self-heal flagged it off so the last-good set stays live.
        $active = $this->db->fetchOne('SELECT active FROM code WHERE name = ?', ['Second']);
        $this->assertNotFalse($active, 'the snippet was still persisted');
        $this->assertSame(0, (int) $active, 'deactivated by the self-heal');
    }

    // ----- ACTIVATE that conflicts with the running set -----------------------------------------

    #[Test]
    public function activating_a_local_snippet_that_conflicts_is_refused_and_self_heals(): void
    {
        $this->loginAs('superadmin');
        $this->seedActive('Alpha', 'w7dup');

        // Beta redeclares w7dup but starts inactive; turning it on makes the union fail to compile.
        $beta = (new Tiger_Model_Code())->insert([
            'org_id' => '', 'name' => 'Beta', 'language' => Tiger_Model_Code::LANG_PHP,
            'code' => 'function w7dup() { return 9; }', 'run_location' => Tiger_Model_Code::LOC_GLOBAL,
            'active' => 0, 'status' => Tiger_Model_Code::STATUS_DRAFT,
        ]);

        $res = $this->call('activate', ['code_id' => $beta]);
        $this->assertSame(0, (int) $res->result, 'a conflicting activate is refused');
        $this->assertStringContainsString('conflicts with the running set', json_encode($res->messages));
        $this->assertSame(0, (int) $this->db->fetchOne('SELECT active FROM code WHERE code_id = ?', [$beta]), 'left inactive by the self-heal');
    }

    // ----- MODULE-snippet toggle that conflicts (config flag rolled back) ------------------------

    #[Test]
    public function activating_a_conflicting_module_snippet_rolls_back_the_config_flag(): void
    {
        $this->loginAs('superadmin');
        $keyA = self::SLUG . '/dupa';
        $keyB = self::SLUG . '/dupb';

        $onA = $this->call('activate', ['code_id' => 'module:' . $keyA]);
        $this->assertSame(1, (int) $onA->result, 'the first module snippet activates cleanly');
        $this->assertTrue(Tiger_Code_Modules::isActive($keyA));

        // The second redeclares w7mdup → the union won't compile → the flag is rolled back off.
        $onB = $this->call('activate', ['code_id' => 'module:' . $keyB]);
        $this->assertSame(0, (int) $onB->result, 'the conflicting module snippet is refused');
        $this->assertStringContainsString('conflicts with the running set', json_encode($onB->messages));
        $this->assertFalse(Tiger_Code_Modules::isActive($keyB), 'its active-set flag was rolled back');
        $this->assertTrue(Tiger_Code_Modules::isActive($keyA), 'the first snippet stays active');
    }

    // ----- restore of a missing id (the catch arm) ----------------------------------------------

    #[Test]
    public function restoring_a_missing_snippet_is_a_clean_error(): void
    {
        $this->loginAs('superadmin');
        // A well-formed request (version >= 1) whose id has no versions → restoreVersion throws → caught.
        $res = $this->call('restore', ['code_id' => 'no-such-code-id', 'version' => 2]);
        $this->assertSame(0, (int) $res->result, 'a missing snippet restore fails cleanly, no crash');
    }

    // ----- footer auto-insert branch ------------------------------------------------------------

    #[Test]
    public function saving_a_js_snippet_with_footer_auto_insert_stores_the_footer_location(): void
    {
        $this->loginAs('superadmin');
        $res = $this->call('save', [
            'name'        => 'Footer JS',
            'language'    => Tiger_Model_Code::LANG_JS,
            'code'        => 'console.log("hi");',
            'auto_insert' => Tiger_Model_Code::AUTO_FOOTER,
            'active'      => '1',
            'priority'   => '20',
        ]);
        $this->assertSame(1, (int) $res->result, 'a client-JS snippet needs no PHP parse-check');
        $row = (new Tiger_Model_Code())->findById($res->data['code_id']);
        $this->assertSame(Tiger_Model_Code::LANG_JS, $row->language);
        $this->assertSame(Tiger_Model_Code::AUTO_FOOTER, $row->auto_insert, 'js honors an explicit footer placement');
    }
}
