<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * System_Service_Settings — /api service for the System settings screen.
 *
 * Validates System_Form_Settings, then writes to the DB `config` table (scope=global)
 * via Tiger_Model_Config — the live-override tier, effective next request, no deploy.
 * Config-discipline: the config store, not a settings table. ACL: admin+
 * (modules/system/configs/acl.ini).
 *
 * @api
 */
class System_Service_Settings extends Tiger_Service_Service
{
    /**
     * Validate the settings form and write session + auto-logout values to the config table.
     *
     * @param  array $params the settings form payload
     * @return void
     */
    public function save(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $form = new System_Form_Settings();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        $v = $form->getValues();

        try {
            $cfg = new Tiger_Model_Config();
            $g   = Tiger_Model_Config::SCOPE_GLOBAL;

            // The three idle-TTL tiers (server-side max, by role); auto-logout is the proactive
            // inactivity feature (toggle + window + action).
            $cfg->set($g, '', 'tiger.session.ttl.privileged', (string) max(60, (int) $v['session_ttl_privileged']));
            $cfg->set($g, '', 'tiger.session.ttl.authed', (string) max(60, (int) $v['session_ttl']));
            $cfg->set($g, '', 'tiger.session.ttl.guest', (string) max(60, (int) $v['session_ttl_guest']));
            $cfg->set($g, '', 'tiger.session.autologout.enabled', !empty($v['autologout_enabled']) ? '1' : '0');
            $cfg->set($g, '', 'tiger.session.autologout.seconds', (string) max(30, (int) $v['autologout_seconds']));
            $cfg->set($g, '', 'tiger.session.autologout.action', $v['autologout_action'] === 'lock' ? 'lock' : 'logout');

            // Email SMTP tab — shared writer (encrypts the password; a blank one keeps the current).
            // The provider decides the transport kind: an API provider stores its credentials and
            // returns early; sendmail means PHP mail(); everything else is SMTP.
            $provider = (string) $v['mail_provider'];
            $pDef     = Tiger_Mail_Provider::get($provider);
            $fields   = (isset($params['mail_field'][$provider]) && is_array($params['mail_field'][$provider]))
                ? $params['mail_field'][$provider] : [];

            Tiger_Mail::saveSettings([
                'provider'   => $provider,
                'fields'     => $fields,
                'transport'  => ($pDef && $pDef['kind'] === Tiger_Mail_Provider::KIND_SMTP && $provider !== 'sendmail') ? 'smtp' : 'mail',
                'host'       => $v['mail_smtp_host'],
                'port'       => $v['mail_smtp_port'],
                'ssl'        => $v['mail_smtp_ssl'],
                'auth'       => $v['mail_smtp_auth'],
                'username'   => $v['mail_smtp_username'],
                'password'   => $v['mail_smtp_password'],
                'from_email' => $v['mail_from_email'],
                'from_name'  => $v['mail_from_name'],
            ]);

            // reCAPTCHA tab — shared writer (encrypts the secret; blank secret keeps the current one).
            Tiger_Recaptcha::saveSettings([
                'enabled'    => !empty($v['recaptcha_enabled']) ? 1 : 0,
                'version'    => $v['recaptcha_version'],
                'site_key'   => $v['recaptcha_site_key'],
                'secret_key' => $v['recaptcha_secret_key'],
                'min_score'  => $v['recaptcha_min_score'] === '' ? 0.5 : $v['recaptcha_min_score'],
                'fail_open'  => !empty($v['recaptcha_fail_open']) ? 1 : 0,
                'hide_badge' => !empty($v['recaptcha_hide_badge']) ? 1 : 0,
            ]);

            // Location tab — provider selection + each adapter's own fields (secrets encrypted). The form
            // fields are dynamic per adapter, so they ride on $params directly (not the static form).
            if (class_exists('Tiger_Location') && method_exists('Tiger_Location', 'saveSettings')
                && array_key_exists('location_ip_provider', $params)) {
                Tiger_Location::saveSettings([
                    'ip_provider'      => $params['location_ip_provider'] ?? null,
                    'address_provider' => $params['location_address_provider'] ?? null,
                    'cache_ttl'        => $params['location_cache_ttl'] ?? null,
                    'adapters'         => (isset($params['location_adapter']) && is_array($params['location_adapter'])) ? $params['location_adapter'] : [],
                ]);
            }

            // Cookies tab — GDPR consent mode + banner copy (shared writer; rides on $params).
            if (class_exists('Tiger_Consent') && array_key_exists('consent_mode', $params)) {
                Tiger_Consent::saveSettings([
                    'mode'         => $params['consent_mode']         ?? null,
                    'message'      => $params['consent_message']      ?? null,
                    'accept_label' => $params['consent_accept_label'] ?? null,
                    'reject_label' => $params['consent_reject_label'] ?? null,
                    'policy_url'   => $params['consent_policy_url']   ?? null,
                    'ccpa_notice'  => $params['consent_ccpa_notice']  ?? null,
                    'honor_gpc'    => !empty($params['consent_honor_gpc']),   // unchecked box = absent = off
                ]);
            }

            // Signup tab — the public-signup kill switch. Lives in the lazy option table (checked only
            // on the signup route, so it's not eager config). Absent checkbox = off.
            if (array_key_exists('signup_settings', $params)) {
                (new Tiger_Model_Option())->set(
                    Tiger_Model_Option::SCOPE_GLOBAL, '', 'signup.public_disabled',
                    !empty($params['signup_disabled']) ? '1' : '0'
                );
            }

            $this->_success([], 'system.settings.saved', '/system/settings');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Live IP-geolocation test for the Location tab's "Test" button. Uses the selected provider with the
     * form's current field values (so a just-typed key is testable without saving). Defaults to the
     * caller's own IP. Returns the lookup result as data (ok + country/city/label, or an error message).
     *
     * @param  array $params ip?, provider, config[field]
     * @return void
     */
    public function locationTest(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        if (!class_exists('Tiger_Location') || !method_exists('Tiger_Location', 'test')) {
            $this->_error('core.api.error.general'); return;
        }
        $ip = trim((string) ($params['ip'] ?? ''));
        if ($ip === '') { $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? ''); }
        $provider = (string) ($params['provider'] ?? '');
        $config   = (isset($params['config']) && is_array($params['config'])) ? $params['config'] : [];

        $this->_success(Tiger_Location::test($ip, $provider, $config));
    }

    /**
     * Live send test for the Email SMTP tab's "Send test" button — the answer to "is my mail
     * actually configured?", which is otherwise unanswerable: the password-reset flow deliberately
     * reveals nothing (no account enumeration), so a broken MTA looks identical to a working one.
     *
     * Like `locationTest`, it uses the form's CURRENT values, so a just-typed host/port/password is
     * testable WITHOUT saving — a wrong setting never has to overwrite a working one to be tried. A
     * blank password falls back to the stored secret (blank = keep, same rule as save).
     *
     * The transport's error text is returned verbatim: on a connection test the message ("Could not
     * open socket", an auth rejection, a TLS failure) IS the diagnostic, and this action is admin-only.
     *
     * @param  array $params to, mail_transport, mail_smtp_{host,port,ssl,auth,username,password}, mail_from_{email,name}
     * @return void
     */
    public function mailTest(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $to = trim((string) ($params['to'] ?? ''));
        if ($to === '' || !Zend_Validate::is($to, 'EmailAddress')) {
            $this->_error('system.settings.smtp.test_bad_address'); return;
        }

        // Blank password → the stored one, so testing an existing setup needs no re-typing.
        $password = (string) ($params['mail_smtp_password'] ?? '');
        if ($password === '') { $password = Tiger_Mail::storedSmtpPassword(); }

        $provider = (string) ($params['mail_provider'] ?? '');
        $pDef     = Tiger_Mail_Provider::get($provider);
        $isApi    = $pDef && $pDef['kind'] === Tiger_Mail_Provider::KIND_API;

        if ($isApi && !Tiger_Mail_Provider::isAvailable($provider)) {
            $this->_success(['ok' => false, 'to' => $to,
                'error' => $this->_translate($pDef['requires_hint'] ?? 'core.mail.provider.requires.generic')]);
            return;
        }

        // API credentials from the form, with each blank secret falling back to the stored one.
        $fields = [];
        if ($isApi) {
            $submitted = (isset($params['mail_field'][$provider]) && is_array($params['mail_field'][$provider]))
                ? $params['mail_field'][$provider] : [];
            $stored = Tiger_Mail::apiCredentials($provider);
            foreach (array_keys(Tiger_Mail_Provider::fields($provider)) as $f) {
                $val = trim((string) ($submitted[$f] ?? ''));
                $fields[$f] = $val !== '' ? $val : (string) ($stored[$f] ?? '');
            }
        }

        $values = [
            'transport' => $isApi ? 'api' : (($pDef && $provider !== 'sendmail') ? 'smtp' : 'mail'),
            'provider'  => $provider,
            'fields'    => $fields,
            'host'      => (string) ($params['mail_smtp_host'] ?? ''),
            'port'      => (string) ($params['mail_smtp_port'] ?? ''),
            'ssl'       => (string) ($params['mail_smtp_ssl'] ?? ''),
            'auth'      => (string) ($params['mail_smtp_auth'] ?? ''),
            'username'  => (string) ($params['mail_smtp_username'] ?? ''),
            'password'  => $password,
        ];

        $started = microtime(true);
        try {
            $mail = new Tiger_Mail();
            $from = trim((string) ($params['mail_from_email'] ?? ''));
            if ($from !== '') { $mail->from($from, trim((string) ($params['mail_from_name'] ?? ''))); }

            $mail->to($to)
                 ->subject($this->_translate('system.settings.smtp.test_subject'))
                 ->html('<p>' . htmlspecialchars($this->_translate('system.settings.smtp.test_body'), ENT_QUOTES) . '</p>')
                 ->send(Tiger_Mail::transportFor($values));

            $this->_success([
                'ok'      => true,
                'to'      => $to,
                'ms'      => (int) round((microtime(true) - $started) * 1000),
                'via'     => $isApi
                    ? (string) $pDef['label']
                    : (($values['transport'] === 'smtp' && $values['host'] !== '')
                        ? $values['host'] . ':' . ($values['port'] !== '' ? $values['port'] : '25')
                        : 'sendmail'),
            ], 'system.settings.smtp.test_sent');
        } catch (Throwable $e) {
            $this->_success([
                'ok'    => false,
                'to'    => $to,
                'ms'    => (int) round((microtime(true) - $started) * 1000),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** Translate a key through the registered translator, falling back to the key itself. */
    protected function _translate(string $key): string
    {
        try {
            if (Zend_Registry::isRegistered('Zend_Translate')) {
                return (string) Zend_Registry::get('Zend_Translate')->translate($key);
            }
        } catch (Throwable $e) {
            // fall through — a test send must never fail on a missing translator
        }
        return $key;
    }
}
