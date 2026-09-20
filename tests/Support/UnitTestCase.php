<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Support;

use PHPUnit\Framework\TestCase;
use Zend_Config;
use Zend_Db_Adapter_Abstract;
use Zend_Db_Table_Abstract;
use Zend_Registry;

/**
 * Base for unit tests that need no external service (no DB, no network).
 *
 * Its one job is to keep the process-global state that ZF1/Tiger reach for — chiefly the
 * `Zend_Config` in `Zend_Registry` that `Tiger_Crypto`/`Tiger_Security`/etc. read — isolated
 * between tests. Every test starts from a clean registry and gets it torn down after, so ordering
 * never leaks a key, a pepper, or a config value from one case into the next.
 *
 * The default `Zend_Db` table adapter is the same kind of process-global state, but it lives on a
 * `Zend_Db_Table_Abstract` static — NOT in `Zend_Registry` — so unsetting the registry does not
 * reach it. An earlier IntegrationTestCase registers a shared adapter once per process and never
 * clears it, so a unit test running after one would find that adapter still installed: a
 * `Tiger_Model_*` query a unit-tested class runs as its "no DB → documented fallback" path would
 * instead SUCCEED against the shared test DB and return live rows, making the unit result depend on
 * test order (TIGER-104: RegistryTest/DependencyTest passed alone but failed in the full batch).
 * So we clear the default adapter for the duration of each unit test and restore whatever was there
 * — a no-DB unit test needs none, and a later integration test still finds the adapter it wired.
 */
abstract class UnitTestCase extends TestCase
{
    /** The default table adapter in place before this test (restored in tearDown). */
    private ?Zend_Db_Adapter_Abstract $savedDefaultDbAdapter = null;

    protected function setUp(): void
    {
        parent::setUp();
        // Capture the process-global default DB adapter, then clear it so no-DB fallbacks fire
        // deterministically. Captured before the registry reset because a resolved adapter is a
        // plain static read that does not depend on the registry.
        $this->savedDefaultDbAdapter = Zend_Db_Table_Abstract::getDefaultAdapter();
        Zend_Db_Table_Abstract::setDefaultAdapter(null);
        Zend_Registry::_unsetInstance();
    }

    protected function tearDown(): void
    {
        Zend_Registry::_unsetInstance();
        // Put back whatever adapter an earlier (integration) test had installed, so a later
        // integration test in the same process still finds the shared adapter it wired once.
        Zend_Db_Table_Abstract::setDefaultAdapter($this->savedDefaultDbAdapter);
        $this->savedDefaultDbAdapter = null;
        parent::tearDown();
    }

    /**
     * Install a `Zend_Config` into the registry from a nested array, exactly where the platform
     * expects it (`Zend_Registry::get('Zend_Config')`). Pass e.g.
     * `['tiger' => ['crypto' => ['key' => $b64]]]`.
     */
    protected function setConfig(array $data): Zend_Config
    {
        $config = new Zend_Config($data, true);
        Zend_Registry::set('Zend_Config', $config);
        return $config;
    }

    /** Convenience: register a valid test crypto key + security pepper in one call. */
    protected function setCryptoConfig(?string $key = null, ?string $pepper = null): void
    {
        $this->setConfig([
            'tiger' => [
                'crypto'   => ['key' => $key ?? base64_encode(str_repeat("\x11", 32))],
                'security' => ['pepper' => $pepper ?? base64_encode(str_repeat("\x22", 32))],
            ],
        ]);
    }
}
