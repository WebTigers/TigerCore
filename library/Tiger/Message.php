<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Message — the platform's way of telling people things (TIGER-114).
 *
 * The facade other modules call. A module that needs to tell an operator something — a backup
 * failed, a payment webhook is misbehaving, a module update is waiting — does not construct rows; it
 * calls `Tiger_Message::toAdmins()` and the message lands in every admin's inbox with the header bell
 * lit. That is the primary reason this module exists. Person-to-person messaging is the optional
 * second surface, and is OFF until an operator enables it — most sites are not a social network and
 * should not silently become one.
 *
 *   Tiger_Message::toAdmins($orgId, 'Backup failed', 'The nightly backup at 02:00 did not complete.');
 *   Tiger_Message::toUser($orgId, $userId, 'Welcome', '…');            // still system: no blocks apply
 *   Tiger_Message::isUserToUserEnabled()                                // the config flag
 *   Tiger_Message::isAtLeastAdmin($role)                                // the blocking rule's question
 *
 * Every send is fail-soft. A message is never the reason a backup job or a webhook handler throws.
 *
 * @api
 * @since 1.6.0
 */
class Tiger_Message
{
    /** Person-to-person messaging. Off by default; `1` enables it for the install. */
    const CFG_USER_TO_USER = 'tiger.message.user_to_user';

    /** Roles at or above this cannot be blocked, and receive `toAdmins()`. */
    const ADMIN_ROLE = 'admin';

    /**
     * Send a SYSTEM message to every active admin of an org.
     *
     * Admin = holds `admin` or a role that inherits from it in this org. Ignores blocks — an operator
     * must not be able to make the platform unable to reach them.
     *
     * @param  string $orgId
     * @param  string $subject
     * @param  string $body
     * @return string|null the message_id, or null if nothing could be sent
     */
    public static function toAdmins($orgId, $subject, $body)
    {
        try {
            $admins = self::_adminIdsOf($orgId);
            if (!$admins) { return null; }
            return self::_deliver($orgId, null, Tiger_Model_Message::KIND_SYSTEM, $subject, $body, $admins, null);
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Send a SYSTEM message to one user. Still `system`: no block applies, and no sender is shown.
     *
     * @return string|null the message_id
     */
    public static function toUser($orgId, $userId, $subject, $body)
    {
        try {
            if ((string) $userId === '') { return null; }
            return self::_deliver($orgId, null, Tiger_Model_Message::KIND_SYSTEM, $subject, $body, [(string) $userId], null);
        } catch (Throwable $e) {
            return null;
        }
    }

    /** Is person-to-person messaging enabled on this install? */
    public static function isUserToUserEnabled()
    {
        return (string) self::_config(self::CFG_USER_TO_USER, '0') === '1';
    }

    /**
     * Is this role admin-or-higher? The blocking rule's question, answered from the live ACL's
     * inheritance chain rather than a hard-coded list, so a site that adds a role above admin gets
     * the right answer without touching this.
     *
     * @param  string|null $role
     * @return bool
     */
    public static function isAtLeastAdmin($role)
    {
        $role = (string) $role;
        if ($role === '') { return false; }
        if ($role === self::ADMIN_ROLE) { return true; }
        if (!Zend_Registry::isRegistered('Zend_Acl')) { return false; }
        try {
            $acl = Zend_Registry::get('Zend_Acl');
            return $acl->hasRole($role) && $acl->hasRole(self::ADMIN_ROLE) && $acl->inheritsRole($role, self::ADMIN_ROLE);
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Write one message and its recipient rows, atomically.
     *
     * Shared by the facade (system) and the service (user). Recipients are deduplicated, and a
     * sender is never their own recipient.
     *
     * @param  string      $orgId
     * @param  string|null $senderUserId null = the app
     * @param  string      $kind         KIND_SYSTEM | KIND_USER
     * @param  string      $subject
     * @param  string      $body
     * @param  string[]    $recipientIds
     * @param  string|null $parentId
     * @return string the message_id
     * @internal called by Message_Service_Message; not a public contract
     */
    public static function _deliver($orgId, $senderUserId, $kind, $subject, $body, array $recipientIds, $parentId)
    {
        $recipientIds = array_values(array_unique(array_filter(array_map('strval', $recipientIds), static function ($id) use ($senderUserId) {
            return $id !== '' && $id !== (string) $senderUserId;
        })));
        if (!$recipientIds) {
            throw new RuntimeException('a message needs at least one recipient');
        }

        $messages   = new Tiger_Model_Message();
        $recipients = new Tiger_Model_MessageRecipient();
        $db         = $messages->getAdapter();

        $db->beginTransaction();
        try {
            $messageId = (string) $messages->insert([
                'org_id'         => (string) $orgId,
                'parent_id'      => $parentId !== null && $parentId !== '' ? (string) $parentId : null,
                'sender_user_id' => $senderUserId !== null && $senderUserId !== '' ? (string) $senderUserId : null,
                'kind'           => $kind === Tiger_Model_Message::KIND_SYSTEM ? $kind : Tiger_Model_Message::KIND_USER,
                'subject'        => mb_substr(trim((string) $subject), 0, Tiger_Model_Message::MAX_SUBJECT),
                'body'           => mb_substr((string) $body, 0, Tiger_Model_Message::MAX_BODY),
            ]);
            foreach ($recipientIds as $uid) {
                $recipients->insert(['message_id' => $messageId, 'user_id' => $uid]);
            }
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
        return $messageId;
    }

    /** User ids holding admin-or-higher in an org. */
    protected static function _adminIdsOf($orgId)
    {
        $out = [];
        foreach ((new Tiger_Model_OrgUser())->usersInOrg((string) $orgId) as $m) {
            $m = is_array($m) ? $m : $m->toArray();
            if (self::isAtLeastAdmin($m['role'] ?? null)) {
                $out[] = (string) $m['user_id'];
            }
        }
        return $out;
    }

    /** Read a dotted config key from the registry, or the default. */
    protected static function _config($key, $default = '')
    {
        if (!Zend_Registry::isRegistered('Zend_Config')) { return $default; }
        $node = Zend_Registry::get('Zend_Config');
        foreach (explode('.', $key) as $seg) {
            if (!($node instanceof Zend_Config)) { return $default; }
            $node = $node->get($seg);
        }
        return is_scalar($node) ? (string) $node : $default;
    }
}
