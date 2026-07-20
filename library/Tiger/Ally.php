<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Ally — a nominal accessibility (ADA / WCAG-A) inspector for HTML. READ-ONLY: it looks and
 * reports, it never rewrites. The engine behind the TigerAlly module (scan CMS pages / pasted HTML)
 * and a handy self-check for any HTML the platform generates (e.g. the Markdown→HTML converter).
 *
 * "Nominal" is the bar: catch the cheap, high-value gaps a static pass can find — images with no
 * alt, controls with no accessible name, inputs with no label, skipped heading levels, tables with
 * no header cells, duplicate ids, a missing document language, positive tabindex. It does NOT judge
 * colour contrast, focus order, or anything needing a live browser/CSS — those aren't statically
 * knowable, and pretending otherwise would just be noise.
 *
 * @api
 */
class Tiger_Ally
{
    const ERROR   = 'error';
    const WARNING = 'warning';

    /**
     * Inspect an HTML string (a full document or a fragment) and return a structured report.
     *
     * @param  string $html the markup to inspect
     * @return array{passed:bool,summary:array{error:int,warning:int},findings:array<int,array>}
     */
    public static function inspect(string $html): array
    {
        $doc = self::_dom($html);
        $findings = [];
        if ($doc) {
            $xp = new DOMXPath($doc);
            self::_imgAlt($xp, $findings);
            self::_controlNames($xp, $findings);
            self::_inputLabels($xp, $findings);
            self::_headings($xp, $findings);
            self::_tables($xp, $findings);
            self::_duplicateIds($xp, $findings);
            // doc-lang only for a real full document — DOMDocument synthesizes an <html> wrapper
            // around a fragment, which would otherwise fire a false positive on every page body.
            if (preg_match('/<html[\s>]/i', $html)) { self::_docLang($xp, $findings); }
            self::_positiveTabindex($xp, $findings);
        }

        $summary = [self::ERROR => 0, self::WARNING => 0];
        foreach ($findings as $f) { $summary[$f['severity']] += $f['count']; }
        return [
            'passed'   => $summary[self::ERROR] === 0,
            'summary'  => $summary,
            'findings' => $findings,
        ];
    }

    /** Parse HTML (fragment-tolerant, UTF-8) into a DOMDocument, or null if unparseable/empty. */
    private static function _dom(string $html): ?DOMDocument
    {
        if (trim($html) === '') { return null; }
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);
        $ok = $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        return $ok ? $doc : null;
    }

    /** Append one finding (rule + severity + human message + a few offending markup samples). */
    private static function _add(array &$findings, string $rule, string $severity, string $message, array $nodes): void
    {
        if (!$nodes) { return; }
        $samples = [];
        foreach (array_slice($nodes, 0, 5) as $n) {
            $html = $n->ownerDocument->saveHTML($n);
            $html = preg_replace('/\s+/', ' ', trim((string) $html));
            $samples[] = mb_substr($html, 0, 160);
        }
        $findings[] = [
            'rule'     => $rule,
            'severity' => $severity,
            'message'  => $message,
            'count'    => count($nodes),
            'samples'  => $samples,
        ];
    }

    /** True when an element is explicitly hidden from the a11y tree (CSS display can't be known here). */
    private static function _hidden(DOMElement $el): bool
    {
        return $el->getAttribute('aria-hidden') === 'true'
            || $el->getAttribute('role') === 'presentation'
            || $el->getAttribute('role') === 'none'
            || $el->hasAttribute('hidden');
    }

    /** Does a control (a/button) have an accessible name? (text, aria-label(ledby), title, or an alt'd img.) */
    private static function _hasName(DOMElement $el): bool
    {
        if (trim($el->textContent) !== '') { return true; }
        if (trim($el->getAttribute('aria-label')) !== '' || trim($el->getAttribute('title')) !== ''
            || trim($el->getAttribute('aria-labelledby')) !== '') { return true; }
        foreach ($el->getElementsByTagName('img') as $img) {
            if (trim($img->getAttribute('alt')) !== '') { return true; }
        }
        return false;
    }

    // ---- rules ----------------------------------------------------------------

    /** Images must carry an alt attribute (alt="" is fine for decorative). */
    private static function _imgAlt(DOMXPath $xp, array &$findings): void
    {
        $bad = [];
        foreach ($xp->query('//img[not(@alt)]') as $img) {
            if (!self::_hidden($img)) { $bad[] = $img; }
        }
        self::_add($findings, 'img-alt', self::ERROR,
            'Image has no alt attribute (use alt="" if purely decorative).', $bad);
    }

    /** Links and buttons need an accessible name (an icon-only control with no label is invisible to SR). */
    private static function _controlNames(DOMXPath $xp, array &$findings): void
    {
        $links = $buttons = [];
        foreach ($xp->query('//a[@href]') as $a) {
            if (!self::_hidden($a) && !self::_hasName($a)) { $links[] = $a; }
        }
        foreach ($xp->query('//button') as $b) {
            if (!self::_hidden($b) && !self::_hasName($b)) { $buttons[] = $b; }
        }
        self::_add($findings, 'link-name', self::ERROR, 'Link has no accessible text.', $links);
        self::_add($findings, 'button-name', self::ERROR, 'Button has no accessible name (add text or aria-label).', $buttons);
    }

    /** Form fields should have a name — a <label for>, wrapping <label>, aria-label(ledby), or title. */
    private static function _inputLabels(DOMXPath $xp, array &$findings): void
    {
        $skip = ['hidden', 'submit', 'button', 'reset', 'image'];
        $bad  = [];
        foreach ($xp->query('//input | //select | //textarea') as $el) {
            if ($el->tagName === 'input' && in_array(strtolower($el->getAttribute('type')), $skip, true)) { continue; }
            if (self::_hidden($el)) { continue; }
            if (trim($el->getAttribute('aria-label')) !== '' || trim($el->getAttribute('aria-labelledby')) !== ''
                || trim($el->getAttribute('title')) !== '') { continue; }
            $id = $el->getAttribute('id');
            if ($id !== '' && $xp->query('//label[@for="' . addslashes($id) . '"]')->length > 0) { continue; }
            if ($xp->query('ancestor::label', $el)->length > 0) { continue; }
            $bad[] = $el;
        }
        self::_add($findings, 'input-label', self::WARNING,
            'Form field has no associated label (a placeholder is not a label).', $bad);
    }

    /** Heading levels shouldn't skip (h2 → h4), and headings shouldn't be empty. */
    private static function _headings(DOMXPath $xp, array &$findings): void
    {
        $empty   = [];
        $skipped = [];
        $prev    = 0;
        foreach ($xp->query('//h1 | //h2 | //h3 | //h4 | //h5 | //h6') as $h) {
            $lvl = (int) substr($h->tagName, 1);
            if (trim($h->textContent) === '') { $empty[] = $h; }
            if ($prev > 0 && $lvl > $prev + 1) { $skipped[] = $h; }
            $prev = $lvl;
        }
        self::_add($findings, 'heading-empty', self::WARNING, 'Heading is empty.', $empty);
        self::_add($findings, 'heading-order', self::WARNING, 'Heading level skips one or more levels.', $skipped);
    }

    /** Data tables should have header cells (<th>). */
    private static function _tables(DOMXPath $xp, array &$findings): void
    {
        $bad = [];
        foreach ($xp->query('//table') as $t) {
            if ($xp->query('.//th', $t)->length === 0) { $bad[] = $t; }
        }
        self::_add($findings, 'table-headers', self::WARNING, 'Table has no header cells (<th>).', $bad);
    }

    /** id attributes must be unique (duplicate ids break label/aria references + scripting). */
    private static function _duplicateIds(DOMXPath $xp, array &$findings): void
    {
        $seen = [];
        $dups = [];
        foreach ($xp->query('//*[@id]') as $el) {
            $id = $el->getAttribute('id');
            if ($id === '') { continue; }
            if (isset($seen[$id])) { $dups[] = $el; } else { $seen[$id] = true; }
        }
        self::_add($findings, 'duplicate-id', self::WARNING, 'Duplicate id attribute.', $dups);
    }

    /** A full document should declare a language on <html>. (Fragments have no <html> — skipped.) */
    private static function _docLang(DOMXPath $xp, array &$findings): void
    {
        $bad = [];
        foreach ($xp->query('//html') as $html) {
            if (trim($html->getAttribute('lang')) === '') { $bad[] = $html; }
        }
        self::_add($findings, 'doc-lang', self::WARNING, '<html> has no lang attribute.', $bad);
    }

    /** Positive tabindex hijacks the natural tab order. */
    private static function _positiveTabindex(DOMXPath $xp, array &$findings): void
    {
        $bad = [];
        foreach ($xp->query('//*[@tabindex]') as $el) {
            if ((int) $el->getAttribute('tabindex') > 0) { $bad[] = $el; }
        }
        self::_add($findings, 'positive-tabindex', self::WARNING,
            'Positive tabindex overrides the natural focus order.', $bad);
    }
}
