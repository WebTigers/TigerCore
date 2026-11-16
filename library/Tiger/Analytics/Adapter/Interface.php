<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Analytics_Adapter_Interface — the contract every analytics REPORTING provider implements.
 *
 * An adapter answers three questions for ONE org: is it set up (`isConfigured`), what does the traffic
 * look like (`report`, in the normalized shape documented on Tiger_Analytics), and does the connection
 * work (`test`). Every method is org-explicit — an adapter never reads "whoever is signed in" — and
 * none may throw: a failure is a value (`['error' => …]` from report, `ok=false` from test), so a
 * dashboard widget can never be broken by a provider outage.
 *
 * Extend Tiger_Analytics_Adapter_Abstract rather than implementing this directly: it carries the
 * normalizer, the error envelope and the org-level secret read.
 *
 * Tracking TAGS are not part of this contract — each provider's module emits its own tag (its own
 * plugin + enabled flag). An adapter is about setup state and reporting only.
 *
 * @api
 */
interface Tiger_Analytics_Adapter_Interface
{
    /**
     * The provider key this adapter is registered under (e.g. `ga`, `matomo`).
     *
     * @return string
     */
    public function name(): string;

    /**
     * A human label for the provider picker (e.g. "Google Analytics").
     *
     * @return string
     */
    public function label(): string;

    /**
     * Is reporting set up for this org (enough config + credentials to make a report call)?
     *
     * @param  string $orgId the org whose settings to read
     * @return bool
     */
    public function isConfigured(string $orgId): bool;

    /**
     * The traffic report for this org, normalized (see Tiger_Analytics::report() for the shape), or
     * `['error' => <i18n key>, 'code' => <machine code>]` on any failure. Never throws.
     *
     * @param  string $orgId  the org whose settings to read
     * @param  array  $period `days` (1..365, default 28) and `fresh` (bool — bypass any cache)
     * @return array
     */
    public function report(string $orgId, array $period = []): array;

    /**
     * A live connection self-test: `['ok' => bool, 'code' => string, 'message' => string]` plus an
     * optional `hint` / `detail`. Never throws, never echoes a credential.
     *
     * @param  string $orgId the org whose settings to read
     * @return array
     */
    public function test(string $orgId): array;
}
