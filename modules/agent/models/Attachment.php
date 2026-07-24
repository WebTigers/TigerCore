<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Agent_Model_Attachment — a file a user dropped onto the agent aside for the agent to use.
 *
 * Images ride to the model as native vision input (base64) when the conversation's provider/model
 * is multimodal; documents are read as extracted text folded into the turn context. The bytes live
 * on the shared media disk under a private `agent/…` prefix (never the Media Manager, never a public
 * URL). `extract` caches a document's readable text so a turn never re-parses.
 *
 * Ownership is enforced on every read: an attachment is scoped to the user who uploaded it and can
 * only be attached to that user's own conversations. Text extraction is best-effort by type —
 * text/code/markup verbatim, DOCX via its XML, PDF via a lightweight operator scan (handles most
 * text PDFs; scanned/CID-font PDFs come back empty). Binary types (images, epub, zip) store fine but
 * carry no extracted text — an image is read by vision, not by parsing.
 *
 * @api
 */
class Agent_Model_Attachment extends Tiger_Model_Table
{
    protected $_name    = 'agent_attachment';
    protected $_primary = 'attachment_id';

    /** Hard upload ceilings (bytes) and the readable-text cap injected into a turn. Images get a
     *  tighter cap because they ride base64-encoded (native vision) — keep the payload within limits. */
    const MAX_BYTES       = 10485760;   // 10 MB (documents / other)
    const IMAGE_MAX_BYTES = 5242880;    // 5 MB (images)
    const EXTRACT_CAP     = 8000;       // chars of extracted text kept per file

    /** Extensions read verbatim as text. */
    const TEXT_EXT  = ['txt','text','md','markdown','csv','tsv','json','log','xml','yaml','yml',
                       'html','htm','ini','conf','env','php','js','ts','jsx','tsx','css','scss',
                       'py','rb','java','c','cpp','h','hpp','go','rs','sql','sh','bash'];
    /** Documents we can extract text from. */
    const DOC_EXT   = ['pdf','docx'];
    /** Images (read by native vision). */
    const IMAGE_EXT = ['png','jpg','jpeg','gif','webp'];
    /** Other accepted types — stored for the agent to ACT on (e.g. an ebook to sell), not read as
     *  text. The North-Star "here's my epub" flow lives here: the agent gets the file + a manifest
     *  entry, and wires it into a product/download with its tools. */
    const OTHER_EXT = ['epub','mobi','zip'];

    // ----- queries -----------------------------------------------------------

    /**
     * Freshly-uploaded, still-unattached rows the given user owns, by id — the guard against
     * attaching another user's file to your turn. Returns arrays (never rows).
     *
     * @param  array  $attachmentIds the ids the client asked to attach
     * @param  string $userId        the owner (the acting user)
     * @return array<int,array>      the owned, pending attachment rows
     */
    public function pendingForUser(array $attachmentIds, $userId): array
    {
        $ids = array_values(array_filter(array_map('strval', $attachmentIds)));
        if (!$ids) { return []; }
        $db = $this->getAdapter();
        return $db->fetchAll(
            $this->activeSelect()
                 ->where('attachment_id IN (?)', $ids)
                 ->where('user_id = ?', (string) $userId)
                 ->where('message_id IS NULL')          // only still-pending rows
                 ->order('created_at ASC')
        );
    }

    /**
     * Link freshly-uploaded rows to the conversation + message that carried them — scoped to the
     * owner so it can never touch another user's rows.
     *
     * @param  array  $attachmentIds  the ids being sent
     * @param  string $conversationId the thread they now belong to
     * @param  string $messageId      the user message that carried them
     * @param  string $userId         the owner
     * @return int                    rows linked
     */
    public function linkToMessage(array $attachmentIds, $conversationId, $messageId, $userId): int
    {
        $ids = array_values(array_filter(array_map('strval', $attachmentIds)));
        if (!$ids) { return 0; }
        $db = $this->getAdapter();
        return (int) $this->update(
            ['conversation_id' => (string) $conversationId, 'message_id' => (string) $messageId],
            implode(' AND ', [
                $db->quoteInto('attachment_id IN (?)', $ids),
                $db->quoteInto('user_id = ?', (string) $userId),   // never cross owners
                'message_id IS NULL',                              // only still-pending rows
                'deleted = 0',
            ])
        );
    }

    // ----- classification ----------------------------------------------------

    /** Is this filename an accepted attachment type? */
    public static function accepted($filename): bool
    {
        $ext = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));
        return in_array($ext, self::TEXT_EXT, true)
            || in_array($ext, self::DOC_EXT, true)
            || in_array($ext, self::IMAGE_EXT, true)
            || in_array($ext, self::OTHER_EXT, true);
    }

    /** The attachment kind for a filename: 'image' (read by vision) or 'file' (read as text). */
    public static function kindFor($filename): string
    {
        return in_array(strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION)), self::IMAGE_EXT, true)
            ? 'image' : 'file';
    }

    // ----- extraction --------------------------------------------------------

    /**
     * Best-effort readable text for a file. Returns the text, '' (tried, nothing usable), or null
     * (a type we don't read — image/binary).
     *
     * @param  string $filename original filename (drives the type)
     * @param  string $mime     the detected mime type
     * @param  string $bytes    the raw file bytes
     * @return string|null      extracted text, '' if none, or null if unreadable by design
     */
    public static function extractText($filename, $mime, $bytes): ?string
    {
        $ext  = strtolower(pathinfo((string) $filename, PATHINFO_EXTENSION));
        $mime = (string) $mime;

        if (in_array($ext, self::TEXT_EXT, true) || strpos($mime, 'text/') === 0 || $mime === 'application/json') {
            $t = (string) $bytes;
            if (!mb_check_encoding($t, 'UTF-8')) { $t = (string) @mb_convert_encoding($t, 'UTF-8', 'UTF-8'); }
            if ($ext === 'html' || $ext === 'htm') { $t = strip_tags(preg_replace('~<(script|style)\b[^>]*>.*?</\1>~is', ' ', $t)); }
            return self::_cap(self::_clean($t));
        }
        if ($ext === 'pdf' || $mime === 'application/pdf') { return self::_cap(self::_pdfText((string) $bytes)); }
        if ($ext === 'docx' || $mime === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            return self::_cap(self::_docxText((string) $bytes));
        }
        return null;   // image / binary — stored, but no text (an image is read by vision)
    }

    protected static function _clean($t): string
    {
        $t = html_entity_decode((string) $t, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $t = preg_replace('~[^\S\n]+~u', ' ', str_replace(["\r\n", "\r"], "\n", $t));
        $t = preg_replace('~[ \t]*\n[ \t]*~', "\n", $t);
        return trim(preg_replace('~\n{3,}~', "\n\n", $t));
    }

    protected static function _cap($t): string
    {
        $t = (string) $t;
        return mb_strlen($t) > self::EXTRACT_CAP ? mb_substr($t, 0, self::EXTRACT_CAP) . ' …[truncated]' : $t;
    }

    /** Pull text from a DOCX (its main document part). ZipArchive when present, else PharData. */
    protected static function _docxText($raw): string
    {
        $zipPath = tempnam(sys_get_temp_dir(), 'agdocx') . '.zip';
        @file_put_contents($zipPath, (string) $raw);
        $xml = '';
        try {
            if (class_exists('ZipArchive')) {
                $z = new ZipArchive();
                if ($z->open($zipPath) === true) { $xml = (string) $z->getFromName('word/document.xml'); $z->close(); }
            } elseif (class_exists('PharData')) {
                $phar = new PharData($zipPath);
                if (isset($phar['word/document.xml'])) { $xml = (string) file_get_contents($phar['word/document.xml']->getPathname()); }
            }
        } catch (Throwable $e) { $xml = ''; }
        @unlink($zipPath);
        if ($xml === '') { return ''; }
        $xml = preg_replace('~<w:tab\b[^>]*/?>~', "\t", $xml);
        $xml = preg_replace('~</w:p>~', "\n", $xml);            // paragraph → newline
        return self::_clean(strip_tags($xml));
    }

    /** Best-effort PDF text: decompress FlateDecode streams, scan Tj/TJ text-show operators. */
    protected static function _pdfText($raw): string
    {
        $raw = (string) $raw;
        $out = '';
        if (preg_match_all('~stream\r?\n(.*?)\r?\nendstream~s', $raw, $m)) {
            foreach ($m[1] as $chunk) {
                $data = @gzuncompress($chunk);
                if ($data === false) { $data = @gzinflate($chunk); }
                if ($data === false) { $data = $chunk; }        // possibly uncompressed
                $out .= self::_pdfOps($data) . "\n";
            }
        } else {
            $out = self::_pdfOps($raw);
        }
        return self::_clean($out);
    }

    /** Extract the shown strings from a PDF content stream's Tj / TJ operators. */
    protected static function _pdfOps($data): string
    {
        $text = '';
        if (preg_match_all('~\[(?:[^\]\\\\]|\\\\.)*\]\s*TJ|\((?:[^()\\\\]|\\\\.)*\)\s*Tj~s', (string) $data, $ops)) {
            foreach ($ops[0] as $op) {
                if (preg_match_all('~\((?:[^()\\\\]|\\\\.)*\)~s', $op, $ss)) {
                    foreach ($ss[0] as $s) { $text .= self::_pdfStr($s); }
                }
                $text .= ' ';
            }
        }
        return $text;
    }

    /** Decode one parenthesized PDF string literal (unescape \n \t \( \\ and octal \ddd). */
    protected static function _pdfStr($s): string
    {
        $s = substr((string) $s, 1, -1);
        return (string) preg_replace_callback('~\\\\([nrtbf()\\\\]|[0-7]{1,3})~', function ($m) {
            $map = ['n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\f", '(' => '(', ')' => ')', '\\' => '\\'];
            return $map[$m[1]] ?? chr(octdec($m[1]));
        }, $s);
    }
}
