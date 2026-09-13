<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Model_MessageRecipient — who received a message, and what they did with it (0048).
 *
 * Every inbox read goes through here, joined to the body. `deleted` on THIS row is the recipient
 * deleting their own copy — nothing else is touched. The unread count is the header bell's query
 * on every admin page, and is a pure index hit on (`user_id`, `read_at`, `deleted`).
 *
 * @api
 * @since 1.6.0
 */
class Tiger_Model_MessageRecipient extends Tiger_Model_Table
{
    protected $_name    = 'message_recipient';
    protected $_primary = 'message_recipient_id';

    /**
     * A user's inbox: their undeleted, unarchived copies joined to the message and sender, newest first.
     *
     * @param  string $userId
     * @param  bool   $archived true = the archive view instead of the inbox
     * @param  int    $limit
     * @param  int    $offset
     * @return array<int,array>
     */
    public function getInbox($userId, $archived = false, $limit = 50, $offset = 0)
    {
        $select = $this->_inboxSelect($userId)
            ->where($archived ? 'r.archived_at IS NOT NULL' : 'r.archived_at IS NULL')
            ->order('m.created_at DESC')
            ->limit((int) $limit, (int) $offset);
        return $this->fetchAll($select)->toArray();
    }

    /**
     * The most recent UNREAD messages — what the header bell's dropdown shows.
     *
     * @param  string $userId
     * @param  int    $limit
     * @return array<int,array>
     */
    public function getRecentUnread($userId, $limit = 5)
    {
        return $this->fetchAll(
            $this->_inboxSelect($userId)
                ->where('r.read_at IS NULL')
                ->where('r.archived_at IS NULL')
                ->order('m.created_at DESC')
                ->limit((int) $limit)
        )->toArray();
    }

    /**
     * How many unread, undeleted messages a user has — the badge number.
     *
     * Archived messages still count if unread: archiving is filing, not reading.
     *
     * @param  string $userId
     * @return int
     */
    public function countUnread($userId)
    {
        if ((string) $userId === '') { return 0; }
        $select = $this->select()
            ->from($this->_name, ['n' => 'COUNT(*)'])
            ->where('user_id = ?', (string) $userId)
            ->where('read_at IS NULL')
            ->where('deleted = 0');
        return (int) $this->getAdapter()->fetchOne($select);
    }

    /**
     * One user's copy of one message, or null — the row every per-recipient action operates on.
     *
     * @param  string $messageId
     * @param  string $userId
     * @return Zend_Db_Table_Row_Abstract|null
     */
    public function getCopy($messageId, $userId)
    {
        return $this->fetchRow(
            $this->activeSelect()
                ->where('message_id = ?', (string) $messageId)
                ->where('user_id = ?', (string) $userId)
        );
    }

    /**
     * Every recipient of a message (for the sender's "to:" line).
     *
     * @param  string $messageId
     * @return array<int,array> rows with user_id, username, read_at
     */
    public function getRecipientsOf($messageId)
    {
        return $this->fetchAll(
            $this->select()
                ->setIntegrityCheck(false)
                ->from(['r' => $this->_name], ['user_id', 'read_at'])
                ->joinLeft(['u' => 'user'], 'u.user_id = r.user_id', ['username' => new Zend_Db_Expr('COALESCE(u.username, u.email)')])
                ->where('r.message_id = ?', (string) $messageId)
                ->order('u.username ASC')
        )->toArray();
    }

    /** The inbox join, shared by the list finders. */
    protected function _inboxSelect($userId)
    {
        return $this->select()
            ->setIntegrityCheck(false)
            ->from(['r' => $this->_name], ['message_recipient_id', 'read_at', 'archived_at'])
            ->join(['m' => 'message'], 'm.message_id = r.message_id',
                ['message_id', 'org_id', 'parent_id', 'sender_user_id', 'kind', 'subject', 'body', 'created_at'])
            ->joinLeft(['u' => 'user'], 'u.user_id = m.sender_user_id', ['sender_name' => new Zend_Db_Expr('COALESCE(u.username, u.email)')])
            ->where('r.user_id = ?', (string) $userId)
            ->where('r.deleted = 0')
            ->where('m.deleted = 0');
    }
}
