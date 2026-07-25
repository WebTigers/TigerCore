<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Media;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use RuntimeException;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Media_Storage_Gcs;

/**
 * Tiger_Media_Storage_Gcs — the Google Cloud Storage adapter, exercised WITHOUT the GCS SDK.
 *
 * `google/cloud-storage` is an OPTIONAL dependency and is NOT in the test vendor tree, so any method
 * that reaches `_bkt()` throws a clear "not installed" error. That makes the SDK-absent degradation
 * contract testable with zero network — it mirrors the S3 adapter's shape:
 *   - construction requires a bucket (rejects a missing one);
 *   - `url()` for a PUBLIC object is pure string-building — no client — so every public-URL shape is a
 *     known-answer test (default `storage.googleapis.com`, a CDN host, segment encoding);
 *   - `url()` for a PRIVATE object returns '' when signing is off (`presign_ttl=0`) and when the
 *     signer itself can't run (SDK absent);
 *   - `_fullKey()` maps visibility → prefix and refuses `..` traversal;
 *   - the best-effort readers degrade when the client can't be built — `exists()`→false, `size()`→0,
 *     `delete()` swallows — while `get()`/`stream()`/`put()`/`write()` surface a clean RuntimeException.
 *
 * A live upload/download round trip needs the SDK + a bucket and belongs to an integration/live
 * suite — see WAVE7-FINDINGS-media.md.
 */
#[CoversClass(Tiger_Media_Storage_Gcs::class)]
final class GcsTest extends UnitTestCase
{
    private function adapter(array $overrides = []): Tiger_Media_Storage_Gcs
    {
        return new Tiger_Media_Storage_Gcs(['bucket' => 'my-bucket'] + $overrides);
    }

    private function call(Tiger_Media_Storage_Gcs $a, string $method, array $args = [])
    {
        return (new ReflectionMethod($a, $method))->invokeArgs($a, $args);
    }

    // ---- construction ---------------------------------------------------------------------------

    #[Test]
    public function a_missing_bucket_is_a_construct_time_error(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/bucket is required/i');
        new Tiger_Media_Storage_Gcs(['region' => 'us']);   // no bucket
    }

    // ---- _fullKey(): the key-mapper + traversal guard ------------------------------------------

    #[Test]
    public function full_key_maps_visibility_to_the_expected_prefix(): void
    {
        $a = $this->adapter();
        $this->assertSame('public/org-1/a.jpg', $this->call($a, '_fullKey', ['org-1/a.jpg', 'public']));
        $this->assertSame('private/org-1/b.epub', $this->call($a, '_fullKey', ['org-1/b.epub', 'private']));
    }

    #[Test]
    public function full_key_honors_a_base_prefix(): void
    {
        $a = $this->adapter(['prefix' => 'tenant', 'public_prefix' => 'pub', 'private_prefix' => 'priv']);
        $this->assertSame('tenant/pub/x.png', $this->call($a, '_fullKey', ['x.png', 'public']));
        $this->assertSame('tenant/priv/x.png', $this->call($a, '_fullKey', ['x.png', 'private']));
    }

    #[Test]
    public function full_key_refuses_a_traversal_key(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/invalid key/i');
        $this->call($this->adapter(), '_fullKey', ['../../etc/passwd', 'private']);
    }

    // ---- url(): PUBLIC is pure string building --------------------------------------------------

    #[Test]
    public function public_url_defaults_to_the_google_storage_host(): void
    {
        $a = $this->adapter();
        $this->assertSame(
            'https://storage.googleapis.com/my-bucket/public/org-1/a.jpg',
            $a->url('org-1/a.jpg', 'public')
        );
    }

    #[Test]
    public function public_url_prefers_a_cdn_host_when_set(): void
    {
        $a = $this->adapter(['cdn' => 'cdn.example.com/']);   // trailing slash trimmed
        $this->assertSame('https://cdn.example.com/public/x.png', $a->url('x.png', 'public'));
    }

    #[Test]
    public function public_url_encodes_path_segments(): void
    {
        $a = $this->adapter();
        $this->assertSame(
            'https://storage.googleapis.com/my-bucket/public/org-1/my%20photo.jpg',
            $a->url('org-1/my photo.jpg', 'public')
        );
    }

    // ---- url(): PRIVATE degrades to '' ---------------------------------------------------------

    #[Test]
    public function private_url_is_empty_when_signing_is_disabled(): void
    {
        $a = $this->adapter(['presign_ttl' => 0]);
        $this->assertSame('', $a->url('org-1/book.epub', 'private'));
    }

    #[Test]
    public function private_url_falls_back_to_empty_when_the_client_cannot_be_built(): void
    {
        // presigning on, but the GCS SDK is absent → _object()/_bkt() throws → caught → ''.
        $a = $this->adapter(['presign_ttl' => 600]);
        $this->assertSame('', $a->url('org-1/book.epub', 'private'));
    }

    // ---- SDK-absent degradation of the readers/writers -----------------------------------------

    #[Test]
    public function bucket_handle_reports_the_missing_sdk(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/google\/cloud-storage is not installed/i');
        $this->call($this->adapter(['project_id' => 'p', 'key_file' => '/tmp/none.json']), '_bkt');
    }

    #[Test]
    public function exists_is_false_and_size_is_zero_without_a_client(): void
    {
        $a = $this->adapter();
        $this->assertFalse($a->exists('org-1/a.jpg', 'public'));
        $this->assertSame(0, $a->size('org-1/a.jpg', 'public'));
    }

    #[Test]
    public function delete_swallows_a_client_failure(): void
    {
        $this->adapter()->delete('org-1/a.jpg', 'private');
        $this->assertTrue(true, 'delete() returned without throwing');
    }

    #[Test]
    public function get_and_stream_surface_a_runtime_exception_without_a_client(): void
    {
        $a = $this->adapter();
        try {
            $a->get('org-1/a.jpg', 'public');
            $this->fail('get() should have thrown');
        } catch (RuntimeException $e) {
            $this->assertMatchesRegularExpression('/not found/i', $e->getMessage());
        }
        try {
            $a->stream('org-1/a.jpg', 'public');
            $this->fail('stream() should have thrown');
        } catch (RuntimeException $e) {
            $this->assertMatchesRegularExpression('/not found/i', $e->getMessage());
        }
    }

    #[Test]
    public function put_throws_when_the_source_is_unreadable(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/cannot read source/i');
        $this->adapter()->put('a.jpg', '/no/such/source/file.bin', 'public');
    }

    #[Test]
    public function put_from_a_real_source_surfaces_the_store_failure_without_a_client(): void
    {
        $src = tempnam(sys_get_temp_dir(), 'tgcssrc');
        file_put_contents($src, 'bytes');
        try {
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessageMatches('/could not store/i');
            $this->adapter()->put('a.jpg', $src, 'public', 'image/jpeg');
        } finally {
            @unlink($src);
        }
    }

    #[Test]
    public function write_surfaces_the_store_failure_without_a_client(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/could not store/i');
        $this->adapter()->write('a.jpg', 'raw-bytes', 'public', 'application/octet-stream');
    }

    #[Test]
    public function write_with_the_public_acl_flag_still_surfaces_the_store_failure(): void
    {
        // public_acl=1 takes the predefinedAcl arm of _upload before _bkt() throws.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/could not store/i');
        $this->adapter(['public_acl' => 1])->write('a.jpg', 'raw', 'public');
    }
}
