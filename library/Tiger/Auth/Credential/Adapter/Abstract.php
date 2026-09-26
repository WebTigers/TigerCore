<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Auth_Credential_Adapter_Abstract — a pluggable verifier for the PASSWORD factor.
 *
 * By default Tiger verifies a login's password against the `user_credential` DB row
 * (`Tiger_Model_UserCredential::verifyPassword`, pepper-aware). A deployment can instead point the
 * password factor at a DIFFERENT authority by registering an adapter and selecting it in the config
 * cascade (`tiger.auth.credential.provider`) — the same config-driven, provider-agnostic pattern as
 * `Tiger_Location`, `Tiger_Mail`'s transport, and `Tiger_Log`'s sinks. The motivating case: on
 * TigerServer the account OWNER's web login should verify against the OS/system credential, so there
 * is a single password (see the TigerServer `server` adapter). Only the password factor is affected —
 * TOTP/other factors, the brute-force audit, session issuance, and the lock screen all stay in
 * `Tiger_Service_Authentication`.
 *
 * An adapter is a PROVIDER CHAIN member, not a hard replace: `appliesTo()` declares which identities
 * it owns (e.g. the account owner), and any identity it declines falls back to the default DB path.
 * So a non-owner user the owner invited still authenticates against the DB credential unchanged.
 *
 * @api
 */
abstract class Tiger_Auth_Credential_Adapter_Abstract
{
    /**
     * Does this adapter own the password factor for THIS user? Return false to let the identity fall
     * back to the default DB credential path (the provider chain).
     *
     * @param  object $user the resolved `Tiger_Model_User` row
     * @return bool
     */
    abstract public function appliesTo($user): bool;

    /**
     * Verify the plaintext password for a user this adapter owns. Called only when `appliesTo()` is
     * true. Must be constant-time-ish and fail closed (any error → false), never throw into login.
     *
     * @param  object $user     the resolved user row
     * @param  string $password the plaintext password
     * @return bool             true when the password is correct
     */
    abstract public function verify($user, string $password): bool;

    /**
     * Is this user currently locked out by the adapter's own brute-force policy? Default: no — the
     * adapter's authority (e.g. the OS, plus fail2ban) owns rate-limiting, and Tiger still records
     * every attempt in the login audit. Override to add an app-tier lockout.
     *
     * @param  object $user the resolved user row
     * @return bool
     */
    public function isLockedOut($user): bool
    {
        return false;
    }

    /**
     * Note a failed verification (for an app-tier lockout/audit an adapter chooses to keep). No-op by
     * default; the login audit log records the failure regardless.
     *
     * @param  object $user the resolved user row
     * @return void
     */
    public function recordFailure($user): void
    {
    }

    /**
     * Note a successful verification. No-op by default.
     *
     * @param  object $user the resolved user row
     * @return void
     */
    public function recordSuccess($user): void
    {
    }
}
