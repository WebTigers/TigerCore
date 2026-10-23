<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\View;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionProperty;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_View_Helper_Asset;

/**
 * Tiger_View_Helper_Asset — the cache-busting asset-URL helper. `$this->asset('/_theme/app.css')`
 * appends `?v=<filemtime>` so a deploy's changed CSS/JS is picked up without a hard refresh — zero-
 * build (mtime, no manifest), query-string (no rewrite, can't 404), feature-flagged, and graceful
 * (remote/empty/missing paths pass through untouched).
 *
 * A pure helper: it stats a file under PUBLIC_PATH and reads a config flag from the registry, so these
 * unit tests point PUBLIC_PATH at a temp docroot with a real asset file and drive the config flag
 * directly. The helper memoizes per request in two process-static caches — reset before each test so
 * ordering never leaks a version token or the resolved flag.
 */
#[CoversClass(Tiger_View_Helper_Asset::class)]
final class AssetTest extends UnitTestCase
{
    private static string $docroot;

    public static function setUpBeforeClass(): void
    {
        // A throwaway docroot with a real asset to stat. PUBLIC_PATH is a process-global constant the
        // helper reads as `PUBLIC_PATH . $path`; define it once, here, at the temp docroot.
        self::$docroot = sys_get_temp_dir() . '/tiger-asset-test-' . getmypid();
        @mkdir(self::$docroot . '/_theme', 0777, true);
        file_put_contents(self::$docroot . '/_theme/app.css', 'body{}');
        touch(self::$docroot . '/_theme/app.css', 1713900000);
        // A published module asset (public/_modules/<slug>/…) for the module-form tests.
        @mkdir(self::$docroot . '/_modules/sms/js', 0777, true);
        file_put_contents(self::$docroot . '/_modules/sms/js/sms.settings.js', '/* */');
        touch(self::$docroot . '/_modules/sms/js/sms.settings.js', 1713900000);
        if (!defined('PUBLIC_PATH')) {
            define('PUBLIC_PATH', self::$docroot);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->resetStatics();
    }

    protected function tearDown(): void
    {
        $this->resetStatics();
        parent::tearDown();
    }

    /** Clear the helper's per-request memo + resolved-flag caches so tests don't leak into each other. */
    private function resetStatics(): void
    {
        foreach (['_enabled' => null, '_memo' => []] as $name => $value) {
            (new ReflectionProperty(Tiger_View_Helper_Asset::class, $name))->setValue(null, $value);
        }
    }

    private function helper(): Tiger_View_Helper_Asset
    {
        return new Tiger_View_Helper_Asset();
    }

    #[Test]
    public function it_appends_a_filemtime_version_token_to_a_local_asset(): void
    {
        // No config => default ON. app.css exists and was stamped mtime 1713900000.
        $this->assertSame('/_theme/app.css?v=1713900000', $this->helper()->asset('/_theme/app.css'));
    }

    #[Test]
    public function an_existing_query_string_is_preserved_with_an_ampersand(): void
    {
        $this->assertSame('/_theme/app.css?a=1&v=1713900000', $this->helper()->asset('/_theme/app.css?a=1'));
    }

    #[Test]
    public function a_fragment_is_kept_last_after_the_version_token(): void
    {
        $this->assertSame('/_theme/app.css?v=1713900000#top', $this->helper()->asset('/_theme/app.css#top'));
    }

    #[Test]
    public function a_missing_file_passes_through_unversioned(): void
    {
        $this->assertSame('/_theme/nope.css', $this->helper()->asset('/_theme/nope.css'));
    }

    #[Test]
    public function a_remote_url_passes_through_untouched(): void
    {
        $this->assertSame('https://cdn.example/app.css', $this->helper()->asset('https://cdn.example/app.css'));
    }

    #[Test]
    public function a_protocol_relative_url_passes_through_untouched(): void
    {
        $this->assertSame('//cdn.example/app.css', $this->helper()->asset('//cdn.example/app.css'));
    }

    #[Test]
    public function an_empty_path_passes_through_untouched(): void
    {
        $this->assertSame('', $this->helper()->asset(''));
    }

    #[Test]
    public function the_result_is_memoized_within_a_request(): void
    {
        $first = $this->helper()->asset('/_theme/app.css');
        // Change the file's mtime AFTER the first resolve — the memoized token must still be served.
        touch(self::$docroot . '/_theme/app.css', 1799999999);
        $this->assertSame($first, $this->helper()->asset('/_theme/app.css'), 'the per-request memo wins');
        touch(self::$docroot . '/_theme/app.css', 1713900000);   // restore for the other tests
    }

    #[Test]
    public function the_cache_bust_flag_off_makes_paths_pass_through(): void
    {
        $this->setConfig(['tiger' => ['assets' => ['cache_bust' => '0']]]);
        $this->assertSame('/_theme/app.css', $this->helper()->asset('/_theme/app.css'));
    }

    #[Test]
    public function the_cache_bust_flag_explicitly_on_still_versions(): void
    {
        $this->setConfig(['tiger' => ['assets' => ['cache_bust' => '1']]]);
        $this->assertSame('/_theme/app.css?v=1713900000', $this->helper()->asset('/_theme/app.css'));
    }

    // ----- module form: asset('js/x.js', 'slug') -> /_modules/slug/js/x.js -----------------------

    #[Test]
    public function the_module_form_resolves_to_the_published_module_assets_dir(): void
    {
        // The intuitive "name the module" call — the bug this guards: before, a bare relative path was
        // passed straight through and the JS 404'd silently.
        $this->assertSame(
            '/_modules/sms/js/sms.settings.js?v=1713900000',
            $this->helper()->asset('js/sms.settings.js', 'sms')
        );
    }

    #[Test]
    public function the_module_form_tolerates_a_leading_slash_on_the_path(): void
    {
        $this->assertSame(
            '/_modules/sms/js/sms.settings.js?v=1713900000',
            $this->helper()->asset('/js/sms.settings.js', 'sms')
        );
    }

    #[Test]
    public function the_module_slug_is_sanitized(): void
    {
        // Junk in the slug can't escape the _modules dir (path traversal / separators are stripped).
        $this->assertSame(
            '/_modules/sms/js/sms.settings.js?v=1713900000',
            $this->helper()->asset('js/sms.settings.js', '../sms/')
        );
    }

    #[Test]
    public function a_missing_module_asset_passes_through_as_the_resolved_path(): void
    {
        $this->assertSame('/_modules/sms/js/nope.js', $this->helper()->asset('js/nope.js', 'sms'));
    }

    #[Test]
    public function an_empty_module_arg_is_ignored(): void
    {
        $this->assertSame('/_theme/app.css?v=1713900000', $this->helper()->asset('/_theme/app.css', ''));
    }
}
