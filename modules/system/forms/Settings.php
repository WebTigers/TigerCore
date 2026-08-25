<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * System_Form_Settings — core platform settings: session lifetime + auto-logout.
 *
 * Values are stored in the `config` table (scope=global) by System_Service_Settings —
 * the live-override tier, no deploy. Session/security lives here (not CMS): it's a
 * platform concern. Room to grow (mail, logging, locale) as tabs on the System page.
 *
 * @api
 */
class System_Form_Settings extends Tiger_Form
{
    protected function elements(): array
    {
        $control = ['class' => 'form-control'];

        return [
            // The three idle-TTL tiers (server-side max, by role). session_ttl = the standard-user tier
            // (kept as the historical name); privileged = admin/superadmin/developer; guest = anonymous.
            ['text', 'session_ttl_privileged', [
                'required'   => true,
                'filters'    => ['StringTrim'],
                'validators' => [['Digits'], ['GreaterThan', false, ['min' => 59]]],
                'attribs'    => array_merge($control, ['id' => 'set-session-ttl-priv', 'inputmode' => 'numeric']),
            ]],
            ['text', 'session_ttl', [
                'required'   => true,
                'filters'    => ['StringTrim'],
                'validators' => [['Digits'], ['GreaterThan', false, ['min' => 59]]],
                'attribs'    => array_merge($control, ['id' => 'set-session-ttl', 'inputmode' => 'numeric']),
            ]],
            ['text', 'session_ttl_guest', [
                'required'   => true,
                'filters'    => ['StringTrim'],
                'validators' => [['Digits'], ['GreaterThan', false, ['min' => 59]]],
                'attribs'    => array_merge($control, ['id' => 'set-session-ttl-guest', 'inputmode' => 'numeric']),
            ]],
            ['checkbox', 'autologout_enabled', [
                'attribs' => ['id' => 'set-autologout-enabled', 'class' => 'form-check-input'],
            ]],
            ['text', 'autologout_seconds', [
                'required'   => true,
                'filters'    => ['StringTrim'],
                'validators' => [['Digits'], ['GreaterThan', false, ['min' => 29]]],
                'attribs'    => array_merge($control, ['id' => 'set-autologout-seconds', 'inputmode' => 'numeric']),
            ]],
            ['radio', 'autologout_action', [
                'multiOptions' => ['logout' => $this->_t('system.settings.autologout.action_logout'), 'lock' => $this->_t('system.settings.autologout.action_lock')],
                'value'        => 'logout',
                'separator'    => '',
                'attribs'      => ['class' => 'form-check-input'],
            ]],

            // Email SMTP tab — transport + the SMTP connection + the From identity. Everything is
            // optional: `mail` (PHP sendmail) needs none of it. The password is a password field —
            // blank = keep the stored one (Tiger_Mail::saveSettings), which is also why the current
            // secret is never rendered back into the form.
            // The provider drives everything: it picks the transport kind, supplies the SMTP
            // defaults, and declares which credential fields the screen renders.
            ['select', 'mail_provider', [
                'multiOptions' => Tiger_Mail_Provider::options(),
                'value'        => 'sendmail',
                'attribs'      => ['id' => 'set-mail-provider', 'class' => 'form-select'],
            ]],
            ['text', 'mail_smtp_host', [
                'required' => false,
                'filters'  => ['StringTrim'],
                'attribs'  => array_merge($control, ['id' => 'set-mail-host', 'autocomplete' => 'off',
                                                     'placeholder' => 'email-smtp.us-east-1.amazonaws.com']),
            ]],
            ['text', 'mail_smtp_port', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [['Digits'], ['Between', false, ['min' => 1, 'max' => 65535, 'inclusive' => true]]],
                'attribs'    => array_merge($control, ['id' => 'set-mail-port', 'inputmode' => 'numeric', 'placeholder' => '587']),
            ]],
            ['select', 'mail_smtp_ssl', [
                'multiOptions' => [
                    'tls' => $this->_t('system.settings.smtp.ssl_tls'),
                    'ssl' => $this->_t('system.settings.smtp.ssl_ssl'),
                    ''    => $this->_t('system.settings.smtp.ssl_none'),
                ],
                'value'   => 'tls',
                'attribs' => ['id' => 'set-mail-ssl', 'class' => 'form-select'],
            ]],
            ['select', 'mail_smtp_auth', [
                'multiOptions' => [
                    'login'   => $this->_t('system.settings.smtp.auth_login'),
                    'plain'   => $this->_t('system.settings.smtp.auth_plain'),
                    'crammd5' => $this->_t('system.settings.smtp.auth_crammd5'),
                    ''        => $this->_t('system.settings.smtp.auth_none'),
                ],
                'value'   => 'login',
                'attribs' => ['id' => 'set-mail-auth', 'class' => 'form-select'],
            ]],
            ['text', 'mail_smtp_username', [
                'required' => false,
                'filters'  => ['StringTrim'],
                'attribs'  => array_merge($control, ['id' => 'set-mail-username', 'autocomplete' => 'off']),
            ]],
            ['password', 'mail_smtp_password', [
                'required' => false,
                'filters'  => ['StringTrim'],
                'attribs'  => array_merge($control, ['id' => 'set-mail-password', 'autocomplete' => 'new-password']),
            ]],
            ['text', 'mail_from_email', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [['EmailAddress']],
                'attribs'    => array_merge($control, ['id' => 'set-mail-from-email', 'placeholder' => 'no-reply@example.com']),
            ]],
            ['text', 'mail_from_name', [
                'required' => false,
                'filters'  => ['StringTrim'],
                'attribs'  => array_merge($control, ['id' => 'set-mail-from-name', 'placeholder' => 'Tiger']),
            ]],
            ['text', 'mail_test_to', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [['EmailAddress']],
                'attribs'    => array_merge($control, ['id' => 'set-mail-test-to', 'placeholder' => 'you@example.com']),
            ]],

            // reCAPTCHA tab — keys are optional (a keyless install just leaves the widget off). The
            // secret is a password field: blank = keep the current one (Tiger_Recaptcha::saveSettings).
            ['checkbox', 'recaptcha_enabled', [
                'attribs' => ['id' => 'set-rc-enabled', 'class' => 'form-check-input'],
            ]],
            ['select', 'recaptcha_version', [
                'multiOptions' => ['v2' => $this->_t('system.settings.recaptcha.version_v2'), 'v3' => $this->_t('system.settings.recaptcha.version_v3')],
                'value'        => 'v2',
                'attribs'      => ['id' => 'set-rc-version', 'class' => 'form-select'],
            ]],
            ['text', 'recaptcha_site_key', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'attribs'    => array_merge($control, ['id' => 'set-rc-site', 'autocomplete' => 'off']),
            ]],
            ['password', 'recaptcha_secret_key', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'attribs'    => array_merge($control, ['id' => 'set-rc-secret', 'autocomplete' => 'new-password']),
            ]],
            ['text', 'recaptcha_min_score', [
                'required'   => false,
                'filters'    => ['StringTrim'],
                'validators' => [['Float'], ['Between', false, ['min' => 0, 'max' => 1, 'inclusive' => true]]],
                'attribs'    => array_merge($control, ['id' => 'set-rc-score', 'inputmode' => 'decimal']),
            ]],
            ['checkbox', 'recaptcha_fail_open', [
                'attribs' => ['id' => 'set-rc-failopen', 'class' => 'form-check-input'],
            ]],
            ['checkbox', 'recaptcha_hide_badge', [
                'attribs' => ['id' => 'set-rc-hidebadge', 'class' => 'form-check-input'],
            ]],
        ];
    }
}
