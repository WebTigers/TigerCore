<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Newsletter_Model_Subscriber — the newsletter opt-in store (migration 0051).
 *
 * A subscriber is a consent-first row: `status` is `pending` until the person confirms, `confirmed`
 * once they do, `unsubscribed` after they opt out. Only `confirmed` rows are ever a legitimate
 * audience — that is the whole point of double opt-in, and it is enforced HERE (confirmed()) so no
 * consumer can accidentally email a pending or opted-out address.
 *
 * Reads exclude soft-deleted rows via activeSelect(); the audience finders additionally filter to
 * confirmed + the caller's org.
 *
 * @api
 * @since 1.9.0
 */
class Newsletter_Model_Subscriber extends Tiger_Model_Table
{
    protected $_name    = 'newsletter_subscriber';
    protected $_primary = 'newsletter_subscriber_id';

    const STATUS_PENDING      = 'pending';
    const STATUS_CONFIRMED    = 'confirmed';
    const STATUS_UNSUBSCRIBED = 'unsubscribed';

    /** The statuses a row may hold. */
    const STATUSES = [self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_UNSUBSCRIBED];

    /**
     * A subscriber row for (org, email), or null. The org scoping is what keeps one tenant's list
     * from colliding with another's on the same install.
     *
     * @param  string $orgId the tenant
     * @param  string $email lowercased address
     * @return array|null    the row
     */
    public function forEmail($orgId, $email)
    {
        $row = $this->fetchRow(
            $this->activeSelect()
                ->where('org_id = ?', (string) $orgId)
                ->where('email = ?', (string) $email)
        );
        return $row ? $row->toArray() : null;
    }

    /**
     * A subscriber by opaque token (the confirm / unsubscribe handle), or null.
     *
     * @param  string $token
     * @return array|null
     */
    public function byToken($token)
    {
        if ((string) $token === '') { return null; }
        $row = $this->fetchRow($this->activeSelect()->where('token = ?', (string) $token));
        return $row ? $row->toArray() : null;
    }

    /**
     * The confirmed subscribers for an org — the audience a consumer (TigerList) may target. Pending
     * and unsubscribed rows are never returned: an audience is opt-ins only.
     *
     * @param  string $orgId the tenant
     * @return array<int,array> the confirmed rows
     */
    public function confirmed($orgId)
    {
        return $this->fetchAll(
            $this->_orgScoped($this->activeSelect(), $orgId)
                ->where('status = ?', self::STATUS_CONFIRMED)
                ->order('created_at ASC')
        )->toArray();
    }

    /**
     * How many confirmed subscribers an org has — the segment count.
     *
     * @param  string $orgId
     * @return int
     */
    public function confirmedCount($orgId)
    {
        $db     = $this->getAdapter();
        $select = $db->select()
            ->from($this->_name, ['n' => new Zend_Db_Expr('COUNT(*)')])
            ->where('deleted = ?', 0)
            ->where('status = ?', self::STATUS_CONFIRMED);
        $this->_orgScoped($select, $orgId);
        return (int) $db->fetchOne($select);
    }

    /**
     * Rows for the admin grid, newest first, optionally filtered by status.
     *
     * @param  string $orgId  the tenant
     * @param  string $status '' for all, else one of STATUSES
     * @param  int    $limit  max rows
     * @return array<int,array>
     */
    public function forOrg($orgId, $status = '', $limit = 500)
    {
        $select = $this->_orgScoped($this->activeSelect(), $orgId)
            ->order('created_at DESC')
            ->limit(max(1, (int) $limit));

        if ((string) $status !== '') {
            $select->where('status = ?', (string) $status);
        }
        return $this->fetchAll($select)->toArray();
    }

    /**
     * Scope a select to a tenant PLUS the global ('') scope.
     *
     * Anonymous visitors on a single-site install resolve to the global org (''), exactly like a
     * shipped CMS page — so an operator acting in their own org must still see those subscribers.
     * Including '' is safe for real multi-site: there the host→org plugin stamps every request
     * (guests included) with its site's org, so '' only ever accumulates single-site rows.
     *
     * @param  Zend_Db_Select $select
     * @param  string         $orgId
     * @return Zend_Db_Select
     */
    protected function _orgScoped($select, $orgId)
    {
        $orgId = (string) $orgId;
        // IN (?) with an array is Zend_Db's portable, safely-quoted multi-value form.
        $scopes = $orgId === '' ? [''] : ['', $orgId];
        return $select->where('org_id IN (?)', $scopes);
    }

    /**
     * Count recent subscribe attempts from an IP — the flood guard read (guests have no user id, so
     * the address is the only handle a rate limit has).
     *
     * @param  string $ip      the address
     * @param  int    $seconds the window
     * @return int             how many came from it
     */
    public function recentCountByIp($ip, $seconds)
    {
        if ((string) $ip === '') { return 0; }
        $db    = $this->getAdapter();
        $since = date('Y-m-d H:i:s', time() - max(1, (int) $seconds));
        return (int) $db->fetchOne(
            $db->select()
                ->from($this->_name, ['n' => new Zend_Db_Expr('COUNT(*)')])
                ->where('ip = ?', (string) $ip)
                ->where('created_at >= ?', $since)
        );
    }
}
