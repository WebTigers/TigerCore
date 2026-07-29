<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Register_Service_Status — a read-only view of the site's registration progress. **It gates nothing.**
 * Registration is a purely optional dashboard widget; nothing is enabled or disabled by this state. It
 * exists so the widget knows what step to show ({@see isVerified}) and so anything that genuinely needs the
 * site's verified identity (e.g. the marketplace Federation) can read {@see tsid}.
 *
 * State lives in the config tier (live, no settings table). Fail-safe: unreadable state = "nothing done".
 *
 * @api
 */
class Register_Service_Status
{
    /** Registration has started (a TSID was issued). */
    public static function hasStarted(): bool
    {
        return self::_cfg('register.tsid') !== '';
    }

    /** The domain-control proof passed. */
    public static function isDomainVerified(): bool
    {
        return self::_cfg('register.domain_verified') === '1';
    }

    /** The admin email was confirmed. */
    public static function isEmailVerified(): bool
    {
        return self::_cfg('register.email_verified') === '1';
    }

    /** Fully verified — both proofs done. This is when the widget shows the "registered" state. */
    public static function isVerified(): bool
    {
        return self::isDomainVerified() && self::isEmailVerified();
    }

    /** The verified site id (TSID) once the domain is verified, else '' — what the Federation reads. */
    public static function tsid(): string
    {
        return self::isDomainVerified() ? self::_cfg('register.tsid') : '';
    }

    /** The full snapshot for the widget / the Registration screen / the `status` API. */
    public static function state(): array
    {
        return [
            'started'         => self::hasStarted(),
            'domain_verified' => self::isDomainVerified(),
            'email_verified'  => self::isEmailVerified(),
            'verified'        => self::isVerified(),
            'domain'          => self::_cfg('register.domain'),
            'email'           => self::_cfg('register.email'),
            'tsid'            => self::_cfg('register.tsid'),
        ];
    }

    /** Read a config value from the live DB tier as a string; unreadable → '' (fail-safe). */
    private static function _cfg(string $key): string
    {
        try {
            return (string) (new Tiger_Model_Config())->get(Tiger_Model_Config::SCOPE_GLOBAL, '', $key);
        } catch (Throwable $e) {
            return '';
        }
    }
}
