<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * System_Form_UserPrefs — the shared base for a signed-in user's own admin-shell preferences: the
 * dashboard layout + widget visibility (System_Service_Dashboard) and the sidebar sort order
 * (System_Service_Nav) (TIGER-528).
 *
 * These are per-user writes with no prose, but they ARE writes a cross-site page could otherwise make
 * a signed-in admin's browser perform, so they validate a `_csrf` token like every other write. The
 * sidebar renders on every admin page — the dashboard included — so the three forms share ONE salt:
 * the token the sidebar renders and the one the dashboard renders are the same value.
 *
 * The payload fields are JSON strings the services parse fail-soft (a malformed or oversized value is
 * a quiet no-op, never an error), so they are declared without filters or validators.
 */
abstract class System_Form_UserPrefs extends Tiger_Form
{
    /**
     * One token for every admin-shell preference write.
     *
     * @return string
     */
    protected function csrfSalt(): string
    {
        return 'System_Form_UserPrefs';
    }

    /**
     * An optional, unfiltered field — the service owns parsing.
     *
     * @param  string $name the element/param name
     * @return array        [type, name, options]
     */
    protected function _field(string $name): array
    {
        return ['text', $name, ['required' => false, 'filters' => [], 'validators' => []]];
    }
}
