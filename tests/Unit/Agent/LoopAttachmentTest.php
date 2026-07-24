<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Agent;

use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;
use Tiger_Agent_Loop;

/** Exposes the Loop's protected attachment shaping so we can assert it without a provider/DB. */
final class ExposedAttachmentLoop extends Tiger_Agent_Loop
{
    public function manifest(array $a): array { return $this->_attachmentManifest($a); }
    public function meta(array $a): array     { return $this->_attachmentMeta($a); }
}

/**
 * Tiger_Agent_Loop — the two pure attachment shapers that fold a dropped file into a turn:
 *  - the model-facing MANIFEST (what was shared + a document's extracted text) folded into context, and
 *  - the lean META sidecar stored on the user message so chips re-render on reload (no bytes/text).
 * Image vision-input is threaded separately (needs a provider), so it isn't covered here.
 */
final class LoopAttachmentTest extends UnitTestCase
{
    private ExposedAttachmentLoop $loop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loop = new ExposedAttachmentLoop('developer', 'user-1', 'org-1');
    }

    #[Test]
    public function documentManifestCarriesItsExtractedText(): void
    {
        $m = $this->loop->manifest([
            ['filename' => 'ch1.md', 'mime' => 'text/markdown', 'kind' => 'file', 'size' => 40, 'extract' => 'Chapter one text.'],
        ]);
        $this->assertCount(1, $m);
        $this->assertSame('file', $m[0]['kind']);
        $this->assertSame('Chapter one text.', $m[0]['text']);
    }

    #[Test]
    public function anUnreadableDocumentGetsAnActionableNote_notAGuess(): void
    {
        $m = $this->loop->manifest([
            ['filename' => 'book.epub', 'mime' => 'application/epub+zip', 'kind' => 'file', 'size' => 900000, 'extract' => ''],
        ]);
        $this->assertArrayHasKey('text', $m[0]);
        $this->assertStringContainsString('stored file', $m[0]['text']);
    }

    #[Test]
    public function imageManifestHasNoTextKey_itRidesAsVisionNotProse(): void
    {
        $m = $this->loop->manifest([
            ['filename' => 'cover.png', 'mime' => 'image/png', 'kind' => 'image', 'size' => 2048],
        ]);
        $this->assertSame('image', $m[0]['kind']);
        $this->assertArrayNotHasKey('text', $m[0]);
    }

    #[Test]
    public function metaSidecarIsLean_noBytesNoTextNoMime(): void
    {
        $meta = $this->loop->meta([
            ['attachment_id' => 'a-1', 'filename' => 'cover.png', 'mime' => 'image/png', 'kind' => 'image',
             'size' => 2048, 'storage_key' => 'agent/o/u/a-1.png', 'extract' => 'should not leak'],
        ]);
        $this->assertSame(['attachment_id', 'filename', 'kind', 'size'], array_keys($meta[0]));
        $this->assertSame('a-1', $meta[0]['attachment_id']);
        $this->assertSame('image', $meta[0]['kind']);
    }
}
