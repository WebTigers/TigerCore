<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * klingon-to-piqad — convert ROMANIZED Klingon (Marc Okrand's Latin transcription) into the
 * pIqaD script (Unicode Private-Use-Area, the ConScript Unicode Registry block U+F8D0–U+F8FF),
 * which the bundled themes/puma/assets/fonts/klingon/pIqaD.ttf renders for the `tlh` locale.
 *
 * This is the authoring bridge for the Klingon translation: Product supplies real Klingon in the
 * standard romanized form (e.g. "nuqneH", "Qapla'", "qatlh Tiger?"), this script transliterates it
 * to pIqaD codepoints EXACTLY — Okrand's 26 letters map 1:1 onto the CSUR block — and the result is
 * dropped into the `languages/tlh/*.php` values. Case matters in Klingon (D, H, I, Q, S are distinct
 * letters), so keep the romanization exactly as written. Non-letters (spaces, digits, punctuation,
 * brand names) pass through untouched.
 *
 * Usage:
 *   echo "nuqneH" | php bin/klingon-to-piqad.php        # stdin  -> pIqaD on stdout
 *   php bin/klingon-to-piqad.php "Qapla'"               # arg    -> pIqaD on stdout
 *   php bin/klingon-to-piqad.php < romanized-klingon.txt
 */

// CSUR pIqaD block: the 26 Klingon letters, in codepoint order U+F8D0.. (digraphs are single letters).
$letters = ['a','b','ch','D','e','gh','H','I','j','l','m','n','ng','o','p','q','Q','r','S','t','tlh','u','v','w','y',"'"];
$map = [];
foreach ($letters as $i => $l) {
    $map[$l] = mb_convert_encoding('&#' . (0xF8D0 + $i) . ';', 'UTF-8', 'HTML-ENTITIES');
}

/**
 * Transliterate one string of romanized Klingon to pIqaD. Greedy longest-match (tlh, ch, gh, ng
 * before their single letters); anything that isn't a Klingon letter is emitted verbatim.
 */
function klingonToPiqad(string $text, array $map): string
{
    $out = '';
    $n   = strlen($text);
    for ($i = 0; $i < $n; ) {
        $matched = false;
        // try 3-, then 2-, then 1-char letters (byte-safe: Klingon romanization is ASCII)
        foreach ([3, 2, 1] as $len) {
            $chunk = substr($text, $i, $len);
            if ($chunk !== false && isset($map[$chunk])) {
                $out .= $map[$chunk];
                $i   += $len;
                $matched = true;
                break;
            }
        }
        if (!$matched) { $out .= $text[$i]; $i++; }
    }
    return $out;
}

$input = $argc > 1 ? implode(' ', array_slice($argv, 1)) : stream_get_contents(STDIN);
echo klingonToPiqad($input, $map);
if ($argc > 1) { echo "\n"; }
