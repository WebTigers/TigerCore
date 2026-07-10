<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Location_Adapter_Interface — the capability contract every Location provider implements.
 *
 * An adapter declares which of the four operations it can do via capabilities(); the
 * Tiger_Location facade only calls an operation on an adapter that supports it (else it
 * throws Tiger_Location_Exception). Every operation returns Tiger_Location_Place(s), so the
 * payload is identical regardless of provider (AWS Location Service, Nominatim/OSM, Google,
 * Mapbox, an IP-geolocation vendor, …). Extend Tiger_Location_Adapter_Abstract rather than
 * implementing this directly — it defaults every op to "unsupported" so you override only
 * what you actually provide.
 *
 * @api
 */
interface Tiger_Location_Adapter_Interface
{
    const CAP_SUGGEST = 'suggest';   // partial text -> autocomplete predictions
    const CAP_GEOCODE = 'geocode';   // text -> structured address(es) + lat/lng
    const CAP_REVERSE = 'reverse';   // lat/lng -> address
    const CAP_IP      = 'ip';        // IP -> approximate location

    /**
     * The CAP_* operations this adapter supports.
     *
     * @return string[] the CAP_* operations this adapter supports
     */
    public function capabilities(): array;

    /**
     * Autocomplete a partial address. Returns Tiger_Location_Place[] — usually id + label
     * (call geocode()/retrieve on a selection for the full structure, or the provider may
     * already include it).
     *
     * @param  string $query the partial address text
     * @param  array  $opts  provider options
     * @return Tiger_Location_Place[]
     */
    public function suggest(string $query, array $opts = []): array;

    /**
     * Geocode free text to structured address(es) with coordinates.
     *
     * @param  string $query the address text to geocode
     * @param  array  $opts  provider options
     * @return Tiger_Location_Place[]
     */
    public function geocode(string $query, array $opts = []): array;

    /**
     * Reverse-geocode coordinates to the nearest address.
     *
     * @param  float $lat  the latitude
     * @param  float $lng  the longitude
     * @param  array $opts provider options
     * @return Tiger_Location_Place|null the nearest place, or null when unplaceable
     */
    public function reverse(float $lat, float $lng, array $opts = []): ?Tiger_Location_Place;

    /**
     * Approximate location for an IP address.
     *
     * @param  string $ip   the IP address to look up
     * @param  array  $opts provider options
     * @return Tiger_Location_Place|null the place, or null when unplaceable
     */
    public function ip(string $ip, array $opts = []): ?Tiger_Location_Place;
}
