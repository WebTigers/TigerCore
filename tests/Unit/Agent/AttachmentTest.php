<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.

namespace Tiger\Tests\Unit\Agent;

use PHPUnit\Framework\Attributes\Test;
use Tiger\Tests\Support\UnitTestCase;

// A module model isn't PSR-0-autoloadable in the unit bootstrap (no module resource loader), but its
// STATIC classification/extraction helpers need no DB — so we load the file directly and exercise them.
require_once TIGER_CORE_PATH . '/modules/agent/models/Attachment.php';

/**
 * Agent_Model_Attachment — the pure classification + text-extraction logic behind agent-aside drops.
 * The kind split (image vs file) drives whether a drop rides as native vision or as extracted text;
 * `accepted()` is the upload gate; `extractText()` is best-effort per type. All static, no DB.
 */
final class AttachmentTest extends UnitTestCase
{
    #[Test]
    public function imagesAreTheVisionKindAndEverythingElseIsAFile(): void
    {
        $this->assertSame('image', \Agent_Model_Attachment::kindFor('cover.PNG'));
        $this->assertSame('image', \Agent_Model_Attachment::kindFor('shot.jpeg'));
        $this->assertSame('file',  \Agent_Model_Attachment::kindFor('manuscript.docx'));
        $this->assertSame('file',  \Agent_Model_Attachment::kindFor('book.epub'));
    }

    #[Test]
    public function acceptedCoversText_doc_image_andTheStoreOnlyTypes_butNotJunk(): void
    {
        foreach (['notes.txt', 'data.json', 'chapter.md', 'paper.pdf', 'brief.docx', 'cover.jpg', 'book.epub', 'archive.zip'] as $ok) {
            $this->assertTrue(\Agent_Model_Attachment::accepted($ok), $ok . ' should be accepted');
        }
        foreach (['virus.exe', 'macro.xlsm', 'noext', 'thing.bin'] as $no) {
            $this->assertFalse(\Agent_Model_Attachment::accepted($no), $no . ' should be rejected');
        }
    }

    #[Test]
    public function textIsReadVerbatimAndCollapsedWhitespaceCleaned(): void
    {
        $out = \Agent_Model_Attachment::extractText('notes.txt', 'text/plain', "Hello   world\n\n\n\nNext");
        $this->assertSame("Hello world\n\nNext", $out);
    }

    #[Test]
    public function htmlHasScriptsAndTagsStripped(): void
    {
        $out = \Agent_Model_Attachment::extractText('page.html', 'text/html', '<p>Keep <script>steal()</script>this</p>');
        $this->assertStringContainsString('Keep', $out);
        $this->assertStringContainsString('this', $out);
        $this->assertStringNotContainsString('steal', $out);
        $this->assertStringNotContainsString('<p>', $out);
    }

    #[Test]
    public function imagesAndUnknownBinariesReturnNull_readByVisionNotParsing(): void
    {
        $this->assertNull(\Agent_Model_Attachment::extractText('cover.png', 'image/png', "\x89PNG\r\n\x1a\n"));
        $this->assertNull(\Agent_Model_Attachment::extractText('book.epub', 'application/epub+zip', 'PK...'));
    }

    #[Test]
    public function extractedTextIsCappedToKeepATurnSane(): void
    {
        $big = str_repeat('A', \Agent_Model_Attachment::EXTRACT_CAP + 500);
        $out = \Agent_Model_Attachment::extractText('big.txt', 'text/plain', $big);
        $this->assertLessThanOrEqual(\Agent_Model_Attachment::EXTRACT_CAP + 20, mb_strlen($out));
        $this->assertStringEndsWith('[truncated]', $out);
    }
}
