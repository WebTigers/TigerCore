<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_MicroId — a compact, human-friendly receipt / order / ticket number from a micro timestamp.
 *
 * The problem it solves: a UUID PK is right for the database but wrong on a receipt, an order page, or a
 * support ticket — nobody reads back "a1b2c3d4-…" over the phone. This mints a short, roughly time-sortable
 * code from the current micro timestamp instead, e.g. `8C-HY-IG-VC-6`, and can decode it back to the
 * timestamp. The UUID stays the internal key; this is the display + lookup number beside it.
 *
 * HOW: the micro timestamp (`sprintf('%.6f', microtime(true))`, e.g. `1760000000.699022`) has its dot
 * stripped to a 16-digit integer and is base-36 encoded (0-9A-Z) into ~10 characters, then grouped with
 * dashes every N characters for readability (configurable — 2, 3, 4…; 0 = no dashes). Because it is derived
 * from time it is roughly sortable by creation, and `getTimestampFromReceiptNumber()` reverses it losslessly.
 *
 * NOT UNIQUE BY ITSELF: two mints in the same microsecond collide, so a caller that uses this as a stored
 * key MUST put a UNIQUE index on the column and retry on a duplicate (a fresh `getReceipt()` each attempt).
 * It is a readable number, not a primary key — reach for Tiger_Uuid when you need guaranteed uniqueness.
 *
 * The base-36 codec (`encode`/`decode`) is the alphaID algorithm by Kevin van Zonneveld et al.
 * (http://kevin.vanzonneveld.net, New BSD) — handles any integer up to 2^53 (9007199254740992), which a
 * 16-digit unix-micro timestamp stays well under until the year ~2286. Requires ext-bcmath.
 *
 * @api
 */
class Tiger_MicroId
{
    /** The base-36 alphabet the codec maps onto (0-9 then A-Z) — uppercase; decode folds input to match. */
    private const INDEX = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     * A fresh receipt number for "now" — the usual entry point.
     *
     * @param  int $group dash grouping: a dash every N characters (0/negative = no dashes). Default 2.
     * @return string e.g. `8C-HY-IG-VC-6`
     */
    public static function getReceipt(int $group = 2): string
    {
        // %.6f pins six fractional digits so the stamp is always 16 digits — deterministic encoding and a
        // lossless timestamp round-trip (the original relied on float-to-string precision, which drifts).
        return self::getReceiptNumberFromTimestamp(sprintf('%.6f', microtime(true)), $group);
    }

    /**
     * Encode a specific micro timestamp into a receipt number.
     *
     * @param  string $timestamp a micro timestamp like `1760000000.699022` (the dot is optional)
     * @param  int    $group     dash grouping (a dash every N characters; 0/negative = no dashes)
     * @return string the grouped receipt number
     */
    public static function getReceiptNumberFromTimestamp(string $timestamp, int $group = 2): string
    {
        $numberStamp = str_replace('.', '', $timestamp);
        return self::_group(self::encode($numberStamp), $group);
    }

    /**
     * Decode a receipt number back to its micro timestamp (dashes and grouping are ignored; case-insensitive).
     *
     * @param  string $receipt a receipt number, e.g. `XC-8S-LQ-8B-HD` or `XC8SLQ8BHD`
     * @return string the micro timestamp, e.g. `1760000000.699022`
     */
    public static function getTimestampFromReceiptNumber(string $receipt): string
    {
        $encoded     = strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $receipt));
        $numberStamp = self::decode($encoded);
        return substr($numberStamp, 0, 10) . '.' . substr($numberStamp, -6);
    }

    /**
     * Translate a (possibly very large) integer to its short base-36 string (the alphaID codec).
     *
     * @param  string|int $in     the integer to encode (string-safe for values near 2^53)
     * @param  int|false  $pad_up pad the output up to at least this many characters (false = no padding)
     * @return string the base-36 encoding
     */
    public static function encode($in, $pad_up = false): string
    {
        $index = self::INDEX;
        $base  = strlen($index);

        if (is_numeric($pad_up)) {
            $pad_up--;
            if ($pad_up > 0) {
                $in += pow($base, $pad_up);
            }
        }

        $out = '';
        for ($t = ($in != 0) ? floor(log($in, $base)) : 0; $t >= 0; $t--) {
            $bcp = bcpow((string) $base, (string) $t);
            $a   = (int) floor($in / $bcp) % $base;
            $out .= substr($index, $a, 1);
            $in  -= ($a * $bcp);
        }

        return strrev($out);
    }

    /**
     * Translate a base-36 string produced by encode() back to its integer (as a string, for big values).
     *
     * @param  string    $in     the base-36 string
     * @param  int|false $pad_up the same padding value used at encode time (false = none)
     * @return string the decoded integer, as a decimal string
     */
    public static function decode($in, $pad_up = false): string
    {
        $index = self::INDEX;
        $base  = strlen($index);

        $in  = strrev($in);
        $out = 0;
        $len = strlen($in) - 1;
        for ($t = 0; $t <= $len; $t++) {
            $bcpow = bcpow((string) $base, (string) ($len - $t));
            $out   = $out + strpos($index, substr($in, $t, 1)) * $bcpow;
        }

        if (is_numeric($pad_up)) {
            $pad_up--;
            if ($pad_up > 0) {
                $out -= pow($base, $pad_up);
            }
        }

        $sout = sprintf('%F', $out);
        return substr($sout, 0, strpos($sout, '.'));
    }

    /**
     * Insert a dash every $n characters for readability.
     *
     * @param  string $s the ungrouped code
     * @param  int    $n a dash every N characters (0/negative = return unchanged)
     * @return string
     */
    private static function _group(string $s, int $n): string
    {
        if ($n <= 0) { return $s; }
        return implode('-', str_split($s, $n));
    }
}
