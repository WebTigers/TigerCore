<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Login — the append-only authentication audit log (see migration 0011).
 *
 * Extends Tiger_Model_Table for the v7 PK + created_at stamp (the table has no
 * updated/deleted/actor columns, so those are simply no-ops). Rows are written by
 * Tiger_Service_Authentication on every attempt and never updated. The failure/IP
 * counters are the substrate for future rate-limiting; the platform records, an app
 * (or a later layer) decides policy.
 *
 * @api
 */
class Tiger_Model_Login extends Tiger_Model_Table
{
    protected $_name    = 'login';
    protected $_primary = 'login_id';

    const RESULT_SUCCESS = 'success';
    const RESULT_FAILURE = 'failure';
    const RESULT_LOCKED  = 'locked';

    /**
     * Record one login attempt.
     *
     * @param  array $data user_id/org_id/identifier/method/result/ip_address/user_agent/fingerprint
     * @return string login_id
     */
    public function record(array $data)
    {
        return $this->insert([
            'user_id'     => isset($data['user_id'])     ? $data['user_id']     : null,
            'org_id'      => isset($data['org_id'])      ? $data['org_id']      : null,
            'identifier'  => isset($data['identifier'])  ? $data['identifier']  : null,
            'method'      => isset($data['method'])      ? $data['method']      : 'password',
            'result'      => isset($data['result'])      ? $data['result']      : self::RESULT_FAILURE,
            'ip_address'  => isset($data['ip_address'])  ? $data['ip_address']  : null,
            'user_agent'  => isset($data['user_agent'])  ? substr((string) $data['user_agent'], 0, 255) : null,
            'fingerprint' => isset($data['fingerprint']) ? $data['fingerprint'] : null,
        ]);
    }

    /**
     * Fetch a user's login history, newest first (for a "recent sign-in activity" view).
     *
     * @param  string $userId the user id
     * @param  int    $limit  max rows to return
     * @return Zend_Db_Table_Rowset_Abstract the login rows, newest first
     */
    public function recentForUser($userId, $limit = 20)
    {
        return $this->fetchAll(
            $this->select()->where('user_id = ?', $userId)->order('created_at DESC'),
            null, (int) $limit
        );
    }

    /**
     * Count recent FAILED/LOCKED attempts for an identifier (email/phone) — for rate-limiting.
     *
     * @param  string $identifier   the login identifier (email/phone)
     * @param  int    $sinceSeconds the look-back window in seconds
     * @return int    the count of failed/locked attempts
     */
    public function recentFailuresForIdentifier($identifier, $sinceSeconds = 900)
    {
        return $this->_countFailuresSince('identifier = ?', $identifier, $sinceSeconds);
    }

    /**
     * Count recent FAILED/LOCKED attempts from an IP — for distributed brute-force detection.
     *
     * @param  string $ip           the client IP address
     * @param  int    $sinceSeconds the look-back window in seconds
     * @return int    the count of failed/locked attempts
     */
    public function recentFailuresFromIp($ip, $sinceSeconds = 900)
    {
        return $this->_countFailuresSince('ip_address = ?', $ip, $sinceSeconds);
    }

    /**
     * The most-targeted accounts by failed sign-ins over a window — the "who is being brute-forced"
     * view (a dashboard/anomaly signal). `existing` is true when the identifier ever matched a real user
     * (the log stores user_id = NULL for a miss), so you can tell a spray at real accounts from noise.
     *
     * @param  int $sinceSeconds look-back window (default 7 days)
     * @param  int $limit        max rows
     * @return array<int,array{identifier:string, attempts:int, existing:bool}>
     */
    public function topFailures($sinceSeconds = 604800, $limit = 5)
    {
        $db    = $this->getAdapter();
        $since = date('Y-m-d H:i:s', time() - max(60, (int) $sinceSeconds));
        $rows  = $db->fetchAll(
            $db->select()
                ->from($this->_name, [
                    'identifier',
                    'attempts' => new Zend_Db_Expr('COUNT(*)'),
                    'existing' => new Zend_Db_Expr('MAX(user_id IS NOT NULL)'),
                ])
                ->where('result <> ?', self::RESULT_SUCCESS)
                ->where('created_at >= ?', $since)
                ->where('identifier IS NOT NULL')
                ->where("identifier <> ''")
                ->group('identifier')
                ->order(new Zend_Db_Expr('COUNT(*) DESC'))
                ->limit(max(1, (int) $limit))
        );
        return array_map(function ($r) {
            return ['identifier' => (string) $r['identifier'], 'attempts' => (int) $r['attempts'], 'existing' => (bool) $r['existing']];
        }, $rows);
    }

    /**
     * Retention: delete log rows older than N days. Call on a schedule (GDPR).
     *
     * @param  int $days the age threshold in days
     * @return int rows removed
     */
    public function purgeOlderThan($days)
    {
        $cutoff = date('Y-m-d H:i:s', time() - ((int) $days * 86400));
        return (int) $this->delete($this->getAdapter()->quoteInto('created_at < ?', $cutoff));
    }

    private function _countFailuresSince($whereCol, $value, $sinceSeconds)
    {
        $since = date('Y-m-d H:i:s', time() - (int) $sinceSeconds);
        $row = $this->fetchRow(
            $this->select()->from($this->_name, ['c' => 'COUNT(*)'])
                ->where($whereCol, $value)
                ->where('result <> ?', self::RESULT_SUCCESS)
                ->where('created_at >= ?', $since)
        );
        return $row ? (int) $row->c : 0;
    }
}
