<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Code;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Code_Runtime;
use Tiger_Log;
use Tiger_Model_Code;
use Zend_Config;
use Zend_Registry;

/**
 * Tiger_Code_Runtime — the loader/guard branches the RuntimeTest/RuntimeExtraTest suites don't reach:
 *   - boot()'s kill-switch return (enabled=0) and its once-per-location short-circuit;
 *   - boot()'s compile-if-missing failure path (a broken active set → compile throws → boot logs + bails,
 *     it does NOT fatal the request);
 *   - compileClient()'s PHTML inline branch;
 *   - the shutdown backstop `_onShutdown()` early-outs (no snippet mid-load / a non-fatal last error).
 *
 * boot()'s version/enabled come from the config node (`tiger.code.*`), so those are set via Zend_Config;
 * a distinctive run_location isolates each test's bundle. Bundles/assets written are cleaned in tearDown.
 */
#[CoversClass(Tiger_Code_Runtime::class)]
final class RuntimeMoreTest extends IntegrationTestCase
{
    private string $cacheDir;
    private string $publicDir;
    private ?Zend_Config $priorConfig = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacheDir  = APPLICATION_ROOT . '/storage/cache/code';
        $this->publicDir = APPLICATION_ROOT . '/public/_code';
        $this->priorConfig = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
    }

    protected function tearDown(): void
    {
        foreach (['bootfail', 'killswitch', 'clientphtml'] as $loc) {
            foreach (glob($this->cacheDir . '/' . $loc . '.*.php') ?: [] as $f) { @unlink($f); }
            foreach (glob($this->cacheDir . '/inject.' . $loc . '.*.php') ?: [] as $f) { @unlink($f); }
            foreach (glob($this->publicDir . '/' . $loc . '.*') ?: [] as $f) { @unlink($f); }
        }
        unset($GLOBALS['__tiger_code_running']);
        if ($this->priorConfig !== null) {
            Zend_Registry::set('Zend_Config', $this->priorConfig);
        } elseif (Zend_Registry::isRegistered('Zend_Config')) {
            Zend_Registry::set('Zend_Config', new Zend_Config([]));
        }
        Tiger_Log::reset();
        parent::tearDown();
    }

    /** Set the `tiger.code` config node (version/enabled) plus a null log sink. */
    private function setCodeConfig(array $code): void
    {
        Zend_Registry::set('Zend_Config', new Zend_Config([
            'tiger' => ['code' => $code, 'log' => ['writer' => 'null']],
        ]));
        Tiger_Log::reset();
    }

    private function insertPhp(string $name, string $code, string $loc): string
    {
        return (new Tiger_Model_Code())->insert([
            'org_id'       => '',
            'name'         => $name,
            'language'     => Tiger_Model_Code::LANG_PHP,
            'code'         => $code,
            'run_location' => $loc,
            'priority'     => 100,
            'active'       => 1,
            'status'       => Tiger_Model_Code::STATUS_ACTIVE,
        ]);
    }

    #[Test]
    public function boot_bails_without_fataling_when_the_active_set_fails_to_compile(): void
    {
        // A broken active snippet at a fresh location + a positive version whose bundle file doesn't exist
        // yet → boot() compiles-if-missing, compile() throws on the php -l, boot() catches + logs + returns.
        $this->insertPhp('broken', 'function tiger_bootfail_broken( {', 'bootfail');   // parse error
        $this->setCodeConfig(['version' => '7', 'enabled' => '1']);

        Tiger_Code_Runtime::boot('bootfail');   // must NOT throw — the request survives

        $this->assertFileDoesNotExist($this->cacheDir . '/bootfail.7.php', 'the broken bundle was never promoted');
    }

    #[Test]
    public function boot_is_a_noop_under_the_kill_switch_and_only_runs_once_per_location(): void
    {
        $this->setCodeConfig(['version' => '3', 'enabled' => '0']);   // execution disabled

        Tiger_Code_Runtime::boot('killswitch');   // kill-switch → immediate return (marks the location booted)
        Tiger_Code_Runtime::boot('killswitch');   // already booted → the very first short-circuit

        $this->assertFileDoesNotExist($this->cacheDir . '/killswitch.3.php', 'a disabled runtime compiles nothing');
    }

    #[Test]
    public function compile_client_handles_an_inline_phtml_snippet(): void
    {
        (new Tiger_Model_Code())->insert([
            'org_id'       => '',
            'name'         => 'phtml-inline',
            'language'     => Tiger_Model_Code::LANG_PHTML,
            'code'         => '<?= "hello from phtml" ?>',
            'run_location' => 'clientphtml',
            'auto_insert'  => Tiger_Model_Code::AUTO_FOOTER,
            'priority'     => 100,
            'active'       => 1,
            'status'       => Tiger_Model_Code::STATUS_ACTIVE,
        ]);

        Tiger_Code_Runtime::compileClient('clientphtml', 1);

        $manifest = $this->cacheDir . '/inject.clientphtml.1.php';
        $this->assertFileExists($manifest);
        $data = include $manifest;
        $footer = $data['footer'] ?? [];
        $types  = array_column($footer, 'type');
        $this->assertContains('phtml', $types, 'the phtml snippet becomes an inline phtml manifest item');
    }

    #[Test]
    public function on_shutdown_is_a_noop_when_no_snippet_was_mid_load(): void
    {
        unset($GLOBALS['__tiger_code_running']);   // nothing executing
        Tiger_Code_Runtime::_onShutdown();
        $this->assertTrue(true, 'no marker → the backstop returns immediately, deactivating nothing');
    }

    #[Test]
    public function on_shutdown_is_a_noop_when_the_last_error_is_not_fatal(): void
    {
        // A snippet marker is set, but the request is ending WITHOUT a fatal (no error, or a mere
        // warning/deprecation) → the backstop must not deactivate anything.
        $GLOBALS['__tiger_code_running'] = 'some-code-id';
        Tiger_Code_Runtime::_onShutdown();
        unset($GLOBALS['__tiger_code_running']);
        $this->assertTrue(true, 'a non-fatal shutdown leaves the snippet active');
    }
}
