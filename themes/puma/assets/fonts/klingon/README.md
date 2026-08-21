# Klingon (pIqaD) font — drop-in

The `tlh` (Klingon) locale renders the site in the **pIqaD** script. To enable it, drop a pIqaD
TrueType font here named **`pIqaD.ttf`** — the `@font-face` in `themes/puma/assets/default.css`
(`font-family: 'pIqaD'`) already points at `assets/fonts/klingon/pIqaD.ttf`, and
`html[lang="tlh"]` switches the body font to it. Until the file exists the browser just falls back
to the default font (nothing breaks).

## How pIqaD text is encoded
pIqaD has no official Unicode block; the community encodes it in the **Unicode Private Use Area**
(the ConScript Unicode Registry range **U+F8D0–U+F8FF**). A pIqaD font maps those PUA codepoints to
the Klingon glyphs. So the translated `languages/tlh/*.php` strings should contain those PUA
codepoints (or a Latin transliteration if you'd rather not require the font at all).

## ⚠️ License — verify before committing the binary
Tiger ships under **BSD-3**, so any font committed to this repo must carry a license that permits
redistribution in an open-source project. Klingon-related IP is owned by CBS/Paramount, and several
"free" pIqaD fonts are **non-commercial / no-redistribution** only. **Do not commit a `.ttf` whose
license forbids redistribution.** Confirm the exact license first (SIL OFL is ideal — it's made for
exactly this). Common starting points to evaluate: the Klingon Language Institute (kli.org), Evertype,
and community pIqaD fonts — check each font's license file, don't assume "free download" = "free to
redistribute."

If the chosen font is **not** redistributable, the alternatives are: (a) load it from an allowed CDN
instead of committing it, or (b) use a Latin transliteration in `languages/tlh/*.php` and skip the
font entirely.

Filename expected by the CSS: **`pIqaD.ttf`** (add `.woff2` too if you have it and I'll wire a
preferred `src`).
