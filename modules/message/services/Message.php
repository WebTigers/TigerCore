<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Message_Service_Message — the `/api` surface for the inbox (TIGER-114).
 *
 * Everything a signed-in user does with messages goes through here: read the inbox, open one, send
 * one, file or delete their copy, block a sender. The inbox screen drives this same surface, so an
 * agent and a person see the same rules.
 *
 * THE RULES, all enforced HERE and not in a screen:
 *   - Everything is scoped to the caller's org. A recipient must be an active member of it.
 *   - `send` is allowed to an admin always, and to anyone else only when person-to-person messaging
 *     is enabled for the install (Tiger_Message::CFG_USER_TO_USER). Admins telling members things is
 *     the second reason this module exists; members chatting is the optional one.
 *   - Blocks are applied per recipient at send time, silently: a blocked sender's message is dropped
 *     for that recipient and the send still reports success. Telling them turns a mute into a
 *     confrontation. System messages never consult blocks.
 *   - You cannot block an admin or higher IN THIS ORG. Role lives on org_user, not user, so the
 *     question is asked of the membership, not the person.
 *   - Read / archive / delete act on the CALLER'S copy only. Deleting never touches the message row
 *     or anyone else's copy.
 */
class Message_Service_Message extends Tiger_Service_Service
{
    const PAGE_SIZE      = 50;
    const MAX_RECIPIENTS = 50;

    /* ---- reading ------------------------------------------------------------------------- */

    /**
     * The inbox (default), the archive (`view=archived`) or what I sent (`view=sent`).
     *
     * @param  array $params view?, page?
     */
    public function list(array $params): void
    {
        if (!$this->_signedIn()) { return; }
        $view   = in_array($params['view'] ?? '', ['archived', 'sent'], true) ? $params['view'] : 'inbox';
        $page   = max(1, (int) ($params['page'] ?? 1));
        $offset = ($page - 1) * self::PAGE_SIZE;

        $rows = $view === 'sent'
            ? (new Tiger_Model_Message())->getSentBy($this->_user_id, self::PAGE_SIZE, $offset)
            : (new Tiger_Model_MessageRecipient())->getInbox($this->_user_id, $view === 'archived', self::PAGE_SIZE, $offset);

        $this->_success([
            'view'     => $view,
            'page'     => $page,
            'messages' => array_map([$this, '_summary'], $rows),
            'unread'   => (new Tiger_Model_MessageRecipient())->countUnread($this->_user_id),
        ]);
    }

    /** The unread count alone — what the header bell polls. */
    public function unreadCount(array $params): void
    {
        if (!$this->_signedIn()) { return; }
        $recipients = new Tiger_Model_MessageRecipient();
        $this->_success([
            'unread' => $recipients->countUnread($this->_user_id),
            'recent' => array_map([$this, '_summary'], $recipients->getRecentUnread($this->_user_id, 5)),
        ]);
    }

    /**
     * One message. Opening it marks the caller's copy read.
     *
     * Only a recipient or the sender may read it — being in the same org is not enough.
     */
    public function get(array $params): void
    {
        if (!$this->_signedIn()) { return; }
        $id      = trim((string) ($params['message_id'] ?? ''));
        $message = $id !== '' ? (new Tiger_Model_Message())->getMessage($id) : null;
        if (!$message) { $this->_error('message.error.not_found'); return; }

        $recipients = new Tiger_Model_MessageRecipient();
        $copy       = $recipients->getCopy($id, $this->_user_id);
        $isSender   = (string) $message['sender_user_id'] === (string) $this->_user_id && $this->_user_id !== '';
        if (!$copy && !$isSender) { $this->_error('message.error.not_found'); return; }   // not "forbidden": existence is private too

        if ($copy && $copy->read_at === null) {
            $recipients->update(['read_at' => gmdate('Y-m-d H:i:s')], $recipients->getAdapter()->quoteInto('message_recipient_id = ?', $copy->message_recipient_id));
        }

        $out = $this->_summary($message + ($copy ? $copy->toArray() : []));
        $out['body'] = (string) $message['body'];
        if ($isSender) {
            $out['recipients'] = $recipients->getRecipientsOf($id);
        }
        $this->_success(['message' => $out]);
    }

    /* ---- sending ------------------------------------------------------------------------- */

    /**
     * Send a message to one or more members of my org.
     *
     * @param  array $params recipients (array of user_id), subject, body, parent_id?
     */
    public function send(array $params): void
    {
        if (!$this->_signedIn()) { return; }
        if (!$this->_maySend()) { $this->_error('message.error.sending_disabled'); return; }

        $form = new Message_Form_Compose();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        $v = $form->getValues();

        $wanted = array_values(array_unique(array_filter(array_map('strval', (array) ($params['recipients'] ?? [])))));
        if (!$wanted) { $this->_error('message.error.no_recipients'); return; }
        if (count($wanted) > self::MAX_RECIPIENTS) { $this->_error('message.error.too_many_recipients'); return; }

        // Every recipient must be an active member of MY org — and blocks are applied here, silently.
        $memberships = new Tiger_Model_OrgUser();
        $blocks      = new Tiger_Model_MessageBlock();
        $deliverTo   = [];
        foreach ($wanted as $uid) {
            if ($uid === (string) $this->_user_id) { continue; }                             // not to yourself
            if (!$memberships->activeMembership($this->_org_id, $uid)) { $this->_error('message.error.unknown_recipient'); return; }
            if ($blocks->isBlocked($this->_org_id, $uid, $this->_user_id)) { continue; }    // silently dropped
            $deliverTo[] = $uid;
        }

        $parentId = trim((string) ($params['parent_id'] ?? ''));
        if ($parentId !== '' && !$this->_mayReply($parentId)) { $this->_error('message.error.not_found'); return; }

        try {
            // Everyone was blocked: still "sent", by design. The sender learns nothing.
            $messageId = $deliverTo
                ? Tiger_Message::_deliver($this->_org_id, $this->_user_id, Tiger_Model_Message::KIND_USER, $v['subject'], $v['body'], $deliverTo, $parentId ?: null)
                : null;
            $this->_success(['message_id' => $messageId], 'message.sent');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /** Members of my org matching a term — the compose screen's recipient picker. */
    public function recipients(array $params): void
    {
        if (!$this->_signedIn()) { return; }
        if (!$this->_maySend()) { $this->_error('message.error.sending_disabled'); return; }
        $rows = (new Tiger_Model_OrgUser())->searchMembers($this->_org_id, (string) ($params['q'] ?? ''), 10);
        $this->_success(['recipients' => array_values(array_filter($rows, fn ($r) => (string) $r['user_id'] !== (string) $this->_user_id))]);
    }

    /* ---- my copy ------------------------------------------------------------------------- */

    public function markRead(array $params): void   { $this->_stamp($params, 'read_at', gmdate('Y-m-d H:i:s'), 'message.marked_read'); }
    public function markUnread(array $params): void { $this->_stamp($params, 'read_at', null, 'message.marked_unread'); }
    public function archive(array $params): void    { $this->_stamp($params, 'archived_at', gmdate('Y-m-d H:i:s'), 'message.archived'); }
    public function unarchive(array $params): void  { $this->_stamp($params, 'archived_at', null, 'message.unarchived'); }

    /** Delete MY copy. The message and everyone else's copies are untouched. */
    public function delete(array $params): void
    {
        if (!$this->_signedIn()) { return; }
        $recipients = new Tiger_Model_MessageRecipient();
        $copy = $recipients->getCopy(trim((string) ($params['message_id'] ?? '')), $this->_user_id);
        if (!$copy) { $this->_error('message.error.not_found'); return; }
        $recipients->softDelete($recipients->getAdapter()->quoteInto('message_recipient_id = ?', $copy->message_recipient_id));
        $this->_success([], 'message.deleted');
    }

    /* ---- blocking ------------------------------------------------------------------------ */

    /** Stop receiving from a member. Not an admin or higher, and not yourself. */
    public function block(array $params): void
    {
        if (!$this->_signedIn()) { return; }
        $target = trim((string) ($params['user_id'] ?? ''));
        if ($target === '' || $target === (string) $this->_user_id) { $this->_error('message.error.cannot_block'); return; }

        $memberships = new Tiger_Model_OrgUser();
        $membership  = $memberships->activeMembership($this->_org_id, $target);
        if (!$membership) { $this->_error('message.error.unknown_recipient'); return; }
        if (Tiger_Message::isAtLeastAdmin($membership->role)) { $this->_error('message.error.cannot_block_admin'); return; }

        $blocks = new Tiger_Model_MessageBlock();
        if (!$blocks->getBlock($this->_org_id, $this->_user_id, $target)) {
            $blocks->insert(['org_id' => $this->_org_id, 'user_id' => $this->_user_id, 'blocked_user_id' => $target]);
        }
        $this->_success([], 'message.blocked');
    }

    public function unblock(array $params): void
    {
        if (!$this->_signedIn()) { return; }
        $blocks = new Tiger_Model_MessageBlock();
        $row = $blocks->getBlock($this->_org_id, $this->_user_id, trim((string) ($params['user_id'] ?? '')));
        if ($row) { $blocks->softDelete($blocks->getAdapter()->quoteInto('message_block_id = ?', $row->message_block_id)); }
        $this->_success([], 'message.unblocked');
    }

    public function blocked(array $params): void
    {
        if (!$this->_signedIn()) { return; }
        $this->_success(['blocked' => (new Tiger_Model_MessageBlock())->getBlockedBy($this->_org_id, $this->_user_id)]);
    }

    /* ---- helpers ------------------------------------------------------------------------- */

    /** Signed in with an org, or an error and false. */
    protected function _signedIn()
    {
        if ((string) $this->_user_id === '' || (string) $this->_org_id === '') { $this->_error('core.api.error.not_allowed'); return false; }
        return true;
    }

    /**
     * Admins may always compose; everyone else only when the install has enabled it.
     *
     * NOT _isAdmin(): that asks "is this role allowed on this service", and every signed-in user is —
     * that is how they read their inbox. The question here is about the ROLE itself, answered the same
     * way the controller answers it, so the Compose button and the API can never disagree.
     */
    protected function _maySend()
    {
        $identity = Zend_Auth::getInstance()->getIdentity();
        return Tiger_Message::isAtLeastAdmin($identity->role ?? null) || Tiger_Message::isUserToUserEnabled();
    }

    /** A reply must be to a message I actually received or sent. */
    protected function _mayReply($parentId)
    {
        $parent = (new Tiger_Model_Message())->getMessage($parentId);
        if (!$parent) { return false; }
        if ((string) $parent['sender_user_id'] === (string) $this->_user_id) { return true; }
        return (bool) (new Tiger_Model_MessageRecipient())->getCopy($parentId, $this->_user_id);
    }

    /** Set or clear a timestamp on MY copy. */
    protected function _stamp(array $params, $column, $value, $okMessage)
    {
        if (!$this->_signedIn()) { return; }
        $recipients = new Tiger_Model_MessageRecipient();
        $copy = $recipients->getCopy(trim((string) ($params['message_id'] ?? '')), $this->_user_id);
        if (!$copy) { $this->_error('message.error.not_found'); return; }
        $recipients->update([$column => $value], $recipients->getAdapter()->quoteInto('message_recipient_id = ?', $copy->message_recipient_id));
        $this->_success([], $okMessage);
    }

    /** The list-row shape. Body is trimmed to a preview; get() returns it whole. */
    protected function _summary(array $row)
    {
        $body = (string) ($row['body'] ?? '');
        return [
            'message_id'  => (string) $row['message_id'],
            'kind'        => (string) ($row['kind'] ?? Tiger_Model_Message::KIND_USER),
            'subject'     => (string) ($row['subject'] ?? ''),
            'preview'     => mb_substr(trim(preg_replace('/\s+/', ' ', strip_tags($body))), 0, 140),
            'sender_id'   => $row['sender_user_id'] !== null ? (string) $row['sender_user_id'] : null,
            'sender_name' => $row['sender_user_id'] === null ? null : (string) ($row['sender_name'] ?? ''),
            'parent_id'   => $row['parent_id'] ?? null,
            'read'        => array_key_exists('read_at', $row) ? $row['read_at'] !== null : null,
            'archived'    => array_key_exists('archived_at', $row) ? $row['archived_at'] !== null : null,
            'created_at'  => (string) $row['created_at'],
        ];
    }
}
