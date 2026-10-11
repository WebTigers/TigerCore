<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Secret — provider-neutral resolution of secrets and sensitive config values.
 *
 * One facade in front of a pluggable set of secret backends, exactly like Tiger_Location (addresses),
 * Tiger_Mail (transports) and the auth credential provider: the BACKEND is a config choice, never baked
 * into the caller. AWS SSM is one adapter; so are env vars, mounted files, HashiCorp Vault, and the
 * inline Tiger_Crypto value every install already uses. A caller asks for a value; it never learns who
 * answered.
 *
 * A secret reference is `scheme:path` — `ssm:/tiger/prod/node/crypto_key`, `env:OPENSRS_API_KEY`,
 * `file:/run/secrets/x`, `enc:<base64-ciphertext>`. The facade dispatches by scheme to the registered
 * adapter; an UNKNOWN scheme (or a value with no scheme at all) is treated as plaintext and returned
 * unchanged. That single rule is what makes adoption a one-liner at every existing `*_enc` call site:
 * swap `Tiger_Crypto::decrypt($enc)` / plaintext-fallback logic for `Tiger_Secret::resolve($value)`, and
 * the same config now accepts a plaintext value, an `enc:` blob, or an `ssm:`/`vault:`/`env:` reference.
 *
 * WHY THIS IS NOT AWS-SPECIFIC — the default driver is `local`, which is today's behavior (an inline
 * Tiger_Crypto value). A shared-hosting install with `tiger.secret.driver` unset behaves exactly as
 * before; the whole layer is invisible until an operator points `driver` at a store. On the WebTigers
 * fleet it is `ssm` and secrets come from Parameter Store via the instance IAM role — nothing baked into
 * a golden image, nothing hand-placed.
 *
 * WHY IT BREAKS THE BOOTSTRAP CHICKEN-AND-EGG — `tiger.crypto.key` cannot come from the app's own
 * encrypted config (it is the key that decrypts it), but it CAN come from SSM, because SSM authenticates
 * with the EC2 instance role, not crypto.key. The root of trust becomes the instance role + KMS, not a
 * pre-shared secret.
 *
 * The built-in dependency-free adapters (local/enc, env, file) self-register on first use, and the
 * config-driven ones (ssm, secretsmanager, chain) lazily wire from `tiger.secret.<scheme>.*` — so the
 * facade is correct in any context (web, CLI, a test) with no module Bootstrap required. The module
 * `modules/secret` eager-wires them and is the documented home; it is never load-bearing for correctness.
 *
 * Resolved values are memoized per process (a store lookup is not repeated within a request). Secrets
 * are never logged. Register a custom backend from a module Bootstrap with register().
 *
 * @api
 */
class Tiger_Secret
{
    /** @var array<string,Tiger_Secret_Adapter_Interface> scheme => adapter */
    protected static $_adapters = [];

    /** @var array<string,?string> ref => resolved value (per-process memo of successful resolutions) */
    protected static $_cache = [];

    /** Dependency-free built-ins, lazily instantiated on first use. */
    protected static $_builtins = [
        'enc'   => 'Tiger_Secret_Adapter_Local',
        'local' => 'Tiger_Secret_Adapter_Local',
        'env'   => 'Tiger_Secret_Adapter_Env',
        'file'  => 'Tiger_Secret_Adapter_File',
    ];

    /**
     * Register (or replace) a backend for a scheme. Call from a module Bootstrap.
     *
     * @param  string                         $scheme  the reference scheme (e.g. 'ssm', 'vault')
     * @param  Tiger_Secret_Adapter_Interface $adapter the backend
     * @return void
     */
    public static function register(string $scheme, Tiger_Secret_Adapter_Interface $adapter): void
    {
        $scheme = strtolower(trim($scheme));
        if ($scheme !== '') {
            self::$_adapters[$scheme] = $adapter;
            self::$_cache = [];
        }
    }

    /**
     * The adapter for a scheme, or null. Registered adapters win; then dependency-free built-ins
     * (enc/local/env/file); then the config-driven lazies (ssm/secretsmanager/chain) wired from
     * `tiger.secret.<scheme>.*`.
     *
     * @param  string $scheme the reference scheme
     * @return Tiger_Secret_Adapter_Interface|null
     */
    public static function adapter(string $scheme): ?Tiger_Secret_Adapter_Interface
    {
        $scheme = strtolower(trim($scheme));
        if ($scheme === '') {
            return null;
        }
        if (isset(self::$_adapters[$scheme])) {
            return self::$_adapters[$scheme];
        }
        if (isset(self::$_builtins[$scheme])) {
            $class = self::$_builtins[$scheme];
            return self::$_adapters[$scheme] = new $class();
        }
        $lazy = self::_lazyFromConfig($scheme);
        if ($lazy !== null) {
            return self::$_adapters[$scheme] = $lazy;
        }
        return null;
    }

    /**
     * Whether a scheme has a usable backend registered or lazily buildable.
     *
     * @param  string $scheme the reference scheme
     * @return bool
     */
    public static function has(string $scheme): bool
    {
        return self::adapter($scheme) !== null;
    }

    /**
     * The known schemes (built-ins plus anything registered or lazily wired so far).
     *
     * @return string[]
     */
    public static function schemes(): array
    {
        return array_values(array_unique(array_merge(array_keys(self::$_builtins), array_keys(self::$_adapters))));
    }

    /**
     * Resolve a CONFIG VALUE. A `scheme:path` reference to a known scheme is fetched from its backend;
     * anything else (including a value with no recognized scheme) is returned unchanged as plaintext.
     * Returns null only when a recognized reference could not be resolved — the caller treats that
     * exactly as a missing credential. This is the one-line swap at an `*_enc` call site.
     *
     * @param  mixed $value the config value (plaintext, `enc:`/`ssm:`/`env:`/… reference, or '')
     * @return string|null the resolved secret, the plaintext, '' for empty, or null if unresolvable
     */
    public static function resolve($value): ?string
    {
        $value = (string) $value;
        if ($value === '') {
            return '';
        }
        $ref = self::_parse($value);
        if ($ref === null) {
            return $value;   // not a recognized reference -> plaintext passthrough
        }
        return self::_fetch($ref[0], $ref[1], $value);
    }

    /**
     * Resolve an explicit reference. A `scheme:path` dispatches to that backend; a BARE name (no scheme)
     * is resolved against the configured default driver (`tiger.secret.driver`) — meaningful for an
     * external store (ssm/env/vault) with its own prefix, not for `local`.
     *
     * @param  string $ref a `scheme:path` reference, or a bare name for the default driver
     * @return string|null the secret value, or null when unresolvable
     */
    public static function get(string $ref): ?string
    {
        $ref = (string) $ref;
        if ($ref === '') {
            return null;
        }
        $parsed = self::_parse($ref);
        if ($parsed === null) {
            $scheme = self::_defaultScheme();   // bare name -> default driver
            return self::_fetch($scheme, $ref, 'bare:' . $scheme . ':' . $ref);
        }
        return self::_fetch($parsed[0], $parsed[1], $ref);
    }

    /**
     * Whether a backend for a scheme can accept writes (implements the writer interface and is available).
     *
     * @param  string $scheme the reference scheme
     * @return bool
     */
    public static function canWrite(string $scheme): bool
    {
        $a = self::adapter($scheme);
        return $a instanceof Tiger_Secret_Writer_Interface && $a->available();
    }

    /**
     * Store a secret at a `scheme:path` reference, when its backend supports writing (ssm, secretsmanager).
     * An ops/provisioning helper — never the request path.
     *
     * @param  string $ref   a `scheme:path` reference
     * @param  string $value the secret value to store
     * @return bool   true on success; false when the scheme is unknown or not writable
     */
    public static function put(string $ref, string $value): bool
    {
        $parsed = self::_parse($ref);
        if ($parsed === null) {
            return false;
        }
        [$scheme, $path] = $parsed;
        $a = self::adapter($scheme);
        if (!($a instanceof Tiger_Secret_Writer_Interface) || !$a->available()) {
            return false;
        }
        self::$_cache = [];
        return $a->put($path, $value);
    }

    /**
     * Clear the per-process memo and all registered/lazy adapters.
     *
     * @return void
     * @internal for tests
     */
    public static function _reset(): void
    {
        self::$_adapters = [];
        self::$_cache = [];
    }

    // ---- internals ---------------------------------------------------------

    /** Parse `scheme:path` iff the scheme is known; else null (treat the whole value as plaintext). */
    protected static function _parse(string $value): ?array
    {
        if (!preg_match('/^([a-z][a-z0-9+_-]*):(.*)$/s', $value, $m)) {
            return null;
        }
        $scheme = $m[1];
        if (!self::has($scheme)) {
            return null;   // unknown scheme (e.g. an https:// URL, a user:pass string) -> plaintext
        }
        return [$scheme, $m[2]];
    }

    /** Fetch via the scheme's adapter, memoized by the original ref string; fail-soft to null. */
    protected static function _fetch(string $scheme, string $path, string $cacheKey): ?string
    {
        if (array_key_exists($cacheKey, self::$_cache)) {
            return self::$_cache[$cacheKey];
        }
        $adapter = self::adapter($scheme);
        $out = null;
        if ($adapter && $adapter->available()) {
            try {
                $out = $adapter->get($path);
            } catch (Throwable $e) {
                $out = null;
            }
        }
        if ($out !== null) {
            self::$_cache[$cacheKey] = $out;   // memoize only successful resolutions
        }
        return $out;
    }

    /** Build a config-driven adapter for a scheme from `tiger.secret.<scheme>.*`, or null. */
    protected static function _lazyFromConfig(string $scheme): ?Tiger_Secret_Adapter_Interface
    {
        $sec = self::_cfgSecret();
        switch ($scheme) {
            case 'ssm':
                if (!class_exists('Tiger_Secret_Adapter_Ssm')) { return null; }
                $o = ($sec && $sec->get('ssm') instanceof Zend_Config) ? $sec->ssm->toArray() : [];
                return new Tiger_Secret_Adapter_Ssm($o);
            case 'secretsmanager':
                if (!class_exists('Tiger_Secret_Adapter_SecretsManager')) { return null; }
                $o = ($sec && $sec->get('secretsmanager') instanceof Zend_Config) ? $sec->secretsmanager->toArray() : [];
                return new Tiger_Secret_Adapter_SecretsManager($o);
            case 'chain':
                $list = $sec ? (string) $sec->get('chain') : '';
                if ($list === '') { return null; }
                $schemes = array_filter(array_map('trim', explode(',', $list)));
                return $schemes ? new Tiger_Secret_Adapter_Chain($schemes) : null;
        }
        return null;
    }

    /** The configured default driver scheme (`tiger.secret.driver`), default 'local'. */
    protected static function _defaultScheme(): string
    {
        $sec = self::_cfgSecret();
        if ($sec) {
            $d = strtolower(trim((string) $sec->get('driver')));
            if ($d !== '') {
                return $d;
            }
        }
        return 'local';
    }

    /** The `tiger.secret` Zend_Config node, or null. */
    protected static function _cfgSecret(): ?Zend_Config
    {
        if (!Zend_Registry::isRegistered('Zend_Config')) {
            return null;
        }
        $cfg = Zend_Registry::get('Zend_Config');
        if ($cfg instanceof Zend_Config && $cfg->get('tiger') && $cfg->tiger->get('secret') instanceof Zend_Config) {
            return $cfg->tiger->secret;
        }
        return null;
    }
}
