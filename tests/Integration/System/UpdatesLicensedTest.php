<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\System;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use System_Service_Updates;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Crypto_Signature;
use Tiger_License_Authority;
use Tiger_License_Checker;
use Tiger_License_Pass;
use Tiger_License_Store;
use Tiger_Module_Discovery;
use Tiger_Module_Github;

/**
 * A LICENSED module is updated only through its authority (TIGER-496).
 *
 * Before this, the updater sent every non-provider module to installFromUrl — an unsigned GitHub download the
 * installer refuses for a licensed manifest — so no TigerPASS module could ever be updated from the Module
 * Manager. Now a licensed module updates under the install's TigerPASS key (or its own license) through
 * installFromAuthority, which verifies the signed artifact before extracting; with no license it is refused,
 * and nothing is fetched at all. The authority and the download are injected transports — no network.
 */
#[CoversClass(System_Service_Updates::class)]
#[CoversClass(Tiger_License_Pass::class)]
final class UpdatesLicensedTest extends IntegrationTestCase
{
    private const AUTHORITY = 'https://store.example/shop/authority';
    private const SLUG      = 'w5pass';

    private string $tmp = '';
    private array $authorityCalls = [];
    private array $downloads = [];
    private $configBefore = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/tiger_updlic_' . getmypid() . '_' . bin2hex(random_bytes(4));
        @mkdir($this->tmp, 0775, true);
        $this->configBefore = \Zend_Registry::isRegistered('Zend_Config') ? \Zend_Registry::get('Zend_Config') : null;
        $base = $this->configBefore ? $this->configBefore->toArray() : [];
        $cfg  = new \Zend_Config($base, true);
        // The updater logs each step; the null sink keeps the strict-output suite from flagging it as risky.
        $cfg->merge(new \Zend_Config(['tiger' => ['pass' => ['authority' => self::AUTHORITY], 'log' => ['writer' => 'null']]], true));
        \Zend_Registry::set('Zend_Config', $cfg);
        \Tiger_Log::reset();

        Tiger_License_Checker::setStore(new class implements Tiger_License_Store {
            private array $rows = [];
            public function get(string $slug): ?array { return $this->rows[$slug] ?? null; }
            public function put(string $slug, array $record): void { $this->rows[$slug] = $record; }
            public function forget(string $slug): void { unset($this->rows[$slug]); }
        });
        Tiger_License_Authority::setTransport(function (string $url, array $payload): ?array {
            $this->authorityCalls[] = ['url' => $url] + $payload;
            return null;   // refuse unless a test says otherwise
        });
        Tiger_Module_Github::setTransport(function (string $url) {
            $this->downloads[] = $url;
            return ['code' => 404, 'body' => ''];
        });

        $this->plantInstalled('1.0.0');
    }

    protected function tearDown(): void
    {
        $this->rrmdir(APPLICATION_PATH . '/modules/' . self::SLUG);
        $link = APPLICATION_ROOT . '/public/_modules/' . self::SLUG;
        if (is_link($link)) { @unlink($link); } elseif (is_dir($link)) { $this->rrmdir($link); }
        @rmdir(APPLICATION_PATH . '/modules');
        @rmdir(APPLICATION_ROOT . '/public/_modules');
        $this->rrmdir($this->tmp);
        Tiger_License_Checker::_reset();
        Tiger_License_Authority::_reset();
        Tiger_Module_Github::setTransport(null);
        if ($this->configBefore !== null) { \Zend_Registry::set('Zend_Config', $this->configBefore); }
        \Tiger_Log::reset();
        parent::tearDown();
    }

    // ---- the resolver -----------------------------------------------------------------------

    #[Test]
    public function a_pass_module_resolves_to_the_tigerpass_key_before_its_own_copy(): void
    {
        $manifest = $this->manifest('1.0.0');
        $this->assertNull(Tiger_License_Pass::licenseFor(self::SLUG, $manifest), 'no license held: none');

        Tiger_License_Checker::remember(self::SLUG, ['key' => 'OWN-KEY', 'authority' => self::AUTHORITY, 'vendor' => 'WebTigers/TigerVendor', 'public_key' => 'pk']);
        $this->assertSame('OWN-KEY', Tiger_License_Pass::licenseFor(self::SLUG, $manifest)['key'], 'own record when no TigerPASS');

        Tiger_License_Checker::remember(Tiger_License_Pass::SLUG, ['key' => 'PASS-KEY', 'authority' => self::AUTHORITY, 'vendor' => 'WebTigers/TigerVendor', 'public_key' => 'pk']);
        $lic = Tiger_License_Pass::licenseFor(self::SLUG, $manifest);
        $this->assertSame(['PASS-KEY', 'pass'], [$lic['key'], $lic['via']], 'TigerPASS wins, so a renewed key is never shadowed by a stale copy');
    }

    #[Test]
    public function the_tigerpass_key_never_covers_another_vendors_module_or_a_free_one(): void
    {
        Tiger_License_Checker::remember(Tiger_License_Pass::SLUG, ['key' => 'PASS-KEY', 'authority' => self::AUTHORITY, 'vendor' => 'WebTigers/TigerVendor', 'public_key' => 'pk']);

        $other = ['slug' => 'acme', 'pricing' => ['model' => 'licensed', 'authority' => 'https://acme.example/authority', 'vendor' => 'acme/TigerVendor']];
        $this->assertNull(Tiger_License_Pass::licenseFor('acme', $other), 'a per-vendor module needs its own license');
        $this->assertNull(Tiger_License_Pass::licenseFor('free', ['slug' => 'free']), 'a free module has no license at all');
    }

    // ---- the updater ------------------------------------------------------------------------

    #[Test]
    public function with_no_license_the_update_is_refused_and_nothing_is_fetched(): void
    {
        $res = $this->applyUpdate();

        $this->assertFalse($res['ok']);
        $this->assertContains('license', array_column($res['log'], 'step'));
        $this->assertSame([], $this->authorityCalls, 'the authority is not asked without a key');
        $this->assertSame([], $this->downloads, 'and nothing is downloaded — above all not an unsigned GitHub release');
        $this->assertSame('1.0.0', $this->installedVersion());
    }

    #[Test]
    public function a_pass_module_updates_through_the_authority_under_the_tigerpass_key(): void
    {
        $keys = Tiger_Crypto_Signature::generateKeypair();
        Tiger_License_Checker::remember(Tiger_License_Pass::SLUG, ['key' => 'PASS-KEY', 'authority' => self::AUTHORITY, 'vendor' => 'WebTigers/TigerVendor', 'public_key' => $keys['public_key']]);
        $this->serveSignedRelease('1.1.0', $keys['secret_key']);

        $res = $this->applyUpdate();

        $this->assertTrue($res['ok'], json_encode($res['log']));
        $this->assertSame('1.1.0', $this->installedVersion());
        $this->assertCount(1, $this->authorityCalls);
        $this->assertSame(['PASS-KEY', self::SLUG], [$this->authorityCalls[0]['key'], $this->authorityCalls[0]['product']], 'the authority was asked, with the TigerPASS key, for this product');
        $this->assertSame(['https://dl.example/' . self::SLUG . '.zip'], $this->downloads, 'only the authority-minted signed URL was fetched');
    }

    #[Test]
    public function a_release_that_fails_its_signature_is_refused_and_the_installed_version_stays(): void
    {
        $trusted = Tiger_Crypto_Signature::generateKeypair();
        $forger  = Tiger_Crypto_Signature::generateKeypair();
        Tiger_License_Checker::remember(Tiger_License_Pass::SLUG, ['key' => 'PASS-KEY', 'authority' => self::AUTHORITY, 'vendor' => 'WebTigers/TigerVendor', 'public_key' => $trusted['public_key']]);
        $this->serveSignedRelease('1.1.0', $forger['secret_key']);   // signed by a key the install does not trust

        $res = $this->applyUpdate();

        $this->assertFalse($res['ok']);
        $this->assertSame('1.0.0', $this->installedVersion(), 'nothing was placed');
    }

    // ---- helpers ----------------------------------------------------------------------------

    private function manifest(string $version): array
    {
        return ['slug' => self::SLUG, 'name' => 'W5 Pass', 'version' => $version,
                'pricing' => ['model' => 'licensed', 'plan' => 'tigerpass', 'authority' => self::AUTHORITY, 'vendor' => 'WebTigers/TigerVendor']];
    }

    private function plantInstalled(string $version): void
    {
        $dir = APPLICATION_PATH . '/modules/' . self::SLUG;
        @mkdir($dir, 0775, true);
        file_put_contents($dir . '/module.json', json_encode($this->manifest($version)));
    }

    private function installedVersion(): string
    {
        return (string) (Tiger_Module_Discovery::manifestFor(self::SLUG)['version'] ?? '');
    }

    /** The authority authorizes and mints a signed URL; the download transport serves the signed zip there. */
    private function serveSignedRelease(string $version, string $secretKey): void
    {
        $zip = $this->tmp . '/' . self::SLUG . '-' . $version . '.zip';
        $z = new \ZipArchive();
        $z->open($zip, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $z->addFromString(self::SLUG . '/module.json', json_encode($this->manifest($version)));
        $z->close();
        $bytes = (string) file_get_contents($zip);
        $sig   = Tiger_Crypto_Signature::signFile($zip, $secretKey);
        $sha   = Tiger_Crypto_Signature::sha256File($zip);
        $url   = 'https://dl.example/' . self::SLUG . '.zip';

        Tiger_License_Authority::setTransport(function (string $u, array $payload) use ($url, $sig, $sha, $version): ?array {
            $this->authorityCalls[] = ['url' => $u] + $payload;
            return ['url' => $url, 'signature' => $sig, 'sha256' => $sha, 'version' => $version];
        });
        Tiger_Module_Github::setTransport(function (string $u) use ($url, $bytes) {
            $this->downloads[] = $u;
            return $u === $url ? ['code' => 200, 'body' => $bytes] : ['code' => 404, 'body' => ''];
        });
    }

    private function applyUpdate(): array
    {
        $svc = (new \ReflectionClass(System_Service_Updates::class))->newInstanceWithoutConstructor();
        $m   = new ReflectionMethod(System_Service_Updates::class, '_applyOne');
        return $m->invoke($svc, ['type' => 'module', 'slug' => self::SLUG, 'name' => 'W5 Pass', 'installed' => '1.0.0',
            'latest' => '1.1.0', 'repository' => 'https://github.com/example/w5pass', 'ref' => 'v1.1.0']);
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) { return; }
        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') { continue; }
            $p = $dir . '/' . $item;
            (is_dir($p) && !is_link($p)) ? $this->rrmdir($p) : @unlink($p);
        }
        @rmdir($dir);
    }
}
