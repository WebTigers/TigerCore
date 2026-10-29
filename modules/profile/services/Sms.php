<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Profile_Service_Sms — the /api service behind the profile Security tab's "Phone & SMS" card.
 *
 * Self-service and STRICTLY self-scoped ($this->_user_id, never a payload id): a person adds and
 * verifies their OWN mobile number for SMS. The flow is a carrier-compliant double opt-in:
 *
 *   requestCode  — the user enters a number + ticks the consent box → an sms_otp challenge is issued
 *                  and a 6-digit code texted (proving they own the number; the consent box is the TCPA
 *                  opt-in). Refused without consent, for a number another account already verified, or
 *                  over the hourly send cap.
 *   verifyCode   — the user enters the code → the pending `sms` credential factor is marked VERIFIED
 *                  (globally unique, migration 0056) and an auditable consent record is written
 *                  (Tiger_Model_SmsConsent): who/when/IP/scopes/the exact language shown. The number is
 *                  now a login identifier (log in by phone → OTP/magic link) and alert target.
 *   remove       — unlink the number (frees it globally) and soft-delete the consent record.
 *
 * STOP/HELP/START suppression is handled out-of-band by the inbound webhook subscriber
 * (Sms_Bootstrap → Tiger_Model_SmsConsent::optOutByPhone); this service only drives enrollment.
 *
 * @api
 */
class Profile_Service_Sms extends Profile_Service_Base
{
    /**
     * The exact consent language the user agrees to — ONE authority, rendered two ways.
     *
     * Carriers (and the US toll-free / 10DLC registration forms) check the opt-in location for a
     * fixed list: the program/brand name, message frequency, "message and data rates may apply",
     * STOP to opt out, HELP for help, and links to the terms and privacy policy. A checkbox missing
     * any of them is the single most common registration rejection, so the sentence is assembled
     * here rather than hardcoded — the brand is the install's own SMS brand (Tiger_Sms::brand(), a
     * deliberate SMS setting and never a hardcoded "WebTigers"; core ships to every customer) and the
     * legal links resolve through Tiger_View_Helper_LegalLinks, which honours tiger.footer.*_url and
     * otherwise finds a published page at the slug.
     *
     * The brand belongs to the SMS module, not to the marketing site: it is what a carrier reviews the
     * registration against, so it must be settable without renaming the site.
     *
     * Rendered twice from the same source so the audit record can never drift from what the user
     * actually saw: $html = true for the checkbox label (anchors, escaped), false for the plain
     * text snapshotted into `sms_consent.consent_text` (URLs spelled out, since a stored record
     * nobody can click needs the address in words).
     *
     * Translations stay PLAIN TEXT — no markup in a language file; the anchors are built here.
     *
     * @param  bool $html true for the HTML label, false for the audit snapshot
     * @return string the full consent sentence
     */
    public static function consentLabel($html = false)
    {
        // ONE view for both jobs: t() owns the locale-fallback chain (active -> source -> key) and
        // legalLinks() owns "what counts as a live legal page". Neither is reimplemented here.
        //
        // It must be a view that can actually RESOLVE those helpers. A bare `new Zend_View()` carries
        // only the Zend_View_Helper_ path, so t()/legalLinks() throw PluginLoader_Exception — and this
        // runs from verify() to snapshot the audit record, where a throw would break enrollment. So:
        // prefer the MVC's own view (a request), else build one and register the Tiger helper path the
        // bootstrap uses. Any failure degrades to '' rather than taking the caller down with it.
        $view = self::_helperView();
        if (!$view) { return ''; }

        $esc = function ($v) use ($html) {
            return $html ? htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') : (string) $v;
        };

        try {
            $label = sprintf($esc($view->t('profile.sms.consent_label')), $esc(Tiger_Sms::brand()));

            $links = self::_legalUrls($view);
            $parts = [];
            foreach (['terms' => 'profile.sms.consent_terms', 'privacy' => 'profile.sms.consent_privacy'] as $k => $key) {
                if (empty($links[$k])) { continue; }
                $text = $esc($view->t($key));
                $parts[$k] = $html
                    ? '<a href="' . htmlspecialchars($links[$k], ENT_QUOTES, 'UTF-8') . '">' . $text . '</a>'
                    : $text . ' (' . $links[$k] . ')';
            }

            // Both, one, or neither — an install with no legal pages still yields a valid sentence.
            if (count($parts) === 2) {
                $label .= ' ' . vsprintf($esc($view->t('profile.sms.consent_legal')), array_values($parts));
            } elseif (count($parts) === 1) {
                $label .= ' ' . sprintf($esc($view->t('profile.sms.consent_legal_one')), reset($parts));
            }
            return $label;
        } catch (Throwable $e) {
            return '';
        }
    }

    /**
     * A view that can resolve Tiger_View_Helper_* — the MVC's own when there is one, else a fresh
     * view with the bootstrap's helper path added. Null when neither can be had.
     */
    protected static function _helperView()
    {
        try {
            $vr = Zend_Controller_Action_HelperBroker::getStaticHelper('ViewRenderer');
            if ($vr && $vr->view instanceof Zend_View_Interface) { return $vr->view; }
        } catch (Throwable $e) {
            // no MVC in play (CLI, a job) — fall through and build one
        }
        try {
            $view = new Zend_View();
            if (defined('TIGER_CORE_PATH')) {
                $view->addHelperPath(TIGER_CORE_PATH . '/library/Tiger/View/Helper', 'Tiger_View_Helper');
            }
            return $view;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Absolute terms/privacy URLs for the consent sentence, or [] when this install has neither.
     *
     * Absolute because the audit snapshot is read long after the fact and outside any request, where
     * a bare "/terms" names nothing. Falls back to the relative path when tiger.site.url is unset.
     */
    protected static function _legalUrls($view)
    {
        $out = [];
        try {
            $links = $view->legalLinks();
        } catch (Throwable $e) {
            return $out;
        }
        $cfg  = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        $base = ($cfg && $cfg->get('tiger') && $cfg->tiger->get('site'))
            ? rtrim((string) $cfg->tiger->site->get('url'), '/') : '';
        foreach (['terms', 'privacy'] as $k) {
            $u = isset($links[$k]) ? (string) $links[$k] : '';
            if ($u === '') { continue; }
            $out[$k] = ($base !== '' && strpos($u, '/') === 0) ? $base . $u : $u;
        }
        return $out;
    }

    /**
     * Current SMS state for the signed-in user (drives the card render + every refresh).
     *
     * @param  array $params unused
     * @return void
     */
    public function status(array $params): void
    {
        $userId = (string) $this->_user_id;
        if ($userId === '') { $this->_error('core.api.error.not_allowed'); return; }
        $this->_success($this->_state($userId));
    }

    /**
     * Step 1 — send a verification code to a number the user is enrolling (requires the consent tick).
     *
     * @param  array $params phone, consent (1), _csrf
     * @return void
     */
    public function requestCode(array $params): void
    {
        $userId = (string) $this->_user_id;
        if ($userId === '') { $this->_error('core.api.error.not_allowed'); return; }

        $form = new Profile_Form_Sms();
        if (!$this->_validCsrf(Profile_Form_Sms::class, $params)) { $this->_error('core.api.error.csrf'); return; }

        // Consent is mandatory — we never text a number without the opt-in (TCPA/10DLC).
        if (empty($params['consent'])) { $this->_error('profile.sms.consent_required', ['field' => 'consent']); return; }

        $e164 = Tiger_Sms::e164((string) ($params['phone'] ?? ''));
        if ($e164 === '') { $this->_error('profile.sms.bad_phone', ['field' => 'phone']); return; }

        $cred = new Tiger_Model_UserCredential();

        // Already this user's verified number? Nothing to do.
        $mine = $cred->factor($userId, Tiger_Model_UserCredential::TYPE_SMS, $e164);
        if ($mine && $mine->verified_at !== null) { $this->_error('profile.sms.already_yours', ['field' => 'phone']); return; }

        // Taken by another account (global uniqueness — one verified number, one user)?
        $owner = $cred->findVerifiedByIdentifier(Tiger_Model_UserCredential::TYPE_SMS, $e164);
        if ($owner && (string) $owner->user_id !== $userId) { $this->_error('profile.sms.in_use', ['field' => 'phone']); return; }

        // We can only text if a provider is configured — don't mint a code we can't deliver.
        if (!Tiger_Sms::isConfigured()) { $this->_error('profile.sms.not_configured'); return; }

        $ch = new Tiger_Model_AuthChallenge();
        if ($ch->countRecent($userId, 'sms_otp', 3600) >= 5) { $this->_error('profile.sms.too_many'); return; }

        try {
            // Reuse the pending factor for this number, else create it (unverified).
            $credId = $mine ? (string) $mine->credential_id : (string) $cred->addSms($userId, $e164);

            $ch->invalidateActive($userId, 'sms_otp');   // only the newest code is ever valid
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $ch->issue($userId, 'sms_otp', $code, 600);

            $res = Tiger_Sms::send($e164, Tiger_Sms::brand() . ' verification code: ' . $code . '. It expires in 10 minutes. Reply HELP for help, STOP to opt out.');
            if (empty($res['ok'])) { $this->_error('profile.sms.send_failed'); return; }

            $data = $this->_state($userId);
            $data['pending_phone'] = $e164;
            $data['pending_mask']  = $this->_mask($e164);
            $this->_success($data, 'profile.sms.code_sent');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Step 2 — confirm the texted code: mark the number verified + record consent (double opt-in done).
     *
     * @param  array $params phone, code, _csrf
     * @return void
     */
    public function verifyCode(array $params): void
    {
        $userId = (string) $this->_user_id;
        if ($userId === '') { $this->_error('core.api.error.not_allowed'); return; }
        if (!$this->_validCsrf(Profile_Form_Sms::class, $params)) { $this->_error('core.api.error.csrf'); return; }

        $e164 = Tiger_Sms::e164((string) ($params['phone'] ?? ''));
        $code = preg_replace('/\D/', '', (string) ($params['code'] ?? ''));
        if ($e164 === '') { $this->_error('profile.sms.bad_phone', ['field' => 'phone']); return; }

        $cred    = new Tiger_Model_UserCredential();
        $pending = $cred->factor($userId, Tiger_Model_UserCredential::TYPE_SMS, $e164);
        if (!$pending) { $this->_error('profile.sms.no_pending'); return; }

        // Redeem the newest active sms_otp (single-use, TTL- and attempt-limited inside the model).
        $chModel = new Tiger_Model_AuthChallenge();
        $active  = $chModel->latestActive($userId, 'sms_otp');
        if ($code === '' || !$active || !$chModel->redeem($active->challenge_id, $code)) {
            $this->_error('profile.sms.bad_code', ['field' => 'code']); return;
        }

        // Race backstop before the write (the unique index is the hard guarantee).
        $owner = $cred->findVerifiedByIdentifier(Tiger_Model_UserCredential::TYPE_SMS, $e164);
        if ($owner && (string) $owner->user_id !== $userId) { $this->_error('profile.sms.in_use', ['field' => 'phone']); return; }

        try {
            $this->_transaction(function () use ($cred, $userId, $e164, $pending) {
                // Retire any OTHER sms factor this user holds (one verified number per account); a
                // soft-deleted row's verified_sms_key becomes NULL, freeing that number globally.
                foreach ($cred->factorsFor($userId) as $f) {
                    if ((string) $f->type === Tiger_Model_UserCredential::TYPE_SMS
                        && (string) $f->credential_id !== (string) $pending->credential_id) {
                        $cred->softDelete($cred->getAdapter()->quoteInto('credential_id = ?', (string) $f->credential_id));
                    }
                }
                $cred->markVerified((string) $pending->credential_id);   // UNIQUE(verified_sms_key) is the backstop
                (new Tiger_Model_SmsConsent())->record(
                    $userId,
                    $e164,
                    [Tiger_Model_SmsConsent::SCOPE_TWOFA, Tiger_Model_SmsConsent::SCOPE_ALERTS],
                    self::consentLabel(),
                    $_SERVER['REMOTE_ADDR'] ?? null
                );
            });
            $this->_success($this->_state($userId), 'profile.sms.verified');
        } catch (Throwable $e) {
            // A duplicate-key here means another account verified it in the race window.
            $msg = $e->getMessage();
            if (stripos($msg, 'uq_cred_verified_sms') !== false || stripos($msg, 'Duplicate') !== false) {
                $this->_error('profile.sms.in_use', ['field' => 'phone']); return;
            }
            $this->_error(APPLICATION_ENV !== 'production' ? $msg : 'core.api.error.general');
        }
    }

    /**
     * Remove the user's verified/pending number and soft-delete their consent record.
     *
     * @param  array $params _csrf
     * @return void
     */
    public function remove(array $params): void
    {
        $userId = (string) $this->_user_id;
        if ($userId === '') { $this->_error('core.api.error.not_allowed'); return; }
        if (!$this->_validCsrf(Profile_Form_Sms::class, $params)) { $this->_error('core.api.error.csrf'); return; }

        try {
            $this->_transaction(function () use ($userId) {
                $cred = new Tiger_Model_UserCredential();
                foreach ($cred->factorsFor($userId) as $f) {
                    if ((string) $f->type === Tiger_Model_UserCredential::TYPE_SMS) {
                        $cred->softDelete($cred->getAdapter()->quoteInto('credential_id = ?', (string) $f->credential_id));
                    }
                }
                (new Tiger_Model_SmsConsent())->removeForUser($userId);
            });
            $this->_success($this->_state($userId), 'profile.sms.removed');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * The current SMS state payload + a rotated CSRF token for the reused card form.
     *
     * @param  string $userId
     * @return array
     */
    protected function _state($userId)
    {
        $cred     = new Tiger_Model_UserCredential();
        $verified = null;
        $pending  = null;
        foreach ($cred->factorsFor($userId) as $f) {
            if ((string) $f->type !== Tiger_Model_UserCredential::TYPE_SMS) { continue; }
            if ($f->verified_at !== null) { $verified = $f; } else { $pending = $f; }
        }
        $consent = (new Tiger_Model_SmsConsent())->forUser($userId);

        return [
            'sms' => [
                'verified'      => $verified ? true : false,
                'phone'         => $verified ? $this->_mask((string) $verified->identifier) : '',
                'pending'       => $pending ? true : false,
                'pending_phone' => $pending ? (string) $pending->identifier : '',
                'pending_mask'  => $pending ? $this->_mask((string) $pending->identifier) : '',
                'scopes'        => $consent ? (string) $consent->scopes : '',
                'opted_out'     => $consent && $consent->opt_out_at !== null,
            ],
            '_csrf' => $this->_freshToken(Profile_Form_Sms::class),
        ];
    }

    /**
     * Mask an E.164 number for display — last four digits only (e.g. +1 ••• ••• 3563 → ••••••3563).
     *
     * @param  string $e164
     * @return string
     */
    protected function _mask($e164)
    {
        $e164 = (string) $e164;
        if (strlen($e164) <= 4) { return $e164; }
        return '••••••' . substr($e164, -4);
    }

}
