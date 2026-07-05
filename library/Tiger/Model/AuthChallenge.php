<?php
/**
 * AuthChallenge — transient, single-use auth proofs (OTP codes, reset/verify/magic
 * tokens). See migration 0005 for the security rationale.
 *
 * The PK is a **v4** UUID (opaque) — challenge ids can appear in URLs, so they must
 * not leak timing. Codes are stored HASHED and compared with hash_equals(); reuse,
 * expiry, and brute-force are all enforced here so callers can't forget them.
 *
 * @api
 */
class Tiger_Model_AuthChallenge extends Tiger_Model_Table
{
    protected $_name    = 'auth_challenge';
    protected $_primary = 'challenge_id';

    /** Opaque ids: a challenge id must not reveal when it was minted (v4, not v7). */
    protected $_uuidVersion = 4;

    /** Lock a challenge after this many wrong attempts (brute-force guard). */
    const MAX_ATTEMPTS = 5;

    /**
     * Issue a challenge: store the HASH of the code and a TTL. The plaintext code is
     * the caller's to deliver (SMS/email) and is never persisted.
     *
     * @param  string|null $userId      may be null in pre-login flows
     * @param  string      $type        sms_otp | email_verify | password_reset | magic_link
     * @param  string      $plainCode   the code/token delivered out-of-band
     * @param  int         $ttlSeconds  lifetime (default 10 min)
     * @return string      challenge_id
     */
    public function issue($userId, $type, $plainCode, $ttlSeconds = 600)
    {
        return $this->insert(array(
            'user_id'    => $userId,
            'type'       => $type,
            'code_hash'  => $this->hashCode($plainCode),
            'expires_at' => date('Y-m-d H:i:s', time() + $ttlSeconds),
        ));
    }

    /**
     * Verify and CONSUME a challenge (single-use). Returns the challenge row on
     * success, or null on any failure (missing / already used / expired / locked /
     * wrong code). A wrong code increments the attempt counter.
     *
     * @return Zend_Db_Table_Row_Abstract|null
     */
    public function redeem($challengeId, $plainCode)
    {
        $row = $this->find($challengeId)->current();
        if (!$row
            || $row->consumed_at !== null                       // already used
            || strtotime($row->expires_at) < time()             // expired
            || (int) $row->attempts >= self::MAX_ATTEMPTS) {     // locked out
            return null;
        }

        // Timing-safe comparison; a mismatch costs an attempt.
        if (!hash_equals((string) $row->code_hash, $this->hashCode($plainCode))) {
            $this->update(
                array('attempts' => (int) $row->attempts + 1),
                $this->getAdapter()->quoteInto('challenge_id = ?', $challengeId)
            );
            return null;
        }

        $this->update(
            array('consumed_at' => $this->_now()),
            $this->getAdapter()->quoteInto('challenge_id = ?', $challengeId)
        );
        return $row;
    }

    /**
     * Delete expired/consumed challenges. Call periodically (cron / bin/tiger).
     * Hard delete — dead challenges have no audit value.
     *
     * @return int rows removed
     */
    public function purgeExpired()
    {
        return $this->delete(
            $this->getAdapter()->quoteInto('expires_at < ?', date('Y-m-d H:i:s'))
        );
    }

    /** SHA-256 of the code. Fine for short-lived, attempt-limited, single-use codes. */
    private function hashCode($plainCode)
    {
        return hash('sha256', $plainCode);
    }
}
