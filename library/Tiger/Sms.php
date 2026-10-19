<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Sms — provider-agnostic SMS send, the SMS sibling of Tiger_Mail.
 *
 * One facade, pluggable adapters. The configured provider is chosen by config and every adapter
 * returns the same shape, so callers never care who delivered it:
 *
 *   tiger.sms.provider               = "twilio" | "sns"        ; which adapter sends
 *   tiger.sms.from                   = "+15551234567"           ; default sender (an origination number)
 *   tiger.sms.adapters.twilio.*      = account_sid / auth_token_enc / from
 *   tiger.sms.adapters.sns.*         = key / secret_key_enc / region / from   (or the instance role on AWS)
 *
 * Graceful by design (the Tiger_Location contract): an unset provider, a missing/incapable adapter, or a
 * provider error yields a clean `['ok'=>false,'error'=>...]` — never a fatal and never an exception into
 * the caller. Numbers are normalized to E.164 before sending. Register more adapters with
 * Tiger_Sms::register(). The built-in Twilio + SNS adapters self-register on first use.
 *
 * It powers the platform's SMS needs the same way Tiger_Mail powers email: auth one-time codes / 2FA,
 * operational alerts (e.g. the Fleet Observer's sev-1 pages), and consent-gated campaigns.
 *
 * @api
 */
class Tiger_Sms
{
    /** @var array<string,string> provider name => adapter class (lowercased keys) */
    protected static $_registry = [];

    /** @var bool whether the built-in adapters have been registered this request */
    protected static $_seeded = false;

    /** @var callable[] inbound-message listeners (modules subscribe via onInbound) */
    protected static $_inbound = [];

    /**
     * Register (or replace) a provider name → adapter class. Case-insensitive.
     *
     * @param  string $name  the provider name (e.g. "twilio")
     * @param  string $class the adapter class (extends Tiger_Sms_Adapter_Abstract)
     * @return void
     */
    public static function register(string $name, string $class): void
    {
        self::$_registry[strtolower(trim($name))] = $class;
    }

    /** Register the first-party adapters once (so the facade works with no module activation). */
    protected static function _seed(): void
    {
        if (self::$_seeded) { return; }
        self::$_seeded = true;
        if (!isset(self::$_registry['twilio'])) { self::$_registry['twilio'] = 'Tiger_Sms_Adapter_Twilio'; }
        if (!isset(self::$_registry['sns']))    { self::$_registry['sns']    = 'Tiger_Sms_Adapter_Sns'; }
    }

    /**
     * Subscribe to inbound SMS — the loose-coupling seam. Call from a module Bootstrap. The listener is
     * handed the normalized message `{provider, message_id, from, to, body, media[], received_at, raw}`
     * whenever a VERIFIED inbound webhook arrives. Receiving is deliberately application-specific:
     * TigerSMS ingests + verifies + normalizes, and YOUR module decides what the message means (reply,
     * STOP/opt-out, a keyword flow, an order lookup…). TigerSMS never knows or cares. Many modules may
     * subscribe; a throwing listener is isolated so it can't break the webhook or the others.
     *
     * @param  callable $listener fn(array $message): void
     * @return void
     */
    public static function onInbound(callable $listener): void
    {
        self::$_inbound[] = $listener;
    }

    /** The registered inbound listeners. @return callable[] */
    public static function inboundListeners(): array
    {
        return self::$_inbound;
    }

    /**
     * Fan a normalized inbound message out to every subscriber (fail-soft — one bad listener never
     * breaks the webhook or the others). The inbound webhook controller calls this after it verifies +
     * parses a message; app modules receive it through {@see onInbound}.
     *
     * @param  array $message the normalized inbound message
     * @return int   how many listeners were notified
     */
    public static function dispatchInbound(array $message): int
    {
        $n = 0;
        foreach (self::$_inbound as $listener) {
            try { $listener($message); $n++; }
            catch (Throwable $e) {
                if (class_exists('Tiger_Log')) { try { Tiger_Log::error('sms.inbound.listener_failed', ['error' => $e->getMessage()]); } catch (Throwable $e2) { /* never fatal */ } }
            }
        }
        return $n;
    }

    /** The configured provider name (`tiger.sms.provider`), lowercased; '' when unset. */
    public static function provider(): string
    {
        return strtolower(trim((string) self::_config('sms.provider', '')));
    }

    /** Whether SMS is usable here: a provider is selected AND its adapter reports itself configured. */
    public static function isConfigured(): bool
    {
        $a = self::_adapter();
        return $a !== null && $a->isConfigured();
    }

    /** The registered provider names (for a settings picker). @return string[] */
    public static function providers(): array
    {
        self::_seed();
        return array_keys(self::$_registry);
    }

    /**
     * Send one SMS via the configured provider. The ONE call the platform uses.
     *
     * @param  string $to      the destination number (normalized to E.164 here)
     * @param  string $message the message body
     * @param  array  $opts    {from?:string} — override the default sender for this message
     * @return array  {ok:bool, id?:string, provider?:string, to?:string, error?:string}
     */
    public static function send(string $to, string $message, array $opts = []): array
    {
        $adapter = self::_adapter();
        if ($adapter === null) {
            return ['ok' => false, 'error' => 'no SMS provider configured (set tiger.sms.provider)'];
        }
        $e164 = self::e164($to);
        if ($e164 === '') {
            return ['ok' => false, 'error' => 'invalid destination number: ' . $to];
        }
        if (trim($message) === '') {
            return ['ok' => false, 'error' => 'empty message'];
        }
        $from = (string) ($opts['from'] ?? self::_config('sms.from', '') ?: $adapter->defaultFrom());
        try {
            $res = $adapter->send($e164, $message, $from, $opts);
            $res['provider'] = self::provider();
            $res['to']       = $e164;
            return $res;
        } catch (Throwable $e) {
            if (class_exists('Tiger_Log')) { try { Tiger_Log::error('sms.send.failed', ['provider' => self::provider(), 'error' => $e->getMessage()]); } catch (Throwable $e2) { /* never fatal */ } }
            return ['ok' => false, 'provider' => self::provider(), 'error' => $e->getMessage()];
        }
    }

    /**
     * Normalize a phone number to E.164 (`+<countrycode><number>`). Keeps a leading `+`; strips spaces,
     * dashes and parentheses; a US-style 10-digit number gets a `+1`. Returns '' when it can't be made
     * into a plausible E.164 number (so the caller fails cleanly rather than sending garbage).
     *
     * @param  string $raw the number as entered
     * @return string the E.164 number, or '' when invalid
     */
    public static function e164(string $raw): string
    {
        $raw = trim($raw);
        $plus = strncmp($raw, '+', 1) === 0;
        $digits = preg_replace('/\D+/', '', $raw);
        if ($digits === '') { return ''; }
        if (!$plus && strlen($digits) === 10) { $digits = '1' . $digits; }   // bare US 10-digit → +1
        // E.164: 8–15 digits, leading digit non-zero.
        if (strlen($digits) < 8 || strlen($digits) > 15 || $digits[0] === '0') { return ''; }
        return '+' . $digits;
    }

    /**
     * A built adapter for a named provider (configured with its own `tiger.sms.adapters.<name>.*`). The
     * inbound webhook uses this to resolve the adapter the request's URL names. Null when unregistered.
     *
     * @param  string $name the provider name
     * @return Tiger_Sms_Adapter_Abstract|null
     */
    public static function adapterFor(string $name): ?Tiger_Sms_Adapter_Abstract
    {
        self::_seed();
        $name = strtolower(trim($name));
        if ($name === '' || !isset(self::$_registry[$name])) { return null; }
        $class = self::$_registry[$name];
        if (!class_exists($class)) { return null; }
        $a = new $class(self::_adapterConfig($name));
        return $a instanceof Tiger_Sms_Adapter_Abstract ? $a : null;
    }

    /** The configured adapter instance, or null when none is selected / the class is missing. */
    protected static function _adapter(): ?Tiger_Sms_Adapter_Abstract
    {
        return self::adapterFor(self::provider());
    }

    /** The `tiger.sms.adapters.<name>.*` config map for one adapter. */
    protected static function _adapterConfig(string $name): array
    {
        if (!Zend_Registry::isRegistered('Zend_Config')) { return []; }
        $cfg = Zend_Registry::get('Zend_Config');
        $sms = ($cfg instanceof Zend_Config && $cfg->get('tiger')) ? $cfg->get('tiger')->get('sms') : null;
        $ad  = ($sms instanceof Zend_Config) ? $sms->get('adapters') : null;
        $one = ($ad instanceof Zend_Config) ? $ad->get($name) : null;
        return ($one instanceof Zend_Config) ? $one->toArray() : [];
    }

    /** A `tiger.<dotKey>` config value with a default. */
    protected static function _config(string $dotKey, $default = null)
    {
        if (!Zend_Registry::isRegistered('Zend_Config')) { return $default; }
        $node = Zend_Registry::get('Zend_Config');
        if (!($node instanceof Zend_Config)) { return $default; }
        $node = $node->get('tiger');
        foreach (explode('.', $dotKey) as $part) {
            if (!($node instanceof Zend_Config)) { return $default; }
            $node = $node->get($part);
        }
        return ($node === null || $node === '') ? $default : $node;
    }
}
