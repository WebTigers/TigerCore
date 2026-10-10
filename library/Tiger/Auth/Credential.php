<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Auth_Credential — the registry + config selector for pluggable password-factor providers.
 *
 * The password factor is provider-agnostic, config-driven, and OPTIONAL: `tiger.auth.credential.provider`
 * names the adapter (unset/`db` → the built-in DB path, which is what every ordinary install runs, so
 * behaviour is unchanged unless a deployment opts in). A module registers an adapter class by name
 * (`Tiger_Auth_Credential::register('server', Server_Auth_Credential::class)`) — the same shape as
 * `Tiger_Location::register()` — and the deployment selects it in the `.ini`/config stack.
 *
 * `providerFor($user)` is the seam `Tiger_Service_Authentication` consults on each login/unlock: it
 * returns the selected adapter ONLY when one is configured AND it `appliesTo()` this user; otherwise
 * null, meaning "use the default DB credential path". So a non-owner user always falls back to the DB.
 *
 * @api
 */
class Tiger_Auth_Credential
{
    /** @var array<string,string> registered adapter classes, by provider name */
    protected static $_adapters = [];

    /** @var array<string,Tiger_Auth_Credential_Adapter_Abstract|null> instantiated adapters, by name */
    protected static $_instances = [];

    /**
     * Register a password-factor adapter under a provider name (idempotent; last registration wins).
     * Call from a module Bootstrap, before login can run.
     *
     * @param  string $name  the provider name selected via `tiger.auth.credential.provider`
     * @param  string $class an `@see Tiger_Auth_Credential_Adapter_Abstract` subclass name
     * @return void
     */
    public static function register($name, $class)
    {
        $name = (string) $name;
        if ($name === '' || $name === 'db') { return; }   // 'db' is the reserved built-in default
        self::$_adapters[$name] = (string) $class;
        unset(self::$_instances[$name]);
    }

    /**
     * The configured provider name (`tiger.auth.credential.provider`), or 'db' when unset/blank —
     * so an install that never opts in always resolves to the default DB path.
     *
     * @return string
     */
    public static function providerName()
    {
        if (!Zend_Registry::isRegistered('Zend_Config')) { return 'db'; }
        $cfg  = Zend_Registry::get('Zend_Config');
        $auth = ($cfg->get('tiger') && $cfg->tiger->get('auth')) ? $cfg->tiger->auth : null;
        $cred = ($auth && $auth->get('credential')) ? $auth->credential : null;
        $name = $cred ? trim((string) $cred->get('provider')) : '';
        return $name !== '' ? $name : 'db';
    }

    /**
     * The adapter that owns the password factor for this user, or null to use the default DB path.
     * Null whenever the provider is 'db'/unset, the named adapter isn't registered/resolvable, or the
     * adapter declines this user (`appliesTo()` false) — the provider chain's fall-through to DB.
     *
     * @param  object $user the resolved `Tiger_Model_User` row
     * @return Tiger_Auth_Credential_Adapter_Abstract|null
     */
    public static function providerFor($user)
    {
        $name = self::providerName();
        if ($name === 'db') { return null; }

        $adapter = self::_adapter($name);
        if (!$adapter) { return null; }

        try {
            return $adapter->appliesTo($user) ? $adapter : null;
        } catch (Throwable $e) {
            return null;   // a misbehaving adapter must never break login — fall back to DB
        }
    }

    /** Instantiate (once) the registered adapter for a name, or null if absent/invalid. */
    protected static function _adapter($name)
    {
        if (array_key_exists($name, self::$_instances)) { return self::$_instances[$name]; }

        $instance = null;
        $class    = self::$_adapters[$name] ?? '';
        if ($class !== '' && class_exists($class)) {
            $obj = new $class();
            if ($obj instanceof Tiger_Auth_Credential_Adapter_Abstract) { $instance = $obj; }
        }
        return self::$_instances[$name] = $instance;
    }

    /** Test seam: drop registered adapters + instances. */
    public static function reset()
    {
        self::$_adapters  = [];
        self::$_instances = [];
    }
}
