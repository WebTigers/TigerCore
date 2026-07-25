<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Vendor_Environment;

/**
 * Tiger_Vendor_Environment — the host-capability probe Tiger_Vendor consults before choosing a
 * provisioning tier (Composer / bundle / tarball). Every method here reads the local process +
 * filesystem only (no network), so the whole surface is unit-testable: the exec/proc_open detection
 * (honoring `disable_functions`), the composer-binary resolution, the vendor/store path + writability
 * probes, the composed `composerUsable()` verdict, and the operator `report()`. The one non-
 * deterministic axis — whether *this* box actually has Composer — is asserted structurally (type +
 * the invariant that ties the pieces together), never against a fixed yes/no.
 */
#[CoversClass(Tiger_Vendor_Environment::class)]
final class VendorEnvironmentTest extends UnitTestCase
{
    #[Test]
    public function exec_enabled_reflects_proc_open_or_exec_availability(): void
    {
        // The harness runs a normal CLI PHP with proc_open available, so this is true here; assert the
        // contract (a bool that agrees with the underlying function availability) rather than a literal.
        $expected = VendorEnvProbe::fnEnabled('proc_open') || VendorEnvProbe::fnEnabled('exec');
        $this->assertSame($expected, Tiger_Vendor_Environment::execEnabled());
    }

    #[Test]
    public function function_enabled_is_false_for_a_missing_function_and_honors_the_real_registry(): void
    {
        $this->assertFalse(VendorEnvProbe::fnEnabled('a_function_that_does_not_exist_zzz'));
        // A function that exists and isn't in disable_functions resolves true (json_encode is always there).
        $this->assertTrue(VendorEnvProbe::fnEnabled('json_encode'));
    }

    #[Test]
    public function app_root_is_the_defined_application_root(): void
    {
        $this->assertSame(APPLICATION_ROOT, Tiger_Vendor_Environment::appRoot());
        $this->assertDirectoryExists(Tiger_Vendor_Environment::appRoot());
    }

    #[Test]
    public function store_dir_sits_beside_composers_vendor(): void
    {
        $this->assertSame(APPLICATION_ROOT . '/vendor-libs', Tiger_Vendor_Environment::storeDir());
    }

    #[Test]
    public function vendor_and_store_writability_are_booleans(): void
    {
        $this->assertIsBool(Tiger_Vendor_Environment::vendorWritable());
        $this->assertIsBool(Tiger_Vendor_Environment::storeWritable());
    }

    #[Test]
    public function composer_binary_is_a_string_or_null(): void
    {
        $bin = Tiger_Vendor_Environment::composerBinary();
        $this->assertTrue($bin === null || is_string($bin));
        if ($bin !== null) {
            $this->assertNotSame('', $bin);
        }
    }

    #[Test]
    public function composer_usable_is_the_conjunction_of_a_binary_and_a_writable_vendor(): void
    {
        $expected = Tiger_Vendor_Environment::composerBinary() !== null
            && Tiger_Vendor_Environment::vendorWritable();
        $this->assertSame($expected, Tiger_Vendor_Environment::composerUsable());
    }

    #[Test]
    public function report_carries_every_capability_key_with_the_right_types(): void
    {
        $r = Tiger_Vendor_Environment::report();

        $this->assertSame(
            ['exec_enabled', 'composer', 'composer_usable', 'vendor_writable', 'store', 'store_writable'],
            array_keys($r)
        );
        $this->assertIsBool($r['exec_enabled']);
        $this->assertTrue($r['composer'] === null || is_string($r['composer']));
        $this->assertIsBool($r['composer_usable']);
        $this->assertIsBool($r['vendor_writable']);
        $this->assertSame(Tiger_Vendor_Environment::storeDir(), $r['store']);
        $this->assertIsBool($r['store_writable']);
    }

    #[Test]
    public function phar_candidates_point_at_the_app_root_composer_phar_spots(): void
    {
        $cands = VendorEnvProbe::pharCandidates();
        $this->assertSame([
            APPLICATION_ROOT . '/composer.phar',
            APPLICATION_ROOT . '/bin/composer.phar',
        ], $cands);
    }
}

/** Test seam: expose Tiger_Vendor_Environment's protected capability helpers. */
final class VendorEnvProbe extends Tiger_Vendor_Environment
{
    public static function fnEnabled(string $fn): bool { return self::_functionEnabled($fn); }
    public static function pharCandidates(): array { return self::_pharCandidates(); }
}
