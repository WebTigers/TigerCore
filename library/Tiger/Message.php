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
 *   Tiger_Message::maySend($role)                                       // may this role compose? (capability, not a role compare)
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
     * The composing CAPABILITY, as an ACL resource (TIGER-335). "Who is admin-class for messaging"
     * is a privilege (`send`) on this resource in `modules/message/configs/acl.ini`, granted to
     * `admin` (so every role that inherits admin gets it — rename-safe), never a role-string compare.
     * It is NOT the dispatcher gate for `Message_Service_Message::send` (that is the service's own
     * ACL rule); it is the data-driven answer to the three "is this role senior enough" questions —
     * who may compose, who to notify (`toAdmins`), and who cannot be blocked.
     */
    const RESOURCE_COMPOSE = 'Tiger_Message_Compose';

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
     * May this role COMPOSE a message? The capability question, answered as a privilege on
     * RESOURCE_COMPOSE ORed with the person-to-person flag (TIGER-335): admin-class roles hold the
     * `send` grant (so they may always compose), and everyone else may compose only when the install
     * has enabled user-to-user messaging. No role string, no `inheritsRole()` — a renamed or added
     * admin-class role is handled by ACL inheritance. Shared by the service and the controller so the
     * Compose button and the API can never disagree.
     *
     * @param  string|null $role
     * @return bool
     */
    public static function maySend($role)
    {
        return self::_roleAllowed($role, 'send', self::RESOURCE_COMPOSE) || self::isUserToUserEnabled();
    }

    /**
     * Is this role admin-or-higher?
     *
     * @param  string|null $role
     * @return bool
     * @deprecated TIGER-335 — reads the ACL inheritance graph as a seniority ladder (`inheritsRole`
     *   means "inherits admin's RULES", not "is at least as senior"), returns true for `admin` before
     *   the ACL-registered check (so the more-privileged `superadmin`/`developer` fail where `admin`
     *   passes in a partial boot), and conflates a missing identity differently from its service twin.
     *   Use `maySend($role)` for the compose/notify/block questions, or `_roleAllowed()` directly for a
     *   bare capability check. Removed once the message module's own call sites are gone (TIGER-335).
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
     * Does $role hold $privilege on $resource, per the live ACL? The facade's pure-ACL authorization
     * primitive — the static sibling of `Tiger_Service_Service::_roleIsAllowed()`, for a role that may
     * be a third party's (an org member being notified or blocked), not just the caller's. Fails
     * CLOSED: no ACL registered, an empty role, or an ungoverned resource all yield false. No role
     * literal — `Zend_Acl::isAllowed()` answers for any role from the same rule the caller is gated by.
     *
     * @param  string|null $role
     * @param  string      $privilege
     * @param  string      $resource
     * @return bool
     */
    protected static function _roleAllowed($role, $privilege, $resource)
    {
        $role = (string) $role;
        if ($role === '' || !Zend_Registry::isRegistered('Zend_Acl')) { return false; }
        try {
            $acl = Zend_Registry::get('Zend_Acl');
            return $acl->has($resource) && $acl->isAllowed($role, $resource, $privilege);
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

    /**
     * User ids holding the compose capability (admin-class) in an org — who `toAdmins()` reaches.
     *
     * Asks the capability (`send` on RESOURCE_COMPOSE), NOT `maySend()`: the user-to-user flag must
     * not turn every member into a system-notification recipient (TIGER-335).
     */
    protected static function _adminIdsOf($orgId)
    {
        $out = [];
        foreach ((new Tiger_Model_OrgUser())->usersInOrg((string) $orgId) as $m) {
            $m = is_array($m) ? $m : $m->toArray();
            if (self::_roleAllowed($m['role'] ?? null, 'send', self::RESOURCE_COMPOSE)) {
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
