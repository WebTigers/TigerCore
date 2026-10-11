<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Secret_Adapter_Local — the default backend: an inline ciphertext decrypted by Tiger_Crypto.
 *
 * This is what every install has had all along — a `*_enc` value sitting in local.ini / the DB config
 * tier, decrypted with the app's `tiger.crypto.key`. As a Tiger_Secret adapter it is reached via the
 * `enc:` (or `local:`) scheme: `enc:<base64-blob>` → Tiger_Crypto::decrypt(<blob>). No external store,
 * no cloud SDK, no network — so a shared-hosting install with `tiger.secret.driver = local` (the
 * default) behaves exactly as before and the whole secrets layer is invisible.
 *
 * Read-only by design: there is no "store" to put a secret into — the ciphertext IS the stored form.
 * (Tiger_Crypto::encrypt() mints one; that's an install/rotation concern, not this adapter's job.)
 *
 * @api
 */
class Tiger_Secret_Adapter_Local implements Tiger_Secret_Adapter_Interface
{
    public function available(): bool
    {
        return class_exists('Tiger_Crypto') && Tiger_Crypto::isConfigured();
    }

    public function get(string $path): ?string
    {
        if ($path === '' || !class_exists('Tiger_Crypto')) {
            return null;
        }
        try {
            return Tiger_Crypto::decrypt($path);
        } catch (Throwable $e) {
            return null;
        }
    }
}
