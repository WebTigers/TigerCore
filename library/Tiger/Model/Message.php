<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Model_Message — the message body store (migration 0047, TIGER-114).
 *
 * One row per message regardless of recipient count; who received it and their read state is
 * Tiger_Model_MessageRecipient. `sender_user_id` NULL is THE APP. `kind` separates the two surfaces
 * that obey different rules: `system` (app → operators; ignores blocks; always on) and `user`
 * (person → person; honours blocks; off unless enabled).
 *
 * @api
 * @since 1.6.0
 */
class Tiger_Model_Message extends Tiger_Model_Table
{
    protected $_name    = 'message';
    protected $_primary = 'message_id';

    const KIND_SYSTEM = 'system';
    const KIND_USER   = 'user';

    const MAX_SUBJECT = 191;
    const MAX_BODY    = 20000;

    /**
     * One message, with its sender's display name joined on — or null.
     *
     * The caller must still check the reader is a recipient (or the sender); this finder does not
     * know who is asking. That check is the service's, where the identity is.
     *
     * @param  string $messageId
     * @return array|null
     */
    public function getMessage($messageId)
    {
        // select(), not activeSelect(): the base's deleted-filter names the table unaliased, and this
        // query aliases it as `m`, so the alias-qualified filter is written here instead.
        $row = $this->fetchRow(
            $this->select()
                ->setIntegrityCheck(false)
                ->from(['m' => $this->_name])
                ->joinLeft(['u' => 'user'], 'u.user_id = m.sender_user_id', ['sender_name' => new Zend_Db_Expr('COALESCE(u.username, u.email)')])
                ->where('m.message_id = ?', (string) $messageId)
                ->where('m.deleted = 0')
        );
        return $row ? $row->toArray() : null;
    }

    /**
     * Messages a user SENT, newest first — the "Sent" view.
     *
     * @param  string $userId
     * @param  int    $limit
     * @param  int    $offset
     * @return array<int,array>
     */
    public function getSentBy($userId, $limit = 50, $offset = 0)
    {
        return $this->fetchAll(
            $this->activeSelect()
                ->where('sender_user_id = ?', (string) $userId)
                ->order('created_at DESC')
                ->limit((int) $limit, (int) $offset)
        )->toArray();
    }
}
