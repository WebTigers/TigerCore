# Klingon (pIqaD) font

The `tlh` (Klingon) locale renders the site in the **pIqaD** script. This is wired up in
`themes/puma/assets/default.css`: an `@font-face` (`font-family: "pIqaD"`) points at `pIqaD.ttf`
here, and `html[lang="tlh"]` switches the body font to it.

## Bundled font
- **`pIqaD.ttf`** — the **Klingon pIqaD HaSta** typeface by Mike Neff (qa'vaj), tidied by Michael
  Everson, redistributed by the "Klingon Project Re-Distributors."
- **License: SIL Open Font License 1.1** (`OFL.txt`). OFL explicitly permits bundling the font in
  software, so it's fine to ship in this BSD-3 repo; the only OFL constraints are that the font
  can't be sold on its own and any *modified* version can't reuse the reserved font name. We bundle
  it unmodified.
- Source: <https://github.com/ChewKeanHo/visuals-fonts-klingon> (release v1.0.0), originally
  <https://www.evertype.com/fonts/tlh/>.

## How pIqaD text is encoded
pIqaD has no official Unicode block; it's encoded in the **Unicode Private Use Area** (the ConScript
Unicode Registry range **U+F8D0–U+F8FF**). This font maps those PUA codepoints to the Klingon glyphs,
so the translated `languages/tlh/*.php` strings should contain those PUA codepoints. (Until Product
fills in real pIqaD text the stubs are English placeholders, which render in the fallback font.)

> Note: this font maps **only** the PUA codepoints to pIqaD; ordinary Latin letters render as Latin.
> So English placeholder text shows as English even with the font active — the strings must actually
> contain the PUA codepoints to display as Klingon.

## Wiring in real Klingon translations (the Product handoff)
Product supplies the translations as **romanized Klingon** — Marc Okrand's standard Latin transcription
(e.g. `nuqneH`, `Qapla'`, `qatlh Tiger?`). Case is significant (`D H I Q S` and the digraphs `ch gh ng
tlh` are distinct letters), so keep the romanization exactly as written. Convert it to pIqaD with the
bundled authoring tool — the mapping is **1:1 and exact** for real Klingon:

```bash
php bin/klingon-to-piqad.php "nuqneH"          # -> the pIqaD codepoints
echo "Qapla'" | php bin/klingon-to-piqad.php   # stdin also works
```

Paste the output into the matching value in `core/languages/tlh/*.php`,
`modules/*/languages/tlh/*.php`, and `themes/puma/languages/tlh/theme.php`. Brand names (Tiger,
WordPress, GitHub) stay untranslated by convention. Nothing else is needed — the `@font-face` +
`html[lang="tlh"]` wiring already renders it.

## Swapping the typeface
The mirror also ships **Mandel** and **vaHbo** styles (same OFL). To use one instead, drop it in as
`pIqaD.ttf` (or add it alongside and point the `@font-face src` at it). A `.woff2` can be added as a
preferred `src` for smaller downloads.
