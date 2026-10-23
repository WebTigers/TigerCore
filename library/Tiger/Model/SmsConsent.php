<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * SmsConsent — the TCPA / 10DLC consent + suppression record for a user's verified mobile number.
 *
 * A verified phone is an auth factor (Tiger_Model_UserCredential type `sms`); CONSENT to text it is a
 * separate, auditable fact (see migration 0055). Each user has at most one active record: who consented,
 * when, from what IP, to which message categories (scopes), the exact language shown, and whether a STOP
 * has since suppressed them. This is the artifact a carrier/TCR audit asks for, and the gate every
 * Tiger_Sms alert sender consults via isSuppressed() before sending to a number.
 *
 * Follows the live-override shape (like Tiger_Model_EmailTemplate): record() revives + overwrites the
 * user's one row rather than inserting duplicates, so a remove → re-consent cycle updates one row and the
 * `(user_id)` unique holds. STOP/START arrive by phone number (the inbound webhook has no user), so the
 * opt-out/opt-in helpers resolve by phone.
 *
 * @api
 */
class Tiger_Model_SmsConsent extends Tiger_Model_Table
{
    protected $_name    = 'sms_consent';
    protected $_primary = 'sms_consent_id';

    /** Consent to receive 2FA / security sign-in codes by SMS. */
    const SCOPE_TWOFA  = 'twofa';
    /** Consent to receive transactional account + security alerts by SMS. */
    const SCOPE_ALERTS = 'alerts';

    /**
     * The user's active (non-deleted) consent record, or null.
     *
     * @param  string $userId the owning user's id
     * @return Zend_Db_Table_Row_Abstract|null
     */
    public function forUser($userId)
    {
        return $this->fetchRow($this->activeSelect()->where('user_id = ?', (string) $userId)) ?: null;
    }

    /**
     * Record (or refresh) a user's SMS consent — the confirmed opt-in after an OTP round-trip.
     *
     * Upserts the user's one row (revives a prior soft-deleted/opted-out record for the same user so the
     * `(user_id)` unique holds), stamping the consent moment: the number, the consented scopes, a snapshot
     * of the language shown, the IP, and `consent_at = now`. Clears any prior opt-out.
     *
     * @param  string      $userId   the owning user's id
     * @param  string      $phone    the E.164 number consent was captured for
     * @param  string[]    $scopes   consented categories (SCOPE_TWOFA, SCOPE_ALERTS)
     * @param  string      $text     the exact consent language the user agreed to (audit snapshot)
     * @param  string|null $ip       the request IP at opt-in (audit)
     * @return string                the sms_consent_id
     */
    public function record($userId, $phone, array $scopes, $text, $ip = null)
    {
        $data = [
            'user_id'      => (string) $userId,
            'phone'        => (string) $phone,
            'scopes'       => implode(',', array_values(array_unique(array_filter(array_map('strval', $scopes))))),
            'consent_text' => (string) $text,
            'consent_ip'   => $ip !== null ? substr((string) $ip, 0, 45) : null,
            'consent_at'   => $this->_now(),
            'opt_out_at'   => null,
            'status'       => 'active',
            'deleted'      => 0,
        ];
        // Match ANY row (incl. soft-deleted) for this user so a prior remove/opt-out doesn't collide on the
        // (user_id) unique — revive + overwrite it instead of inserting a duplicate.
        $row = $this->fetchRow(
            $this->select()->where('user_id = ?', (string) $userId)->order('created_at DESC')
        );
        if ($row) {
            $this->update($data, $this->getAdapter()->quoteInto('sms_consent_id = ?', $row->sms_consent_id));
            return (string) $row->sms_consent_id;
        }
        return (string) $this->insert($data);
    }

    /**
     * Is this number currently suppressed — a live consent row carrying a STOP (opt-out)?
     *
     * A Tiger_Sms alert sender calls this before sending; true means the recipient has opted out and must
     * not be messaged. A number with no record at all is NOT suppressed (it simply has no consent yet —
     * that's the sender's own consent gate, separate from suppression).
     *
     * @param  string $phone the E.164 number
     * @return bool
     */
    public function isSuppressed($phone)
    {
        if ((string) $phone === '') { return false; }
        return (bool) $this->fetchRow(
            $this->activeSelect()
                ->where('phone = ?', (string) $phone)
                ->where('opt_out_at IS NOT NULL')
        );
    }

    /**
     * Honor a STOP: suppress every active consent record for a number (the inbound webhook has no user).
     *
     * @param  string $phone the E.164 number that texted STOP
     * @return int    rows suppressed
     */
    public function optOutByPhone($phone)
    {
        if ((string) $phone === '') { return 0; }
        $db = $this->getAdapter();
        return (int) $this->update(
            ['opt_out_at' => $this->_now(), 'status' => 'opted_out'],
            $db->quoteInto('phone = ?', (string) $phone) . ' AND deleted = 0 AND opt_out_at IS NULL'
        );
    }

    /**
     * Honor a START/UNSTOP: lift the suppression on a number's active records.
     *
     * @param  string $phone the E.164 number that texted START
     * @return int    rows re-subscribed
     */
    public function optInByPhone($phone)
    {
        if ((string) $phone === '') { return 0; }
        $db = $this->getAdapter();
        return (int) $this->update(
            ['opt_out_at' => null, 'status' => 'active'],
            $db->quoteInto('phone = ?', (string) $phone) . ' AND deleted = 0 AND opt_out_at IS NOT NULL'
        );
    }

    /**
     * Remove a user's consent (soft-delete) — called when they remove their verified number.
     *
     * @param  string $userId the owning user's id
     * @return bool            true when a record was present and removed
     */
    public function removeForUser($userId)
    {
        $row = $this->forUser($userId);
        if (!$row) { return false; }
        $this->softDelete($this->getAdapter()->quoteInto('sms_consent_id = ?', $row->sms_consent_id));
        return true;
    }
}
