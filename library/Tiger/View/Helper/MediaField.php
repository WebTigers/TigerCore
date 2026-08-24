<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_View_Helper_MediaField — a form field that picks media via TigerMediaPicker.
 *
 * Renders a hidden input (holding the selected media_id, or a comma list when multiple)
 * plus a live preview and Choose/Clear buttons. The picker JS (tiger.media-picker.js,
 * loaded by the admin layout) auto-wires it — no per-field script.
 *
 *   <?= $this->mediaField('hero_image', $page->hero_image, ['kind' => 'image', 'label' => 'Hero image']) ?>
 *
 * Options: kind (restrict type), multiple (bool), label, id.
 *
 * @api
 */
class Tiger_View_Helper_MediaField extends Zend_View_Helper_Abstract
{
    /**
     * Render a media-picker form field (hidden input + live preview + Choose/Clear buttons).
     *
     * @param  string $name    the form field name (also the default id)
     * @param  string $value   the current media_id (or a comma list when multiple)
     * @param  array  $options kind, multiple (bool), label, id
     * @return string          the field's HTML markup
     */
    public function mediaField($name, $value = '', array $options = [])
    {
        $esc      = function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES); };
        $kind     = (string) ($options['kind'] ?? '');
        $multiple = !empty($options['multiple']) ? '1' : '0';
        // A caller-supplied label is already localized by the view; only the default needs a key.
        $label    = (string) ($options['label'] ?? $this->_t('core.media.field.choose', 'Choose media'));
        $id       = (string) ($options['id'] ?? $name);
        $value    = (string) $value;
        $hasVal   = trim($value) !== '';

        // Server-render the preview for an existing single value.
        $preview = '';
        if ($hasVal && $multiple === '0') {
            $model = new Tiger_Model_Media();
            $row   = $model->findById($value);
            if ($row) {
                $m = $row->toArray();
                // The preview conveys WHICH media is selected, so it carries real alt text rather
                // than being marked decorative; the non-image tile gets the same treatment.
                $altText  = $this->_t('core.media.field.preview_alt', 'Selected media preview');
                $fileText = $this->_t('core.media.field.file', 'Selected file');
                $preview = ($m['kind'] === 'image')
                    ? '<img src="' . $esc($model->thumbUrl($m)) . '" alt="' . $esc($altText) . '" style="width:56px;height:56px;object-fit:cover;border-radius:6px;">'
                    : '<span class="d-inline-flex align-items-center justify-content-center bg-body-secondary rounded" style="width:56px;height:56px;" title="' . $esc($fileText) . '">'
                      . '<i class="fa-solid fa-file" aria-hidden="true"></i><span class="visually-hidden">' . $esc($fileText) . '</span></span>';
            }
        }

        return '<div class="media-field d-flex align-items-center gap-2" data-media-field>'
            . '<div data-media-preview class="d-flex gap-1">' . $preview . '</div>'
            . '<input type="hidden" name="' . $esc($name) . '" id="' . $esc($id) . '" value="' . $esc($value) . '">'
            . '<button type="button" class="btn btn-sm btn-outline-primary" data-media-choose data-kind="' . $esc($kind) . '" data-multiple="' . $multiple . '">'
            . '<i class="fa-solid fa-photo-film me-1"></i>' . $esc($label) . '</button>'
            . '<button type="button" class="btn btn-sm btn-outline-secondary" data-media-clear' . ($hasVal ? '' : ' hidden') . '>'
            . $esc($this->_t('core.media.field.clear', 'Clear')) . '</button>'
            . '</div>';
    }

    /**
     * Translate a key, falling back to the English literal.
     *
     * A view helper has no `$this->t()`, so this routes through the registered `t` helper when a
     * view is attached (inheriting its source-locale fallback), then the registry translator, then
     * the literal. Fail-soft by design: this helper is also instantiated bare (no view, no
     * translator) in tests, and a raw key rendered into a button would be worse than English.
     *
     * @param  string $key      the translation key
     * @param  string $fallback the English literal to use when nothing resolves
     * @return string           the translated text, or the fallback
     */
    protected function _t($key, $fallback)
    {
        try {
            if ($this->view instanceof Zend_View_Interface && method_exists($this->view, 't')) {
                $out = (string) $this->view->t($key);
                if ($out !== '' && $out !== $key) { return $out; }
            }
            if (Zend_Registry::isRegistered('Zend_Translate')) {
                $tr  = Zend_Registry::get('Zend_Translate');
                $out = (string) $tr->translate($key);
                if ($out !== '' && $out !== $key) { return $out; }
            }
        } catch (Throwable $e) {
            // fail-open — a head/label lookup must never break a render
        }
        return $fallback;
    }
}
