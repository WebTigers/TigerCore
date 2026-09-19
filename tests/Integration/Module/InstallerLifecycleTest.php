<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Integration\Module;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tiger\Tests\Support\IntegrationTestCase;
use Tiger_Crypto_Signature;
use Tiger_License_Authority;
use Tiger_Model_Module;
use Tiger_Module_Installer;

/**
 * Tiger_Module_Installer — the install/update/remove LIFECYCLE end to end, against a real DB + filesystem.
 * The extract guard, manifest/slug gatekeepers, and the signature-material verifier are pinned by the unit
 * suite (InstallerExtractTest / InstallerManifestTest / InstallerSignatureTest); this drives the parts that
 * need the `module` table and the on-disk placement:
 *
 *   - installFromTarball / installFromUpload: extract → place into modules/ → migrate → record; the
 *     already-installed guard and the force-update path; the licensed-must-be-signed gate (a real Ed25519
 *     keypair signs a real fixture tarball → the happy path installs; the SAME manifest UNSIGNED is refused);
 *     the PHP hard-requires gate.
 *   - remove(): deletes files + the row, and refuses a non-installer-managed slug.
 *   - migrateModule() / publishAssets() / unpublishAssets(): the activate-time capability hooks.
 *   - installFromAuthority(): the guard + the two reachable failure branches (authority refuses; the signed
 *     download URL is unreachable), driven through the injected authority transport with NO real network.
 *
 * Everything the DB writes rolls back with the per-test transaction; the physical module dirs + published
 * symlinks are NOT transactional, so each test cleans up the slugs + links it created.
 */
#[CoversClass(Tiger_Module_Installer::class)]
final class InstallerLifecycleTest extends IntegrationTestCase
{
    private string $tmp = '';
    /** Slugs whose modules/<slug> dir + public/_modules/<slug> link must be removed after the test. */
    private array $installed = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmp = sys_get_temp_dir() . '/tiger_instlife_' . getmypid() . '_' . bin2hex(random_bytes(4));
        @mkdir($this->tmp, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->installed as $slug) {
            $this->rrmdir(APPLICATION_PATH . '/modules/' . $slug);
            $link = APPLICATION_ROOT . '/public/_modules/' . $slug;
            if (is_link($link)) { @unlink($link); } elseif (is_dir($link)) { $this->rrmdir($link); }
        }
        // Prune fixture scaffolding if now empty.
        @rmdir(APPLICATION_PATH . '/modules');
        @rmdir(APPLICATION_ROOT . '/public/_modules');
        $this->rrmdir($this->tmp);
        Tiger_License_Authority::_reset();
        parent::tearDown();
    }

    // ---- installFromTarball / installFromUpload --------------------------------

    #[Test]
    public function installsAFreeModuleFromATarballAndRecordsIt(): void
    {
        $tar = $this->makeModulePackage('w4free', ['name' => 'W4 Free', 'version' => '1.0.0']);

        $r = Tiger_Module_Installer::installFromUpload($tar);
        $this->installed[] = 'w4free';

        $this->assertSame('w4free', $r['slug']);
        $this->assertSame('W4 Free', $r['name']);
        $this->assertSame('1.0.0', $r['version']);
        $this->assertDirectoryExists(APPLICATION_PATH . '/modules/w4free');
        $this->assertFileExists(APPLICATION_PATH . '/modules/w4free/module.json');

        $row = (new Tiger_Model_Module())->bySlug('w4free');
        $this->assertNotNull($row, 'the install is recorded in the module table');
        $this->assertSame('1.0.0', (string) $row->version);
        $this->assertSame(1, (int) $row->active);
        $this->assertSame(Tiger_Model_Module::SOURCE_UPLOAD, $row->source);
        $this->assertFalse($r['updated'], 'a first install is not an update');
    }

    /**
     * TIGER-169 (B2): re-uploading an already-installed key must NEVER be a silent no-op. The same or an
     * older version without `force` is refused with the recoverable E_ALREADY_INSTALLED code (the service
     * turns that into a specific message); a strictly NEWER upload updates in place with no force (the
     * iterate/upgrade loop); and `force` always updates.
     */
    #[Test]
    public function reinstallingIsExplicit_NoisyNotSilent(): void
    {
        $this->installFixture('w4force', ['version' => '1.0.0']);

        // Same version, no force → refused with the NAMED, recoverable code — not a silent no-op or a
        // generic failure. The existing install is untouched.
        try {
            Tiger_Module_Installer::installFromUpload($this->makeModulePackage('w4force', ['version' => '1.0.0']));
            $this->fail('re-uploading the same version without force should throw');
        } catch (RuntimeException $e) {
            $this->assertSame(Tiger_Module_Installer::E_ALREADY_INSTALLED, $e->getCode(), 'a distinct, recoverable code');
            $this->assertStringContainsString('already installed', $e->getMessage());
        }
        $this->assertSame('1.0.0', (string) (new Tiger_Model_Module())->bySlug('w4force')->version, 'unchanged after a refusal');

        // An OLDER upload is also refused (never a downgrade-by-accident).
        try {
            Tiger_Module_Installer::installFromUpload($this->makeModulePackage('w4force', ['version' => '0.9.0']));
            $this->fail('an older version without force should throw');
        } catch (RuntimeException $e) {
            $this->assertSame(Tiger_Module_Installer::E_ALREADY_INSTALLED, $e->getCode());
        }

        // A strictly NEWER upload updates in place WITHOUT force (the B2 iterate/upgrade loop).
        $r = Tiger_Module_Installer::installFromUpload($this->makeModulePackage('w4force', ['version' => '1.1.0']));
        $this->assertSame('1.1.0', $r['version']);
        $this->assertTrue($r['updated'], 'a newer upload is flagged as an update');
        $this->assertSame('1.1.0', (string) (new Tiger_Model_Module())->bySlug('w4force')->version);

        // force still updates (e.g. deliberately reinstalling the same version).
        $r2 = Tiger_Module_Installer::installFromUpload($this->makeModulePackage('w4force', ['version' => '1.1.0']), ['force' => true]);
        $this->assertTrue($r2['updated']);
    }

    #[Test]
    public function aLicensedModuleInstallsWhenSignedAndIsRefusedWhenUnsigned(): void
    {
        $manifest = [
            'name'    => 'W4 Licensed',
            'version' => '2.0.0',
            'pricing' => ['model' => 'licensed', 'authority' => 'https://store.example/authority', 'vendor' => 'acme/TigerVendor'],
        ];

        // UNSIGNED → refused up front (a licensed artifact MUST arrive signed).
        $unsignedTar = $this->makeModulePackage('w4lic', $manifest);
        try {
            Tiger_Module_Installer::installFromUpload($unsignedTar);
            $this->fail('an unsigned licensed module must be refused');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('unsigned', $e->getMessage());
        }
        $this->assertDirectoryDoesNotExist(APPLICATION_PATH . '/modules/w4lic', 'nothing was placed');

        // SIGNED with a real Ed25519 keypair over the tarball bytes → the signature verifies and it installs.
        $signedTar = $this->makeModulePackage('w4lic', $manifest);
        $keys      = Tiger_Crypto_Signature::generateKeypair();
        $signature = Tiger_Crypto_Signature::signFile($signedTar, $keys['secret_key']);
        $sha256    = Tiger_Crypto_Signature::sha256File($signedTar);

        $r = Tiger_Module_Installer::installFromTarball($signedTar, ['source' => Tiger_Model_Module::SOURCE_URL], [
            'signature' => [
                'algo'       => Tiger_Crypto_Signature::ALGO,
                'public_key' => $keys['public_key'],
                'signature'  => $signature,
                'sha256'     => $sha256,
            ],
        ]);
        $this->installed[] = 'w4lic';
        $this->assertSame('w4lic', $r['slug']);
        $this->assertDirectoryExists(APPLICATION_PATH . '/modules/w4lic');
    }

    #[Test]
    public function aTamperedSignedArtifactIsRefusedBeforePlacement(): void
    {
        $tar  = $this->makeModulePackage('w4tamper', ['version' => '1.0.0']);
        $keys = Tiger_Crypto_Signature::generateKeypair();
        $sig  = Tiger_Crypto_Signature::signFile($tar, $keys['secret_key']);

        // Flip a byte AFTER signing → the artifact no longer matches the signature.
        $bytes = file_get_contents($tar);
        $bytes[strlen($bytes) >> 1] = ($bytes[strlen($bytes) >> 1] === "\x00") ? "\x01" : "\x00";
        file_put_contents($tar, $bytes);

        try {
            Tiger_Module_Installer::installFromTarball($tar, [], ['signature' => [
                'algo' => Tiger_Crypto_Signature::ALGO, 'public_key' => $keys['public_key'], 'signature' => $sig,
            ]]);
            $this->fail('a tampered artifact must be refused');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('signature verification FAILED', $e->getMessage());
        }
        $this->assertDirectoryDoesNotExist(APPLICATION_PATH . '/modules/w4tamper');
    }

    #[Test]
    public function aPhpRequirementBeyondThisServerIsAHardBlock(): void
    {
        // PHP is the one HARD gate in _checkRequires (Tiger compat is advisory; PHP is not).
        $tar = $this->makeModulePackage('w4php', ['requires' => ['php' => '>=99.0']]);
        try {
            Tiger_Module_Installer::installFromUpload($tar);
            $this->fail('an impossible PHP requirement must block the install');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('requires PHP', $e->getMessage());
        }
        $this->assertDirectoryDoesNotExist(APPLICATION_PATH . '/modules/w4php');
    }

    // ---- remove() --------------------------------------------------------------

    #[Test]
    public function removeDeletesTheFilesAndTheRow(): void
    {
        $this->installFixture('w4rm', ['version' => '1.0.0']);
        $this->assertDirectoryExists(APPLICATION_PATH . '/modules/w4rm');

        $this->assertTrue(Tiger_Module_Installer::remove('w4rm'));
        $this->assertDirectoryDoesNotExist(APPLICATION_PATH . '/modules/w4rm');
        $this->assertNull((new Tiger_Model_Module())->bySlug('w4rm'), 'the row is dropped');
    }

    #[Test]
    public function removeRefusesAModuleItDoesNotManage(): void
    {
        // A discovered (not installer-managed) module row → remove() must refuse it.
        (new Tiger_Model_Module())->setActive('w4discovered', true, ['source' => Tiger_Model_Module::SOURCE_DISCOVERED, 'name' => 'Discovered']);
        try {
            Tiger_Module_Installer::remove('w4discovered');
            $this->fail('remove() must refuse a non-installer-managed module');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString("isn't an installer-managed module", $e->getMessage());
        }
    }

    // ---- migrateModule / publishAssets / unpublishAssets -----------------------

    #[Test]
    public function migrateModuleRunsAModulesOwnMigrationsAndIsANoOpWithout(): void
    {
        // No migrations/ dir → false (the no-op branch).
        $this->plantModule('w4nomig', ['module.json' => json_encode(['slug' => 'w4nomig'])]);
        $this->assertFalse(Tiger_Module_Installer::migrateModule('w4nomig'));
        $this->rrmdir(APPLICATION_PATH . '/modules/w4nomig');

        // With a migrations/ dir → true, and the schema is applied.
        $this->plantModule('w4mig', [
            'module.json'               => json_encode(['slug' => 'w4mig']),
            'migrations/9001_w4.php'    => "<?php\nreturn ['up' => ['CREATE TABLE `w4_migtest` (`id` INT NOT NULL, PRIMARY KEY(`id`))'], 'down' => ['DROP TABLE `w4_migtest`']];\n",
        ]);
        try {
            $this->assertTrue(Tiger_Module_Installer::migrateModule('w4mig'));
            $this->assertTrue($this->tableExists('w4_migtest'), 'the module migration created its table');
        } finally {
            try { $this->db->query('DROP TABLE IF EXISTS `w4_migtest`'); } catch (\Throwable $e) {}
            try { $this->db->query("DELETE FROM `tiger_migration` WHERE version = '9001'"); } catch (\Throwable $e) {}
            $this->rrmdir(APPLICATION_PATH . '/modules/w4mig');
        }
    }

    /**
     * publishAllAssets() — every module with an assets/ dir gets its public link, without anyone
     * naming it (TIGER-123). Bundled modules are opt-out, so nothing ever activated them; until this,
     * a module added by a core update had no link until someone ran module:activate by hand.
     */
    #[Test]
    public function publishAllAssetsLinksEveryModuleThatHasAssets(): void
    {
        $this->plantModule('w4all_a', ['module.json' => json_encode(['slug' => 'w4all_a']), 'assets/a.js' => "//a\n"]);
        $this->plantModule('w4all_b', ['module.json' => json_encode(['slug' => 'w4all_b']), 'assets/b.js' => "//b\n"]);
        $this->plantModule('w4all_c', ['module.json' => json_encode(['slug' => 'w4all_c'])]);   // no assets/ dir
        $this->installed = array_merge($this->installed, ['w4all_a', 'w4all_b', 'w4all_c']);

        $done = Tiger_Module_Installer::publishAllAssets([], [APPLICATION_PATH . '/modules']);

        $this->assertContains('w4all_a', $done);
        $this->assertContains('w4all_b', $done);
        $this->assertNotContains('w4all_c', $done, 'a module with no assets/ has nothing to publish');
        $this->assertFileExists(APPLICATION_ROOT . '/public/_modules/w4all_a/a.js');
        $this->assertFileExists(APPLICATION_ROOT . '/public/_modules/w4all_b/b.js');
        $this->assertFalse(file_exists(APPLICATION_ROOT . '/public/_modules/w4all_c'));
    }

    /** It runs on every migrate, so a second pass must be a no-op — no churn, nothing re-linked. */
    #[Test]
    public function publishAllAssetsIsIdempotent(): void
    {
        $this->plantModule('w4idem', ['module.json' => json_encode(['slug' => 'w4idem']), 'assets/x.css' => "x{}\n"]);
        $this->installed[] = 'w4idem';

        $first  = Tiger_Module_Installer::publishAllAssets([], [APPLICATION_PATH . '/modules']);
        $link   = APPLICATION_ROOT . '/public/_modules/w4idem';
        $inode  = @fileinode($link);
        $second = Tiger_Module_Installer::publishAllAssets([], [APPLICATION_PATH . '/modules']);

        $this->assertContains('w4idem', $first);
        $this->assertNotContains('w4idem', $second, 'already pointing at the right place: left alone');
        $this->assertSame($inode, @fileinode($link), 'the link was not recreated');
    }

    /** A deactivated module's assets are not served — deactivate unpublishes, and this must not undo that. */
    #[Test]
    public function publishAllAssetsSkipsDeactivatedModules(): void
    {
        $this->plantModule('w4off', ['module.json' => json_encode(['slug' => 'w4off']), 'assets/off.js' => "//\n"]);
        $this->installed[] = 'w4off';

        $done = Tiger_Module_Installer::publishAllAssets(['w4off'], [APPLICATION_PATH . '/modules']);

        $this->assertNotContains('w4off', $done);
        $this->assertFalse(file_exists(APPLICATION_ROOT . '/public/_modules/w4off'), 'a deactivated module stays unpublished');
    }

    #[Test]
    public function publishAndUnpublishAssetsManageThePublicLink(): void
    {
        $this->plantModule('w4assets', [
            'module.json'      => json_encode(['slug' => 'w4assets']),
            'assets/app.css'   => "body{}\n",
        ]);
        $this->installed[] = 'w4assets';   // ensure the link is cleaned even if an assert fails

        Tiger_Module_Installer::publishAssets('w4assets');
        $link = APPLICATION_ROOT . '/public/_modules/w4assets';
        $this->assertTrue(is_link($link) || is_dir($link), 'assets are published to public/_modules');
        $this->assertFileExists($link . '/app.css');

        Tiger_Module_Installer::unpublishAssets('w4assets');
        $this->assertFalse(is_link($link) || is_dir($link), 'unpublish removes the public link');

        $this->rrmdir(APPLICATION_PATH . '/modules/w4assets');
    }

    // ---- installFromAuthority : guard + reachable failure branches -------------

    #[Test]
    public function installFromAuthorityRejectsIncompleteMeta(): void
    {
        try {
            Tiger_Module_Installer::installFromAuthority('https://store.example/authority', 'LIC-1', ['product' => 'w4x']);
            $this->fail('a licensed install needs authority + key + product + public_key');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('public key', $e->getMessage());
        }
    }

    #[Test]
    public function installFromAuthorityThrowsWhenTheAuthorityRefuses(): void
    {
        Tiger_License_Authority::setTransport(static fn(string $url, array $payload): ?array => null);   // refused/unreachable
        try {
            Tiger_Module_Installer::installFromAuthority('https://store.example/authority', 'LIC-1', [
                'product' => 'w4x', 'public_key' => str_repeat('a', 44),
            ]);
            $this->fail('a refused authority must abort the install');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('did not authorize', $e->getMessage());
        }
    }

    #[Test]
    public function installFromAuthorityThrowsWhenTheSignedDownloadIsUnreachable(): void
    {
        // The authority authorizes + returns a (dead) signed URL → the download step fails, no network hit.
        Tiger_License_Authority::setTransport(static fn(string $url, array $payload): ?array => [
            'url'       => 'http://127.0.0.1:9/licensed.zip',
            'signature' => base64_encode(str_repeat("\x01", 64)),
            'sha256'    => str_repeat('0', 64),
            'version'   => '1.0.0',
        ]);
        try {
            Tiger_Module_Installer::installFromAuthority('https://store.example/authority', 'LIC-1', [
                'product' => 'w4x', 'public_key' => str_repeat('a', 44),
            ]);
            $this->fail('an unreachable signed download must abort the install');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Failed to download', $e->getMessage());
        }
    }

    // ---- helpers ---------------------------------------------------------------

    /** Install a free fixture module and register it for cleanup. */
    private function installFixture(string $slug, array $manifest): array
    {
        $r = Tiger_Module_Installer::installFromUpload($this->makeModulePackage($slug, $manifest));
        $this->installed[] = $slug;
        return $r;
    }

    /**
     * Build a real package archive containing a single top dir <slug>/ with a module.json — the shape a
     * GitHub/upload archive has (the installer unwraps the single top dir).
     *
     * We use a ZIP (ZipArchive), not a PharData tar.gz: the installer extracts a zip via the universally
     * available `zip` extension (detected by the "PK" magic, so the extensionless path is irrelevant),
     * whereas PharData tar.gz round-tripping is not portable across every PHP build/CI runner. The
     * installer, the signature gate, and the single-top-dir unwrap are all archive-format-agnostic.
     */
    private function makeModulePackage(string $slug, array $manifest): string
    {
        $manifest += ['slug' => $slug, 'name' => ucfirst($slug)];
        $manifest['slug'] = $slug;
        $path = $this->tmp . '/' . $slug . '_' . bin2hex(random_bytes(3)) . '.zip';
        $zip  = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            $this->fail('could not create the fixture zip at ' . $path);
        }
        $zip->addFromString($slug . '/module.json', json_encode($manifest));
        $zip->addFromString($slug . '/README.md', "# {$slug}\n");
        $zip->close();
        return $path;
    }

    /** Plant a module dir directly under the app modules root (for the activate-time hooks). */
    private function plantModule(string $slug, array $files): void
    {
        $dir = APPLICATION_PATH . '/modules/' . $slug;
        @mkdir($dir, 0775, true);
        foreach ($files as $rel => $contents) {
            $full = $dir . '/' . $rel;
            @mkdir(dirname($full), 0775, true);
            file_put_contents($full, $contents);
        }
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
