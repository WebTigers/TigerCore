<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Media;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use RuntimeException;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Media_Storage_Azure;

/**
 * Tiger_Media_Storage_Azure — the Azure Blob adapter, exercised WITHOUT the Azure SDK.
 *
 * `microsoft/azure-storage-blob` is an OPTIONAL dependency and is NOT in the test vendor tree, so
 * every method that reaches `_c()` throws a clear "not installed" error. That is exactly the
 * SDK-absent degradation contract this suite pins, with zero network:
 *   - construction resolves single-container vs two-container mode (and rejects neither);
 *   - `url()` for a PUBLIC blob is pure string-building — no client — so every public-URL shape is a
 *     known-answer test (default `*.blob.core.windows.net`, a CDN host, an `endpoint` override), in
 *     both container layouts, with path segments encoded;
 *   - `url()` for a PRIVATE blob returns '' when presigning is off (`presign_ttl=0`), when the
 *     account key is unavailable, and (SAS helper class absent) when signing itself can't run;
 *   - the key-mapper `_blob()` maps visibility → prefix (single-container) or bare key
 *     (two-container) and refuses `..` traversal in both;
 *   - `_fromConnStr()` parses AccountName / AccountKey out of a connection string;
 *   - the best-effort readers degrade when the client can't be built — `exists()`→false, `size()`→0,
 *     `delete()` swallows — while `get()`/`stream()`/`put()`/`write()` surface a clean RuntimeException.
 *
 * A live createBlockBlob/getBlob round trip needs the SDK + an account and belongs to an
 * integration/live suite — see WAVE7-FINDINGS-media.md.
 */
#[CoversClass(Tiger_Media_Storage_Azure::class)]
final class AzureTest extends UnitTestCase
{
    /** Single-container adapter (visibility → key prefix within one container). */
    private function single(array $overrides = []): Tiger_Media_Storage_Azure
    {
        return new Tiger_Media_Storage_Azure(['container' => 'media', 'account' => 'acct'] + $overrides);
    }

    /** Two-container adapter (visibility → container; no visibility key prefix). */
    private function two(array $overrides = []): Tiger_Media_Storage_Azure
    {
        return new Tiger_Media_Storage_Azure(
            ['public_container' => 'pubc', 'private_container' => 'privc', 'account' => 'acct'] + $overrides
        );
    }

    private function call(Tiger_Media_Storage_Azure $a, string $method, array $args = [])
    {
        return (new ReflectionMethod($a, $method))->invokeArgs($a, $args);
    }

    // ---- construction ---------------------------------------------------------------------------

    #[Test]
    public function neither_a_container_nor_a_container_pair_is_a_construct_time_error(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/container/i');
        new Tiger_Media_Storage_Azure(['account' => 'acct']);   // no container at all
    }

    #[Test]
    public function a_single_container_is_enough_to_construct(): void
    {
        $a = $this->single();
        $this->assertInstanceOf(Tiger_Media_Storage_Azure::class, $a);
    }

    // ---- _blob(): the key-mapper + traversal guard ----------------------------------------------

    #[Test]
    public function blob_maps_the_visibility_prefix_in_single_container_mode(): void
    {
        $a = $this->single();
        $this->assertSame('public/org-1/a.jpg', $this->call($a, '_blob', ['org-1/a.jpg', 'public']));
        $this->assertSame('private/org-1/b.epub', $this->call($a, '_blob', ['org-1/b.epub', 'private']));
    }

    #[Test]
    public function blob_omits_the_visibility_prefix_in_two_container_mode(): void
    {
        // In two-container mode the container carries the visibility, so the blob name is just the key.
        $a = $this->two();
        $this->assertSame('org-1/a.jpg', $this->call($a, '_blob', ['org-1/a.jpg', 'public']));
        $this->assertSame('org-1/a.jpg', $this->call($a, '_blob', ['org-1/a.jpg', 'private']));
    }

    #[Test]
    public function blob_honors_a_base_prefix(): void
    {
        $a = $this->single(['prefix' => 'tenant', 'public_prefix' => 'pub']);
        $this->assertSame('tenant/pub/x.png', $this->call($a, '_blob', ['x.png', 'public']));
    }

    #[Test]
    public function blob_refuses_a_traversal_key(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/invalid key/i');
        $this->call($this->single(), '_blob', ['../../etc/passwd', 'private']);
    }

    #[Test]
    public function container_selects_by_visibility_in_two_container_mode(): void
    {
        $a = $this->two();
        $this->assertSame('pubc', $this->call($a, '_container', ['public']));
        $this->assertSame('privc', $this->call($a, '_container', ['private']));
    }

    // ---- url(): PUBLIC is pure string building --------------------------------------------------

    #[Test]
    public function public_url_defaults_to_the_blob_endpoint_two_container(): void
    {
        // container carries visibility; path = <container>/<key>, host = <account>.blob.core.windows.net
        $a = $this->two();
        $this->assertSame(
            'https://acct.blob.core.windows.net/pubc/org-1/a.jpg',
            $a->url('org-1/a.jpg', 'public')
        );
    }

    #[Test]
    public function public_url_defaults_to_the_blob_endpoint_single_container(): void
    {
        $a = $this->single();
        $this->assertSame(
            'https://acct.blob.core.windows.net/media/public/x.png',
            $a->url('x.png', 'public')
        );
    }

    #[Test]
    public function public_url_prefers_a_cdn_host_when_set(): void
    {
        $a = $this->two(['cdn' => 'cdn.example.com/']);   // trailing slash trimmed
        $this->assertSame('https://cdn.example.com/pubc/x.png', $a->url('x.png', 'public'));
    }

    #[Test]
    public function public_url_honors_an_endpoint_override(): void
    {
        $a = $this->two(['endpoint' => 'https://acct.blob.core.usgovcloudapi.net/']);
        $this->assertSame(
            'https://acct.blob.core.usgovcloudapi.net/pubc/x.png',
            $a->url('x.png', 'public')
        );
    }

    #[Test]
    public function public_url_encodes_path_segments(): void
    {
        $a = $this->two();
        $this->assertSame(
            'https://acct.blob.core.windows.net/pubc/org-1/my%20photo.jpg',
            $a->url('org-1/my photo.jpg', 'public')
        );
    }

    // ---- url(): PRIVATE degrades to '' three ways ----------------------------------------------

    #[Test]
    public function private_url_is_empty_when_presigning_is_disabled(): void
    {
        $a = $this->single(['presign_ttl' => 0, 'key' => 'k']);
        $this->assertSame('', $a->url('org-1/book.epub', 'private'));
    }

    #[Test]
    public function private_url_is_empty_without_an_account_key(): void
    {
        // presigning on, but no account key → can't sign → '' (stream via the ACL route).
        $a = $this->single(['presign_ttl' => 600]);   // 'account' set, but no 'key'
        $this->assertSame('', $a->url('org-1/book.epub', 'private'));
    }

    #[Test]
    public function private_url_falls_back_to_empty_when_the_sas_helper_is_absent(): void
    {
        // account + key present + presigning on, but the Azure SAS helper class is not installed →
        // the try/catch returns '' rather than throwing.
        $a = $this->single(['presign_ttl' => 600, 'key' => 'c2VjcmV0']);
        $this->assertSame('', $a->url('org-1/book.epub', 'private'));
    }

    // ---- _fromConnStr(): parse account name / key out of a connection string --------------------

    #[Test]
    public function account_name_and_key_come_from_a_connection_string(): void
    {
        $cs = 'DefaultEndpointsProtocol=https;AccountName=foo;AccountKey=YmFyPT0=;EndpointSuffix=core.windows.net';
        $a  = new Tiger_Media_Storage_Azure(['container' => 'media', 'connection_string' => $cs]);
        $this->assertSame('foo', $this->call($a, '_accountName'));
        $this->assertSame('YmFyPT0=', $this->call($a, '_accountKey'), 'the value keeps its trailing =/== base64 padding');
    }

    #[Test]
    public function an_explicit_account_setting_wins_over_the_connection_string(): void
    {
        $a = new Tiger_Media_Storage_Azure([
            'container'         => 'media',
            'account'           => 'explicit',
            'connection_string' => 'AccountName=fromcs;AccountKey=k',
        ]);
        $this->assertSame('explicit', $this->call($a, '_accountName'));
    }

    #[Test]
    public function a_missing_connection_string_field_yields_empty(): void
    {
        $a = new Tiger_Media_Storage_Azure(['container' => 'media']);
        $this->assertSame('', $this->call($a, '_fromConnStr', ['AccountName']));
    }

    // ---- SDK-absent degradation of the readers/writers -----------------------------------------

    #[Test]
    public function client_build_reports_the_missing_sdk(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/azure-storage-blob is not installed/i');
        $this->call($this->single(['key' => 'k']), '_c');
    }

    #[Test]
    public function exists_is_false_and_size_is_zero_without_a_client(): void
    {
        $a = $this->single(['key' => 'k']);
        $this->assertFalse($a->exists('org-1/a.jpg', 'public'));
        $this->assertSame(0, $a->size('org-1/a.jpg', 'public'));
    }

    #[Test]
    public function delete_swallows_a_client_failure(): void
    {
        $this->single(['key' => 'k'])->delete('org-1/a.jpg', 'private');
        $this->assertTrue(true, 'delete() returned without throwing');
    }

    #[Test]
    public function get_and_stream_surface_a_runtime_exception_without_a_client(): void
    {
        $a = $this->single(['key' => 'k']);
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
        $this->single(['key' => 'k'])->put('a.jpg', '/no/such/source/file.bin', 'public');
    }

    /**
     * CHARACTERIZATION (see WAVE7-FINDINGS-media.md): unlike S3/Gcs — whose only SDK touch is inside
     * the guarded `_c()` (friendly "not installed"/"could not store") — Azure's `_create()`
     * instantiates `\MicrosoftAzure\Storage\Blob\Models\CreateBlockBlobOptions` BEFORE the try/catch,
     * so with the SDK absent put()/write() surface a raw `\Error` (class-not-found), NOT a wrapped
     * RuntimeException. This test pins the CURRENT behavior; it is not an endorsement of it.
     */
    #[Test]
    public function put_from_a_real_source_surfaces_a_class_not_found_error_without_the_sdk(): void
    {
        $src = tempnam(sys_get_temp_dir(), 'tazsrc');
        file_put_contents($src, 'bytes');
        try {
            $this->expectException(\Error::class);
            $this->expectExceptionMessageMatches('/CreateBlockBlobOptions|not found/i');
            $this->single(['key' => 'k'])->put('a.jpg', $src, 'public', 'image/jpeg');
        } finally {
            @unlink($src);
        }
    }

    #[Test]
    public function write_surfaces_a_class_not_found_error_without_the_sdk(): void
    {
        // CHARACTERIZATION — same as above: the un-guarded SDK options `new` throws a raw \Error.
        $this->expectException(\Error::class);
        $this->expectExceptionMessageMatches('/CreateBlockBlobOptions|not found/i');
        $this->single(['key' => 'k'])->write('a.jpg', 'raw-bytes', 'public', 'application/octet-stream');
    }
}
