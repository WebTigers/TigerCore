<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Application;
use Tiger_Version;
use ReflectionMethod;
use ReflectionProperty;
use Zend_Config;

/**
 * Tiger_Application — the front door (proxy normalization, path constants, the include path, the
 * config cascade, and the guarded dispatch). The full boot needs a live Zend_Application + a request,
 * so this exercises the PURE, seam-able helpers directly (reflection + a controlled `$_SERVER`) and
 * leaves the orchestration (`run()`/`boot()`/`fail()`) to the live-boot boundary — see
 * WAVE5-FINDINGS-app.md. Path constants are already defined by the test bootstrap (pointing at the
 * repo, exactly as a real boot sets them), so the constant + config helpers run against real files.
 */
#[CoversClass(Tiger_Application::class)]
final class ApplicationTest extends UnitTestCase
{
    private array $server;
    private string $includePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->server      = $_SERVER;         // proxy normalization mutates $_SERVER — snapshot it
        $this->includePath = get_include_path();
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        set_include_path($this->includePath);
        parent::tearDown();
    }

    private function app(): Tiger_Application
    {
        return new Tiger_Application(APPLICATION_ROOT);
    }

    private function call(Tiger_Application $app, string $method, array $args = [])
    {
        return (new ReflectionMethod(Tiger_Application::class, $method))->invokeArgs($app, $args);
    }

    #[Test]
    public function the_constructor_normalizes_the_root_path(): void
    {
        $app  = new Tiger_Application('C:\\sites\\my-app\\');   // backslashes + a trailing slash
        $root = (new ReflectionProperty(Tiger_Application::class, 'root'))->getValue($app);
        $this->assertSame('C:/sites/my-app', $root);
    }

    #[Test]
    public function normalize_proxy_applies_the_forwarded_client_and_https(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR']   = '203.0.113.7, 10.0.0.1, 10.0.0.2';   // client is leftmost
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
        unset($_SERVER['HTTPS'], $_SERVER['SERVER_PORT']);

        $this->call($this->app(), 'normalizeProxy');

        $this->assertSame('203.0.113.7', $_SERVER['REMOTE_ADDR'], 'the original client, not an ALB hop');
        $this->assertSame('on', $_SERVER['HTTPS']);
        $this->assertSame(443, $_SERVER['SERVER_PORT']);
        $this->assertTrue(defined('HTTPS'), 'the HTTPS boolean constant is exposed');
    }

    #[Test]
    public function normalize_proxy_leaves_plain_http_alone(): void
    {
        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_X_FORWARDED_PROTO'], $_SERVER['HTTPS']);
        $_SERVER['SERVER_PORT'] = 80;

        $this->call($this->app(), 'normalizeProxy');

        $this->assertArrayNotHasKey('HTTPS', $_SERVER, 'no forwarded-proto → HTTPS is not forced on');
        $this->assertSame(80, $_SERVER['SERVER_PORT']);
    }

    /**
     * Runs in a SEPARATE PROCESS: `defineConstants()` mints ~8 process-global constants the test
     * bootstrap deliberately leaves unset (MODULES_PATH, PROJECT_LIBRARY_PATH, …). Defining them in
     * the parent would leak — e.g. `ScanServiceTest` conditionally defines MODULES_PATH itself — so
     * this test forks. `setIncludePath()` depends on those constants, so it's asserted here too.
     */
    #[Test]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function define_constants_and_include_path_from_the_root(): void
    {
        $app = $this->app();

        $this->call($app, 'defineConstants');
        $this->assertTrue(defined('TIGER_VERSION'));
        $this->assertSame(Tiger_Version::VERSION, TIGER_VERSION, 'the canonical version IS tiger-core’s');
        $this->assertTrue(defined('MODULES_PATH'));
        $this->assertTrue(defined('APPLICATION_ENV'));

        $this->call($app, 'setIncludePath');
        $parts = explode(PATH_SEPARATOR, get_include_path());
        $this->assertSame(PROJECT_LIBRARY_PATH, $parts[0], 'the app library resolves ahead of the framework');
        $this->assertContains(TIGER_CORE_PATH . '/library', $parts);
    }

    #[Test]
    public function load_custom_hook_is_a_no_op_when_absent(): void
    {
        // No custom.php at the (repo) root → the hook simply does nothing and never fatals.
        $this->assertNull($this->call($this->app(), 'loadCustomHook'));
    }

    #[Test]
    public function build_config_merges_the_ini_cascade_into_a_read_only_config(): void
    {
        $config = $this->call($this->app(), 'buildConfig');

        $this->assertInstanceOf(Zend_Config::class, $config);
        $this->assertTrue($config->readOnly(), 'the published config is frozen');
        // core.ini is the base of the cascade — its keys are present.
        $this->assertSame('puma', (string) $config->tiger->theme);
        $this->assertNotNull($config->resources, 'the resources tree loaded from core.ini');
    }

    #[Test]
    public function update_in_progress_is_false_without_the_maintenance_flag(): void
    {
        // No var/update/.maintenance flag on disk → normal dispatch (returns false, serves nothing).
        $this->assertFalse($this->call($this->app(), '_updateInProgress'));
    }

    // ---- the update health probe ----------------------------------------------
    //
    // The vendor swap happens behind this 503 page. Tiger_Update_Core then probes the site to decide
    // whether the NEW code boots — so the page must step aside for that one request, or the probe
    // measures the holding page and every successful update is rolled back (which is exactly what
    // happened in the field before the nonce existed).

    /** Write the maintenance flag with an optional nonce; returns a cleanup callable. */
    private function flag(string $nonce = ''): callable
    {
        $dir  = APPLICATION_ROOT . '/var/update';
        $file = $dir . '/.maintenance';
        $pre  = is_dir($dir);
        @mkdir($dir, 0775, true);
        file_put_contents($file, time() . ($nonce !== '' ? ' ' . $nonce : ''));
        return function () use ($file, $dir, $pre): void {
            @unlink($file);
            if (!$pre) { @rmdir($dir); }
        };
    }

    #[Test]
    public function a_flagged_update_serves_the_maintenance_page(): void
    {
        $cleanup = $this->flag('abc123');
        unset($_SERVER['HTTP_X_TIGER_UPDATE_PROBE']);
        try {
            ob_start();
            $served = $this->call($this->app(), '_updateInProgress');
            $body   = (string) ob_get_clean();
            $this->assertTrue($served, 'an ordinary visitor is held at the maintenance page');
            $this->assertStringContainsString('Updating', $body);
        } finally { $cleanup(); }
    }

    #[Test]
    public function the_matching_probe_nonce_is_admitted_to_a_real_dispatch(): void
    {
        $cleanup = $this->flag('abc123');
        $_SERVER['HTTP_X_TIGER_UPDATE_PROBE'] = 'abc123';
        try {
            ob_start();
            $served = $this->call($this->app(), '_updateInProgress');
            $body   = (string) ob_get_clean();
            $this->assertFalse($served, 'the health probe must reach the NEW code, not the holding page');
            $this->assertSame('', $body);
        } finally { $cleanup(); }
    }

    #[Test]
    public function a_wrong_probe_nonce_is_still_held_at_the_maintenance_page(): void
    {
        // The nonce is a targeted exception, not a public bypass — a guess must not get through.
        $cleanup = $this->flag('abc123');
        $_SERVER['HTTP_X_TIGER_UPDATE_PROBE'] = 'not-the-nonce';
        try {
            ob_start();
            $served = $this->call($this->app(), '_updateInProgress');
            ob_end_clean();
            $this->assertTrue($served);
        } finally { $cleanup(); }
    }

    #[Test]
    public function a_probe_header_without_a_nonce_in_the_flag_is_held(): void
    {
        // A nonce-less flag is not an open door for an arbitrary header.
        $cleanup = $this->flag();
        $_SERVER['HTTP_X_TIGER_UPDATE_PROBE'] = 'anything';
        unset($_SERVER['HTTP_USER_AGENT']);
        try {
            ob_start();
            $served = $this->call($this->app(), '_updateInProgress');
            ob_end_clean();
            $this->assertTrue($served);
        } finally { $cleanup(); }
    }

    #[Test]
    public function a_legacy_updater_probe_is_admitted_when_the_flag_has_no_nonce(): void
    {
        // The updater that RUNS an update is the old one being replaced. Without this bridge an
        // install on a pre-nonce version could never self-update to the release that fixes
        // self-updating — on a product whose promise is "no shell".
        $cleanup = $this->flag();                       // legacy flag: timestamp only
        unset($_SERVER['HTTP_X_TIGER_UPDATE_PROBE']);
        $_SERVER['HTTP_USER_AGENT'] = 'Tiger_Update health';
        try {
            ob_start();
            $served = $this->call($this->app(), '_updateInProgress');
            ob_end_clean();
            $this->assertFalse($served);
        } finally { $cleanup(); }
    }

    #[Test]
    public function the_legacy_bridge_closes_once_the_flag_carries_a_nonce(): void
    {
        // A nonce-capable updater is held to the nonce — the UA alone stops being enough.
        $cleanup = $this->flag('abc123');
        unset($_SERVER['HTTP_X_TIGER_UPDATE_PROBE']);
        $_SERVER['HTTP_USER_AGENT'] = 'Tiger_Update health';
        try {
            ob_start();
            $served = $this->call($this->app(), '_updateInProgress');
            ob_end_clean();
            $this->assertTrue($served);
        } finally { $cleanup(); }
    }
}
