<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Secret_Bootstrap — eager-wire the config-driven secret backends at boot.
 *
 * The Tiger_Secret facade is self-sufficient: its built-in backends (local/env/file) self-register on
 * first use, and the config-driven ones (ssm/secretsmanager/chain) lazily wire from `tiger.secret.*`
 * the moment a reference needs them — so correctness NEVER depends on this Bootstrap running (it works
 * in CLI, in tests, and on an install where this module is inactive). This Bootstrap is a convenience:
 * it instantiates the configured external backends up front so `Tiger_Secret::schemes()` lists them and
 * a misconfiguration surfaces at boot rather than at first use. It registers class names only and never
 * throws — a missing SDK simply means that adapter isn't wired, exactly as the facade would decide.
 *
 * @see Tiger_Secret
 */
class Secret_Bootstrap extends Zend_Application_Module_Bootstrap
{
    /** Touch each configured external backend once so it's registered + validated at boot. */
    protected function _initSecretAdapters()
    {
        if (!class_exists('Tiger_Secret')) {
            return;
        }
        if (!Zend_Registry::isRegistered('Zend_Config')) {
            return;
        }
        $cfg = Zend_Registry::get('Zend_Config');
        $sec = ($cfg instanceof Zend_Config && $cfg->get('tiger') && $cfg->tiger->get('secret') instanceof Zend_Config)
            ? $cfg->tiger->secret : null;
        if (!$sec) {
            return;   // nothing to eager-wire; built-ins remain lazy
        }

        // Instantiating via adapter() runs the facade's own lazy wiring + caches the result.
        foreach (['ssm', 'secretsmanager', 'chain'] as $scheme) {
            if ($sec->get($scheme) !== null) {
                try {
                    Tiger_Secret::adapter($scheme);
                } catch (Throwable $e) {
                    // Fail-soft: a bad adapter config must never take the site down at boot.
                }
            }
        }

        // If a default driver is named and it's one of the external schemes, warm it too.
        $driver = strtolower(trim((string) $sec->get('driver')));
        if ($driver !== '' && !in_array($driver, ['local', 'enc', 'env', 'file'], true)) {
            try {
                Tiger_Secret::adapter($driver);
            } catch (Throwable $e) {
                // ignore
            }
        }
    }
}
