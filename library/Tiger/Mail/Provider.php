<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Mail_Provider — the catalog of mail providers the admin can choose from.
 *
 * One declarative table drives three things: the provider dropdown, which credential fields the
 * screen renders, and how `Tiger_Mail` builds a transport. Adding a provider is a row here plus
 * (for an API provider) one transport class — no changes to the form, the view, or the service.
 *
 * **Why both SMTP and API entries per service.** Nearly every service speaks SMTP, so SMTP is the
 * universal fallback and needs no driver — just the right host/port/encryption, which is exactly
 * what an operator otherwise has to go hunting for. The API entries exist for the cases SMTP can't
 * serve: hosts that firewall outbound 587/465 (common on shared/cPanel, a target platform), bulk
 * throughput, and provider features SMTP can't express. Listing them side by side — "Amazon SES
 * (SMTP)" and "Amazon SES (API)" — makes the choice explicit rather than hidden in a transport
 * setting.
 *
 * **Placeholders.** An SMTP host may contain `{field}`, interpolated from the provider's own
 * credential values (SES's region, Mailgun's EU/US host). See `smtpFor()`.
 *
 * @api
 * @see Tiger_Mail
 */
class Tiger_Mail_Provider
{
    /** Sent by an ordinary SMTP transport — no driver, just connection settings. */
    const KIND_SMTP = 'smtp';

    /** Sent over the provider's HTTPS API by a Tiger_Mail_Transport_* driver. */
    const KIND_API = 'api';

    /**
     * The provider table. Each entry:
     *   label            human name shown in the dropdown
     *   kind             KIND_SMTP | KIND_API
     *   smtp             (smtp) host/port/ssl/auth defaults; host may contain {field} placeholders
     *   transport        (api) the Tiger_Mail_Transport_* class
     *   requires_class   (api, optional) a class that must exist for this driver to work
     *   requires_hint    (api, optional) what to install when requires_class is missing
     *   fields           credential inputs: key => [label, placeholder, secret, required, help]
     *
     * @var array<string,array>
     */
    protected static $_providers = [

        'custom' => [
            'label'  => 'Custom SMTP server',
            'kind'   => self::KIND_SMTP,
            'smtp'   => ['host' => '', 'port' => 587, 'ssl' => 'tls', 'auth' => 'login'],
            'fields' => [],
        ],

        'sendmail' => [
            'label'  => 'PHP mail() / sendmail',
            'kind'   => self::KIND_SMTP,
            'smtp'   => ['host' => '', 'port' => '', 'ssl' => '', 'auth' => ''],
            'fields' => [],
        ],

        // --- Amazon SES -------------------------------------------------------------------
        'ses-smtp' => [
            'label'  => 'Amazon SES (SMTP)',
            'kind'   => self::KIND_SMTP,
            'smtp'   => ['host' => 'email-smtp.{region}.amazonaws.com', 'port' => 587, 'ssl' => 'tls', 'auth' => 'login'],
            'fields' => [
                'region' => ['label' => 'Region', 'placeholder' => 'us-east-1', 'required' => true],
            ],
            // NOTE: SES SMTP credentials are NOT your AWS access keys — they're generated in the
            // SES console. Username/password go in the standard SMTP fields.
            'help' => 'core.mail.provider.help.ses_smtp',
        ],
        'ses-api' => [
            'label'          => 'Amazon SES (API)',
            'kind'           => self::KIND_API,
            'transport'      => 'Tiger_Mail_Transport_Ses',
            'requires_class' => 'Aws\\SesV2\\SesV2Client',
            'requires_hint'  => 'core.mail.provider.requires.aws_sdk',
            'fields'         => [
                'region' => ['label' => 'Region', 'placeholder' => 'us-east-1', 'required' => true],
                'key'    => ['label' => 'Access key ID', 'placeholder' => 'AKIA…', 'required' => false,
                             'help' => 'core.mail.provider.help.ses_api_iam'],
                'secret' => ['label' => 'Secret access key', 'secret' => true, 'required' => false],
            ],
            'help' => 'core.mail.provider.help.ses_api',
        ],

        // --- SendGrid ---------------------------------------------------------------------
        'sendgrid-smtp' => [
            'label'  => 'SendGrid (SMTP)',
            'kind'   => self::KIND_SMTP,
            'smtp'   => ['host' => 'smtp.sendgrid.net', 'port' => 587, 'ssl' => 'tls', 'auth' => 'login'],
            'fields' => [],
            'help'   => 'core.mail.provider.help.sendgrid_smtp',
        ],
        'sendgrid-api' => [
            'label'     => 'SendGrid (API)',
            'kind'      => self::KIND_API,
            'transport' => 'Tiger_Mail_Transport_SendGrid',
            'fields'    => [
                'key' => ['label' => 'API key', 'placeholder' => 'SG.…', 'secret' => true, 'required' => true],
            ],
        ],

        // --- Mailgun ----------------------------------------------------------------------
        'mailgun-smtp' => [
            'label'  => 'Mailgun (SMTP)',
            'kind'   => self::KIND_SMTP,
            'smtp'   => ['host' => '{host}', 'port' => 587, 'ssl' => 'tls', 'auth' => 'login'],
            'fields' => [
                'host' => ['label' => 'SMTP host', 'placeholder' => 'smtp.mailgun.org', 'required' => true,
                           'help' => 'core.mail.provider.help.mailgun_region'],
            ],
        ],
        'mailgun-api' => [
            'label'     => 'Mailgun (API)',
            'kind'      => self::KIND_API,
            'transport' => 'Tiger_Mail_Transport_Mailgun',
            'fields'    => [
                'domain'   => ['label' => 'Sending domain', 'placeholder' => 'mg.example.com', 'required' => true],
                'key'      => ['label' => 'API key', 'placeholder' => 'key-…', 'secret' => true, 'required' => true],
                'endpoint' => ['label' => 'API base', 'placeholder' => 'https://api.mailgun.net', 'required' => false,
                               'help' => 'core.mail.provider.help.mailgun_region'],
            ],
        ],

        // --- Postmark ---------------------------------------------------------------------
        'postmark-smtp' => [
            'label'  => 'Postmark (SMTP)',
            'kind'   => self::KIND_SMTP,
            'smtp'   => ['host' => 'smtp.postmarkapp.com', 'port' => 587, 'ssl' => 'tls', 'auth' => 'login'],
            'fields' => [],
            'help'   => 'core.mail.provider.help.postmark_smtp',
        ],
        'postmark-api' => [
            'label'     => 'Postmark (API)',
            'kind'      => self::KIND_API,
            'transport' => 'Tiger_Mail_Transport_Postmark',
            'fields'    => [
                'key'    => ['label' => 'Server token', 'secret' => true, 'required' => true],
                'stream' => ['label' => 'Message stream', 'placeholder' => 'outbound', 'required' => false],
            ],
        ],

        // --- Resend -----------------------------------------------------------------------
        'resend-smtp' => [
            'label'  => 'Resend (SMTP)',
            'kind'   => self::KIND_SMTP,
            'smtp'   => ['host' => 'smtp.resend.com', 'port' => 587, 'ssl' => 'tls', 'auth' => 'login'],
            'fields' => [],
            'help'   => 'core.mail.provider.help.resend_smtp',
        ],
        'resend-api' => [
            'label'     => 'Resend (API)',
            'kind'      => self::KIND_API,
            'transport' => 'Tiger_Mail_Transport_Resend',
            'fields'    => [
                'key' => ['label' => 'API key', 'placeholder' => 're_…', 'secret' => true, 'required' => true],
            ],
        ],

        // --- Brevo (formerly Sendinblue) --------------------------------------------------
        'brevo-smtp' => [
            'label'  => 'Brevo (SMTP)',
            'kind'   => self::KIND_SMTP,
            'smtp'   => ['host' => 'smtp-relay.brevo.com', 'port' => 587, 'ssl' => 'tls', 'auth' => 'login'],
            'fields' => [],
        ],
        'brevo-api' => [
            'label'     => 'Brevo (API)',
            'kind'      => self::KIND_API,
            'transport' => 'Tiger_Mail_Transport_Brevo',
            'fields'    => [
                'key' => ['label' => 'API key', 'placeholder' => 'xkeysib-…', 'secret' => true, 'required' => true],
            ],
        ],

        // --- Mailjet ----------------------------------------------------------------------
        'mailjet-smtp' => [
            'label'  => 'Mailjet (SMTP)',
            'kind'   => self::KIND_SMTP,
            'smtp'   => ['host' => 'in-v3.mailjet.com', 'port' => 587, 'ssl' => 'tls', 'auth' => 'login'],
            'fields' => [],
        ],
        'mailjet-api' => [
            'label'     => 'Mailjet (API)',
            'kind'      => self::KIND_API,
            'transport' => 'Tiger_Mail_Transport_Mailjet',
            'fields'    => [
                'key'    => ['label' => 'API key', 'required' => true],
                'secret' => ['label' => 'Secret key', 'secret' => true, 'required' => true],
            ],
        ],

        // --- Google Workspace / Microsoft 365 ---------------------------------------------
        // SMTP only, deliberately. Both providers' send APIs (Gmail API, Microsoft Graph) require
        // an OAuth2 flow — a service account with domain-wide delegation, or app credentials with
        // Mail.Send — NOT a pasteable API key, so they can't be configured on a settings screen the
        // way the others can. That belongs behind the TigerConnect OAuth broker; until then SMTP is
        // the honest, working option rather than a field that can't be filled in.
        'google-smtp' => [
            'label'  => 'Google Workspace / Gmail (SMTP)',
            'kind'   => self::KIND_SMTP,
            'smtp'   => ['host' => 'smtp.gmail.com', 'port' => 587, 'ssl' => 'tls', 'auth' => 'login'],
            'fields' => [],
            'help'   => 'core.mail.provider.help.google_smtp',
        ],
        'microsoft-smtp' => [
            'label'  => 'Microsoft 365 / Outlook (SMTP)',
            'kind'   => self::KIND_SMTP,
            'smtp'   => ['host' => 'smtp.office365.com', 'port' => 587, 'ssl' => 'tls', 'auth' => 'login'],
            'fields' => [],
            'help'   => 'core.mail.provider.help.microsoft_smtp',
        ],
    ];

    /**
     * Every provider, keyed by slug.
     *
     * @return array<string,array> the provider table
     */
    public static function all()
    {
        return self::$_providers;
    }

    /**
     * One provider definition.
     *
     * @param  string $key the provider slug
     * @return array|null  the definition, or null when unknown
     */
    public static function get($key)
    {
        $key = (string) $key;
        return isset(self::$_providers[$key]) ? self::$_providers[$key] : null;
    }

    /**
     * Dropdown options (slug => label), in table order.
     *
     * @return array<string,string>
     */
    public static function options()
    {
        $out = [];
        foreach (self::$_providers as $key => $def) { $out[$key] = $def['label']; }
        return $out;
    }

    /**
     * Whether a provider's driver can actually run here — an API provider may need an optional
     * module (SES needs the AWS SDK). Capability-detected, never assumed.
     *
     * @param  string $key the provider slug
     * @return bool        true when the provider is usable on this install
     */
    public static function isAvailable($key)
    {
        $def = self::get($key);
        if (!$def) { return false; }
        if (($def['kind'] ?? '') !== self::KIND_API) { return true; }
        $needs = $def['requires_class'] ?? '';
        return $needs === '' || class_exists($needs);
    }

    /**
     * Resolve a provider's SMTP settings, interpolating `{field}` placeholders from its own
     * credential values (SES's region, Mailgun's host).
     *
     * @param  string $key    the provider slug
     * @param  array  $fields that provider's stored credential values
     * @return array{host:string,port:string,ssl:string,auth:string}
     */
    public static function smtpFor($key, array $fields = [])
    {
        $def  = self::get($key);
        $smtp = ($def && isset($def['smtp'])) ? $def['smtp'] : ['host' => '', 'port' => 587, 'ssl' => 'tls', 'auth' => 'login'];

        $host = (string) $smtp['host'];
        if (strpos($host, '{') !== false) {
            foreach ($fields as $name => $value) {
                $host = str_replace('{' . $name . '}', (string) $value, $host);
            }
            // An un-substituted placeholder means the operator hasn't filled that field in yet.
            if (strpos($host, '{') !== false) { $host = ''; }
        }

        return [
            'host' => $host,
            'port' => (string) $smtp['port'],
            'ssl'  => (string) $smtp['ssl'],
            'auth' => (string) $smtp['auth'],
        ];
    }

    /**
     * The credential field names a provider declares (for the form + the config writer).
     *
     * @param  string $key the provider slug
     * @return array<string,array> field name => definition
     */
    public static function fields($key)
    {
        $def = self::get($key);
        return ($def && isset($def['fields'])) ? $def['fields'] : [];
    }

    /**
     * Whether a provider's field holds a secret (encrypted at rest, never echoed back).
     *
     * @param  string $provider the provider slug
     * @param  string $field    the field name
     * @return bool
     */
    public static function isSecret($provider, $field)
    {
        $fields = self::fields($provider);
        return !empty($fields[$field]['secret']);
    }
}
