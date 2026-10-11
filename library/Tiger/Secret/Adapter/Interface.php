<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Secret_Adapter_Interface — the contract every secret backend implements.
 *
 * An adapter resolves a reference to a secret VALUE. The reference is whatever makes sense for the
 * backend: an SSM parameter path, an env var name, a file path, or (for the Local adapter) an inline
 * ciphertext blob. The Tiger_Secret facade dispatches by scheme and never learns how a backend finds
 * or decrypts the value — AWS SSM, HashiCorp Vault, env vars and `/run/secrets` are all the same shape
 * behind this interface.
 *
 * Two rules keep the platform resilient:
 *   - available() is capability-detection (SDK present, role reachable, feature configured). The facade
 *     skips an unavailable adapter rather than failing, so a missing cloud SDK on shared hosting is a
 *     no-op, never a fatal.
 *   - get() returns null for "not found / unavailable / unreadable" and MUST NOT throw for the ordinary
 *     missing case; the facade also guards every call in a try/catch as a backstop. A null is "secret
 *     unusable" — the caller treats it exactly as it would a missing credential today.
 *
 * @api
 */
interface Tiger_Secret_Adapter_Interface
{
    /**
     * Whether this backend can be used right now (SDK loaded, configured, reachable). The facade only
     * calls get() on an available adapter.
     *
     * @return bool
     */
    public function available(): bool;

    /**
     * Resolve a backend-relative reference to its secret value, or null when it cannot be resolved.
     *
     * @param  string $path the backend-relative reference (SSM path, env name, file path, ciphertext …)
     * @return string|null the secret value, or null when absent/unreadable
     */
    public function get(string $path): ?string;
}
