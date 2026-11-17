<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Backup_Form_Write — the shared base for every write on the Backup & Restore screen (TIGER-528).
 *
 * The screen renders ONE `_csrf` token and drives run / remove / restore / saveSettings (over `/api`)
 * plus the upload-restore controller action from it, so the forms share one salt — otherwise each
 * would mint its own token and every call after the first would fail "token expired".
 *
 * `components` arrives as an array (`components[]`) or a comma list, so it carries no filter
 * (`StringTrim` casts an array with a warning); the service's `_components()` normalizes it.
 */
abstract class Backup_Form_Write extends Tiger_Form
{
    /**
     * One token for every Backup screen write.
     *
     * @return string
     */
    protected function csrfSalt(): string
    {
        return 'Backup_Form_Write';
    }

    /**
     * An optional, unfiltered, unvalidated field — the service owns its normalization + refusal.
     *
     * @param  string $name the element/param name
     * @return array        [type, name, options]
     */
    protected function _field(string $name): array
    {
        return ['text', $name, ['required' => false, 'filters' => [], 'validators' => []]];
    }
}
