<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Application;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Application_Bootstrap;
use ReflectionMethod;
use Zend_Application;
use Zend_Auth;
use Zend_Auth_Storage_NonPersistent;
use Zend_Config;

/**
 * Tiger_Application_Bootstrap — the base app bootstrap. Its `_init*` methods are boot orchestration
 * (they wire ZF1 resources + plugins and need a live `Zend_Application` run — the expected coverage
 * gap, noted in WAVE5-FINDINGS-app.md). This covers the PURE, seam-able helpers those methods lean on:
 * the dot-notation config folder, the bearer-token sniff, the current actor/org readers, and the
 * language-file cascade — reached via reflection on a bootstrap built over a bare Zend_Application.
 */
#[CoversClass(Tiger_Application_Bootstrap::class)]
final class BootstrapHelpersTest extends UnitTestCase
{
    private Tiger_Application_Bootstrap $bootstrap;
    private array $server;

    protected function setUp(): void
    {
        parent::setUp();
        $this->server    = $_SERVER;
        $this->bootstrap = new Tiger_Application_Bootstrap(new Zend_Application('testing'));
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->server;
        Zend_Auth::getInstance()->clearIdentity();
        parent::tearDown();
    }

    private function call(string $method, array $args = [])
    {
        return (new ReflectionMethod(Tiger_Application_Bootstrap::class, $method))->invokeArgs($this->bootstrap, $args);
    }

    /** Sign a synthetic identity into the process singleton (in-memory, no session). */
    private function identify(?array $identity): void
    {
        $auth = Zend_Auth::getInstance();
        $auth->setStorage(new Zend_Auth_Storage_NonPersistent());
        if ($identity !== null) {
            $auth->getStorage()->write((object) $identity);
        }
    }

    #[Test]
    public function set_nested_config_folds_a_dot_key_into_the_tree(): void
    {
        $config = new Zend_Config(['tiger' => ['theme' => 'puma']], true);

        $this->call('_setNestedConfig', [$config, 'tiger.skin', 'jaguar']);
        $this->call('_setNestedConfig', [$config, 'tiger.theme', 'aurora']);        // overwrite a leaf
        $this->call('_setNestedConfig', [$config, 'a.b.c.d', 'deep']);              // create the whole branch

        $this->assertSame('jaguar', $config->tiger->skin);
        $this->assertSame('aurora', $config->tiger->theme, 'an existing leaf is overwritten (DB tier wins)');
        $this->assertSame('deep', $config->a->b->c->d);
    }

    #[Test]
    public function bearer_request_detects_a_tiger_token(): void
    {
        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer tgr_abc123';
        $this->assertTrue($this->call('_bearerRequest'));

        $_SERVER['HTTP_AUTHORIZATION'] = 'Bearer someothertoken';
        $this->assertFalse($this->call('_bearerRequest'), 'a non-tgr bearer is not a Tiger token');

        unset($_SERVER['HTTP_AUTHORIZATION']);
        $this->assertFalse($this->call('_bearerRequest'), 'no Authorization header → not a token request');

        // The proxied header variant (Apache rewrites Authorization into REDIRECT_*).
        $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] = 'Bearer  tgr_x';
        $this->assertTrue($this->call('_bearerRequest'));
    }

    #[Test]
    public function current_org_and_user_come_from_the_identity(): void
    {
        $this->identify(['user_id' => 'u-1', 'org_id' => 'o-9']);
        $this->assertSame('o-9', $this->call('_currentOrgId'));
        $this->assertSame('u-1', $this->call('_currentUserId'));
    }

    #[Test]
    public function current_org_and_user_are_null_without_an_identity(): void
    {
        $this->identify(null);
        $this->assertNull($this->call('_currentOrgId'));
        $this->assertNull($this->call('_currentUserId'));
    }

    #[Test]
    public function current_org_is_null_when_the_identity_carries_no_org(): void
    {
        $this->identify(['user_id' => 'u-1']);   // no org_id
        $this->assertNull($this->call('_currentOrgId'));
        $this->assertSame('u-1', $this->call('_currentUserId'));
    }

    #[Test]
    public function language_files_return_the_existing_cascade_members(): void
    {
        // Constants point at the repo (test bootstrap), so the core `en` file resolves; every returned
        // path is a real file (missing cascade members are filtered out).
        $files = $this->call('_languageFiles', ['en']);
        $this->assertIsArray($files);
        $this->assertNotEmpty($files);
        foreach ($files as $f) {
            $this->assertFileExists($f);
        }
        $this->assertContains(TIGER_CORE_PATH . '/core/languages/en/core.php', $files, 'the core language file leads the cascade');
    }

    /**
     * A presented session id whose file cannot be read (cPanel's shared session dir after an account is
     * recreated) must be dropped BEFORE session_start — PHP defines SID even on a failed start, so there
     * is no retry afterwards. A readable file, a missing file, or a malformed id are left alone.
     */
    #[Test]
    public function an_unreadable_presented_session_file_is_dropped_before_start(): void
    {
        $dir = sys_get_temp_dir() . '/tiger-sess-' . bin2hex(random_bytes(4)); mkdir($dir, 0700);
        $log = ini_set('error_log', $dir . '/error.log');   // the guard logs what it dropped; keep that off the test output
        try {
            $bad = str_repeat('b', 26); file_put_contents("$dir/sess_$bad", 'x'); chmod("$dir/sess_$bad", 0000);
            $this->assertTrue(Tiger_Application_Bootstrap::dropUnreadableSessionId($bad, '5;' . $dir), 'unreadable → dropped ("N;/path" form parsed)');
            $this->assertTrue(Tiger_Application_Bootstrap::dropUnreadableSessionId($bad, '5;0600;' . $dir), '"N;mode;/path" form parsed');
            $good = str_repeat('c', 26); file_put_contents("$dir/sess_$good", 'x');
            $this->assertFalse(Tiger_Application_Bootstrap::dropUnreadableSessionId($good, $dir), 'readable → kept');
            $this->assertFalse(Tiger_Application_Bootstrap::dropUnreadableSessionId(str_repeat('d', 26), $dir), 'no such file → kept (PHP creates it)');
            $this->assertFalse(Tiger_Application_Bootstrap::dropUnreadableSessionId('../../etc/passwd', $dir), 'junk never reaches the filesystem');
            $this->assertFalse(Tiger_Application_Bootstrap::dropUnreadableSessionId('', $dir));
            $this->assertStringContainsString('not readable', (string) file_get_contents($dir . '/error.log'), 'the drop is logged');
        } finally {
            ini_set('error_log', (string) $log);
            @chmod("$dir/sess_$bad", 0600); array_map('unlink', glob("$dir/sess_*") ?: []); @unlink($dir . '/error.log'); @rmdir($dir);
        }
    }
}
