<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Secret_Adapter_Env — resolve a secret from an environment variable (the 12-factor backend).
 *
 * Reached via the `env:` scheme: `env:OPENSRS_API_KEY` → `getenv('OPENSRS_API_KEY')`. Works on any
 * host with no SDK and no cloud — a container platform, a systemd unit's `Environment=`, a PaaS config
 * var. Always "available" (there is always an environment); an unset or empty var resolves to null so
 * the caller treats it exactly as a missing credential. Reads `getenv()` first, then `$_ENV` / `$_SERVER`
 * (PHP-FPM populates the latter when `variables_order` includes them).
 *
 * @api
 */
class Tiger_Secret_Adapter_Env implements Tiger_Secret_Adapter_Interface
{
    public function available(): bool
    {
        return true;
    }

    public function get(string $path): ?string
    {
        if ($path === '') {
            return null;
        }
        $v = getenv($path);
        if ($v !== false && $v !== '') {
            return $v;
        }
        foreach ([$_ENV, $_SERVER] as $bag) {
            if (isset($bag[$path]) && $bag[$path] !== '') {
                return (string) $bag[$path];
            }
        }
        return null;
    }
}
