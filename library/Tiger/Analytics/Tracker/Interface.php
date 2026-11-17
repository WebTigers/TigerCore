<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Analytics_Tracker_Interface — the optional SEND side of an analytics provider: a server-side event.
 *
 * Separate from Tiger_Analytics_Adapter_Interface (reporting) on purpose: an adapter that only reports keeps
 * working unchanged, and one that can also receive events implements this as well. Tiger_Analytics::track()
 * sends to every registered adapter that implements it and canTrack() the org.
 *
 * The EVENT an adapter receives (already normalized by Tiger_Analytics::track()):
 *
 *   [
 *     'name'      => 'short_link_click',      // [a-z0-9_], <= 40 chars
 *     'params'    => ['slug' => 'spring', …], // scalar values only
 *     'client_id' => 'opaque stable id',       // the visitor (GA's _ga cookie id when present)
 *     'url'       => 'https://site/l/spring',  // the page the event happened on
 *     'referrer'  => 'https://…' | '',
 *     'ip'        => 'client ip' | '',
 *     'ua'        => 'user agent' | '',
 *   ]
 *
 * track() must be FAIL-SOFT and FAST (a short timeout): it runs in a request, and a slow or dead provider
 * must never break or stall the page that fired the event. Return false on any failure; never throw.
 *
 * @api
 * @since 1.65.0
 */
interface Tiger_Analytics_Tracker_Interface
{
    /**
     * Can this provider receive events for the org right now (its send credentials are configured)?
     *
     * @param  string $orgId the org
     * @return bool
     */
    public function canTrack(string $orgId): bool;

    /**
     * Send one event for the org.
     *
     * @param  string $orgId the org
     * @param  array  $event the normalized event (shape in the interface doc)
     * @return bool          true when the provider accepted it
     */
    public function track(string $orgId, array $event): bool;
}
