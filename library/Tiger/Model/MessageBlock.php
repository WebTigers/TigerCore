<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Model_MessageBlock — one user muting another, within an org (migration 0049).
 *
 * Read at SEND time by the service: a blocked sender's message is silently dropped for that
 * recipient. Never consulted for `system` messages.
 *
 * @api
 * @since 1.6.0
 */
class Tiger_Model_MessageBlock extends Tiger_Model_Table
{
    protected $_name    = 'message_block';
    protected $_primary = 'message_block_id';

    /**
     * Does `$userId` block `$senderId` in this org?
     *
     * @param  string $orgId
     * @param  string $userId   the potential recipient
     * @param  string $senderId the potential sender
     * @return bool
     */
    public function isBlocked($orgId, $userId, $senderId)
    {
        if ((string) $userId === '' || (string) $senderId === '') { return false; }
        return (bool) $this->fetchRow(
            $this->activeSelect()
                ->where('org_id = ?', (string) $orgId)
                ->where('user_id = ?', (string) $userId)
                ->where('blocked_user_id = ?', (string) $senderId)
        );
    }

    /**
     * Everyone this user has blocked in this org, with names — the "Blocked" screen.
     *
     * @param  string $orgId
     * @param  string $userId
     * @return array<int,array>
     */
    public function getBlockedBy($orgId, $userId)
    {
        return $this->fetchAll(
            $this->select()
                ->setIntegrityCheck(false)
                ->from(['b' => $this->_name], ['message_block_id', 'blocked_user_id', 'created_at'])
                ->joinLeft(['u' => 'user'], 'u.user_id = b.blocked_user_id', ['username' => new Zend_Db_Expr('COALESCE(u.username, u.email)')])
                ->where('b.org_id = ?', (string) $orgId)
                ->where('b.user_id = ?', (string) $userId)
                ->where('b.deleted = 0')
                ->order('u.username ASC')
        )->toArray();
    }

    /**
     * The block row for a pair, if any (to unblock, or to avoid inserting twice).
     *
     * @return Zend_Db_Table_Row_Abstract|null
     */
    public function getBlock($orgId, $userId, $blockedUserId)
    {
        return $this->fetchRow(
            $this->activeSelect()
                ->where('org_id = ?', (string) $orgId)
                ->where('user_id = ?', (string) $userId)
                ->where('blocked_user_id = ?', (string) $blockedUserId)
        );
    }
}
