<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Secret_Adapter_File — resolve a secret from a file on disk (the mounted-secret backend).
 *
 * Reached via the `file:` scheme: `file:/run/secrets/opensrs_api_key` → the file's contents. This is
 * the Docker / Kubernetes / systemd `LoadCredential=` convention — the orchestrator mounts each secret
 * as a file and the app reads it, no SDK and no cloud API. A single trailing newline (the usual
 * secret-file convention) is stripped; a missing, unreadable, or empty file resolves to null.
 *
 * @api
 */
class Tiger_Secret_Adapter_File implements Tiger_Secret_Adapter_Interface
{
    public function available(): bool
    {
        return true;
    }

    public function get(string $path): ?string
    {
        if ($path === '' || !is_file($path) || !is_readable($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        // Secret files conventionally carry a trailing newline that isn't part of the secret.
        $raw = rtrim($raw, "\r\n");
        return $raw === '' ? null : $raw;
    }
}
