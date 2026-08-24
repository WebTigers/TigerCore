<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Cms_Form_Page — create/edit a CMS page, layout, or partial.
 *
 * A declarative Tiger_Form schema (elements only — the view owns all markup; the
 * base strips decorators to ViewHelper-only and adds CSRF). Validated by
 * Cms_Service_Page::save() before the write. Only `title` is hard-required here:
 * the service derives a page_key from the slug/title and applies type-specific
 * rules, so layouts/partials (no slug) and pages (no explicit key) both validate.
 *
 * Note: `body` is deliberately NOT StripTags-filtered — a body is template source
 * (HTML / Markdown / PHTML), so stripping tags would corrupt it.
 *
 * @api
 */
class Cms_Form_Page extends Tiger_Form
{
    protected function elements(): array
    {
        $control = ['class' => 'form-control'];
        $select  = ['class' => 'form-select'];

        return [
            ['hidden', 'page_id', []],

            ['text', 'title', [
                'required' => true,
                'filters'  => ['StringTrim'],
                'attribs'  => array_merge($control, ['id' => 'cms-title', 'maxlength' => 255]),
            ]],

            ['text', 'slug', [
                'filters' => ['StringTrim'],
                'attribs' => array_merge($control, ['id' => 'cms-slug', 'maxlength' => 191]),
            ]],

            ['text', 'page_key', [
                'filters' => ['StringTrim'],
                'attribs' => array_merge($control, ['id' => 'cms-key', 'maxlength' => 191]),
            ]],

            ['select', 'type', [
                'multiOptions' => [
                    'page'    => $this->_t('cms.type.page'),
                    'layout'  => $this->_t('cms.type.layout'),
                    'partial' => $this->_t('cms.type.partial'),
                    'block'   => $this->_t('cms.type.block'),
                ],
                'value'        => 'page',
                'attribs'      => array_merge($select, ['id' => 'cms-type']),
            ]],

            ['select', 'format', [
                'multiOptions' => [
                    'html'     => $this->_t('cms.format.html'),
                    'markdown' => $this->_t('cms.format.markdown'),
                    'phtml'    => $this->_t('cms.format.phtml'),
                    'builder'  => $this->_t('cms.format.builder'),
                ],
                'value'        => 'html',
                'attribs'      => array_merge($select, ['id' => 'cms-format']),
            ]],

            ['select', 'status', [
                'multiOptions' => [
                    'draft'     => $this->_t('cms.status.draft'),
                    'published' => $this->_t('cms.status.published'),
                    'archived'  => $this->_t('cms.status.archived'),
                ],
                'value'        => 'draft',
                'attribs'      => array_merge($select, ['id' => 'cms-status']),
            ]],

            ['select', 'locale', [
                'multiOptions' => [
                    'en' => $this->_t('cms.locale.en'),
                    'es' => $this->_t('cms.locale.es'),
                ],
                'value'        => 'en',
                'attribs'      => array_merge($select, ['id' => 'cms-locale']),
            ]],

            ['text', 'layout_key', [
                'filters' => ['StringTrim'],
                'attribs' => array_merge($control, ['id' => 'cms-layout', 'maxlength' => 191]),
            ]],

            ['text', 'published_at', [
                'filters' => ['StringTrim'],
                'attribs' => array_merge($control, ['id' => 'cms-published-at', 'placeholder' => 'YYYY-MM-DD HH:MM:SS']),
            ]],

            ['textarea', 'body', [
                'filters' => ['StringTrim'],
                'attribs' => array_merge($control, ['id' => 'cms-body', 'rows' => 18, 'spellcheck' => 'false']),
            ]],

            // --- SEO & head (stored in the page `meta`; rendered into the public page) ---
            // The SEO title overrides <title>/og:title for this page (meta.seo.title); blank = the
            // page title. Read back by Seo_Service_Head::forRow().
            ['text', 'seo_title', [
                'filters' => ['StringTrim'],
                'attribs' => array_merge($control, ['id' => 'cms-seo-title', 'maxlength' => 255]),
            ]],
            ['textarea', 'meta_description', [
                'filters' => ['StringTrim'],
                'attribs' => array_merge($control, ['id' => 'cms-meta-description', 'rows' => 2, 'maxlength' => 320]),
            ]],
            // The per-page share image (og:image) — a MEDIA REFERENCE (a media_id, or an absolute
            // https URL when pasted), stored as meta.seo.og_image_id and resolved to a real URL +
            // true dimensions by Seo_Service_Head. Declared here so it validates and round-trips
            // with the form, but the VIEW renders it through the mediaField() picker helper (which
            // emits the very same hidden input) — so this element is never echoed itself.
            ['hidden', 'og_image_id', [
                'filters'    => ['StringTrim'],
                'validators' => [['Regex', false, ['pattern' => '#^(https?://\S+|[0-9a-fA-F-]{36})$#']]],
                'attribs'    => ['id' => 'cms-og-image'],
            ]],
            // Raw <head> additions (link/meta/style/script src) + end-of-body scripts. Admin-authored
            // (trusted) — output verbatim. Emptied by default; a tenant-safe editor would sanitize.
            ['textarea', 'head_html', [
                'attribs' => array_merge($control, ['id' => 'cms-head-html', 'rows' => 4, 'spellcheck' => 'false',
                    'placeholder' => '<link rel="stylesheet" href="…">   <meta …>   <script src="…"></script>']),
            ]],
            ['textarea', 'body_scripts', [
                'attribs' => array_merge($control, ['id' => 'cms-body-scripts', 'rows' => 4, 'spellcheck' => 'false',
                    'placeholder' => '<script src="…"></script>   <script>…</script>']),
            ]],
        ];
    }
}
