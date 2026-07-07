<?php
/**
 * Tiger_Security — the application PEPPER (a keyed secret mixed into hashes).
 *
 * SALTS are already handled per-record: bcrypt (password_hash) mints a random salt per
 * password. A PEPPER is different — one secret, shared by the whole install, kept OUT of
 * the database (local.ini / a secrets manager), and HMAC'd into a value before it's
 * hashed. The payoff: a stolen `user`/`user_credential` table is useless on its own —
 * without the pepper you can't even start cracking, because every stored hash was keyed
 * by a secret the DB never held. It also lifts short, low-entropy codes (a 6-digit OTP,
 * a 10-char recovery code) out of offline brute-force range.
 *
 * The pepper is RANDOM AT INSTALL and never committed — Tiger_Install::provisionSecrets()
 * writes it to local.ini (the same place the cPanel/installer form writes the DB creds).
 * It must not change afterwards: losing/rotating it makes every peppered hash unverifiable
 * (passwords would all need a reset). Passwords migrate gracefully (verify falls back to a
 * pre-pepper hash and re-hashes on login); transient codes (OTP/recovery) are simply
 * re-issued.
 *
 * When NO pepper is configured every method degrades to exactly the legacy behavior
 * (`password_hash($p)` / `hash('sha256', $code)`), so existing installs are unaffected
 * until a pepper is provisioned.
 *
 * @api
 */
class Tiger_Security
{
    /**
     * Prepare a password for password_hash(): HMAC-then-base64 when a pepper is set,
     * else the raw password (identical to the no-pepper path).
     *
     * base64 of the 32-byte HMAC is 44 chars — safely under bcrypt's 72-byte limit and
     * free of the NUL-byte truncation trap, which also means arbitrarily long passwords
     * are no longer silently truncated by bcrypt.
     */
    public static function prehashPassword($plain)
    {
        $pepper = self::pepper();
        if ($pepper === '') {
            return (string) $plain;
        }
        return base64_encode(hash_hmac('sha256', (string) $plain, $pepper, true));
    }

    /**
     * Keyed hash for short secret CODES (OTP / reset / recovery). Peppered with a
     * per-context subkey when set, else plain sha256 (legacy). `$context` domain-separates
     * so the same code value hashes differently as a recovery code vs a login challenge.
     *
     * @return string 64-char hex, same shape/length as the legacy sha256.
     */
    public static function hashCode($code, $context = '')
    {
        $pepper = self::pepper();
        if ($pepper === '') {
            return hash('sha256', (string) $code);
        }
        $subkey = hash_hmac('sha256', 'code:' . $context, $pepper, true);   // HKDF-style split
        return hash_hmac('sha256', (string) $code, $subkey);
    }

    /** Is a pepper configured? (Drives the graceful-migration fallbacks in the callers.) */
    public static function hasPepper()
    {
        return self::pepper() !== '';
    }

    /** Mint a fresh pepper for local.ini / a secrets manager (used by the installer). */
    public static function generatePepper()
    {
        return base64_encode(random_bytes(32));
    }

    /** The configured pepper string ('' when unset). Any-length key material — used as-is. */
    protected static function pepper()
    {
        $cfg = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        if ($cfg && $cfg->get('tiger') && $cfg->tiger->get('security')) {
            return (string) $cfg->tiger->security->get('pepper');
        }
        return '';
    }
}
