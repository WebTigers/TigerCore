<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Secret_Writer_Interface — the OPTIONAL write side of a secret backend.
 *
 * Most of the platform only reads secrets (resolve a credential at request time). Writing — seeding a
 * parameter, rotating a key — is an ops / provisioning concern used by setup tooling and the control
 * plane, never the request path. Only backends that support it implement this (SSM, Secrets Manager);
 * the Local/Env/File adapters are read-only. A caller capability-checks with
 * `instanceof Tiger_Secret_Writer_Interface` before writing.
 *
 * Both methods return a bool rather than throwing, mirroring the read side: a failed write is a false,
 * handled by the caller, not an exception to catch.
 *
 * @api
 */
interface Tiger_Secret_Writer_Interface
{
    /**
     * Store (create or overwrite) a secret at a backend-relative path.
     *
     * @param  string $path  the backend-relative reference
     * @param  string $value the secret value to store
     * @return bool   true on success
     */
    public function put(string $path, string $value): bool;

    /**
     * Remove a secret (idempotent — a missing secret is still a success).
     *
     * @param  string $path the backend-relative reference
     * @return bool   true on success
     */
    public function delete(string $path): bool;
}
