<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * OrgUser — MEMBERSHIP. The most important table in the substrate.
 *
 * A row here says "user U belongs to org O, with role R." It is simultaneously:
 *
 *   1. THE TENANCY BOUNDARY. What stops a user from acting inside an org is the
 *      ABSENCE of an org_user row linking them to it. Cross-tenant denial is
 *      therefore structural — a missing row — not a code check someone can forget
 *      to write. Every tenant-scoped query should be gated on "does an (org_id,
 *      user_id) membership exist?"
 *
 *   2. THE ROLE CARRIER. The role lives HERE, per-membership — not on the user —
 *      so the same user can be `admin` in one org and `viewer` in another. This is
 *      the core of Tiger's multi-tenant ACL (Tiger's key evolution over a single
 *      global role per user). The ACL engine reads the role from the current
 *      org_user row for the acting user + current org.
 *
 * A user has at most ONE membership per org (unique org_id + user_id). `status`
 * distinguishes active / invited / suspended memberships without deleting the row.
 *
 * @api
 */
class Tiger_Model_OrgUser extends Tiger_Model_Table
{
    /** A membership authorizes only in this state (the table also holds `invited` / `suspended`). */
    const STATUS_ACTIVE = 'active';

    protected $_name    = 'org_user';
    protected $_primary = 'org_user_id';

    /**
     * The membership row for a (org, user) pair, or null if none exists.
     *
     * A null return IS the cross-tenant denial signal — callers should treat "no
     * membership" as "not a member of this tenant, deny."
     *
     * @param  string $orgId
     * @param  string $userId
     * @return Zend_Db_Table_Row_Abstract|null
     */
    /**
     * The membership ONLY IF it currently authorizes — status `active`, not soft-deleted.
     *
     * `membership()` deliberately returns invited/suspended rows too, because administration needs to
     * see and edit them. Authorization must not use it: `activeSelect()` filters `deleted` only, so a
     * SUSPENDED membership still returned its role and an already-authenticated session kept admin
     * rights after being suspended — the sessions that mattered were exactly the ones not re-checked.
     * Fresh identity construction always did check `status === 'active'`, so the two disagreed.
     *
     * @param  string $orgId
     * @param  string $userId
     * @return Zend_Db_Table_Row_Abstract|null
     * @see    Tiger_Model_OrgUser::membership() for the administrative lookup that includes inactive rows
     */
    public function activeMembership($orgId, $userId)
    {
        $row = $this->membership($orgId, $userId);
        return ($row && (string) $row->status === self::STATUS_ACTIVE) ? $row : null;
    }

    /**
     * The role a membership currently confers, or null when it confers none.
     *
     * @param  string $orgId
     * @param  string $userId
     * @return string|null
     */
    public function activeRoleOf($orgId, $userId)
    {
        $row = $this->activeMembership($orgId, $userId);
        return $row ? $row->role : null;
    }

    public function membership($orgId, $userId)
    {
        return $this->fetchRow(
            $this->activeSelect()->where('org_id = ?', $orgId)->where('user_id = ?', $userId)
        ) ?: null;
    }

    /**
     * The role a user holds in an org, or null if they are not a member.
     *
     * @param  string $orgId
     * @param  string $userId
     * @return string|null
     */
    public function roleOf($orgId, $userId)
    {
        $row = $this->membership($orgId, $userId);
        return $row ? $row->role : null;
    }

    /**
     * All memberships for a user (i.e. every org they belong to). Useful for an
     * org switcher / "your organizations" list.
     *
     * @param  string $userId
     * @return Zend_Db_Table_Rowset_Abstract
     */
    public function orgsForUser($userId)
    {
        return $this->fetchAll($this->activeSelect()->where('user_id = ?', $userId));
    }

    /**
     * All memberships in an org (i.e. its members). Useful for a team/members list.
     *
     * @param  string $orgId
     * @return Zend_Db_Table_Rowset_Abstract
     */
    public function usersInOrg($orgId)
    {
        return $this->fetchAll($this->activeSelect()->where('org_id = ?', $orgId));
    }

    /**
     * Active members of an org whose name or email matches — a recipient picker's query (TIGER-114).
     *
     * Scoped to ONE org on purpose: a picker that could complete names across tenants is a directory
     * leak. Returns display fields only, never the row wholesale.
     *
     * @param  string $orgId
     * @param  string $term   what the user typed; empty returns the first page alphabetically
     * @param  int    $limit
     * @return array<int,array{user_id:string,name:string,role:string}>
     */
    public function searchMembers($orgId, $term, $limit = 10)
    {
        $select = $this->select()
            ->setIntegrityCheck(false)
            ->from(['ou' => $this->_name], ['user_id', 'role'])
            ->join(['u' => 'user'], 'u.user_id = ou.user_id', ['name' => new Zend_Db_Expr('COALESCE(u.username, u.email)')])
            ->where('ou.org_id = ?', (string) $orgId)
            ->where('ou.status = ?', self::STATUS_ACTIVE)
            ->where('ou.deleted = 0')
            ->where('u.deleted = 0')
            ->order('name ASC')
            ->limit(max(1, min(50, (int) $limit)));

        $term = trim((string) $term);
        if ($term !== '') {
            // Escape LIKE metacharacters so a typed "%" matches a percent sign, not everything.
            $like = $this->getAdapter()->quote('%' . addcslashes($term, '%_\\') . '%');
            $select->where("(u.username LIKE $like OR u.email LIKE $like)");
        }
        return $this->fetchAll($select)->toArray();
    }

    /**
     * A user's live memberships with each org's display name — what the Users admin modal lists.
     *
     * @param  string $userId
     * @return array<int,array{org_id:string,org_name:string,org_slug:string,role:string,status:string}>
     */
    public function membershipsWithOrgs($userId)
    {
        $select = $this->select()
            ->setIntegrityCheck(false)
            ->from(['ou' => $this->_name], ['org_id', 'role', 'status'])
            ->join(['o' => 'org'], 'o.org_id = ou.org_id', ['org_name' => 'name', 'org_slug' => 'slug'])
            ->where('ou.user_id = ?', (string) $userId)
            ->where('ou.deleted = 0')
            ->where('o.deleted = 0')
            ->order('o.name ASC');
        return $this->fetchAll($select)->toArray();
    }

    /**
     * Make $userId a member of $orgId with $role (active), creating or updating the membership.
     *
     * `org_user` carries a plain UNIQUE (org_id, user_id) that a soft-deleted row still occupies, so a
     * membership that was removed is REACTIVATED rather than inserted twice. Returns the membership id.
     *
     * @param  string $orgId
     * @param  string $userId
     * @param  string $role
     * @return string the org_user_id
     */
    public function assign($orgId, $userId, $role)
    {
        $row = $this->fetchRow($this->select()->where('org_id = ?', (string) $orgId)->where('user_id = ?', (string) $userId));
        if ($row) {
            $row->role    = (string) $role;
            $row->status  = self::STATUS_ACTIVE;
            $row->deleted = 0;
            $row->save();
            return (string) $row->org_user_id;
        }
        return (string) $this->insert(['org_id' => (string) $orgId, 'user_id' => (string) $userId,
                                       'role' => (string) $role, 'status' => self::STATUS_ACTIVE]);
    }

    /**
     * Remove $userId from $orgId (soft-delete the membership). A no-op when there is none.
     *
     * @param  string $orgId
     * @param  string $userId
     * @return void
     */
    public function revoke($orgId, $userId)
    {
        $row = $this->membership($orgId, $userId);
        if ($row) {
            $row->deleted = 1;
            $row->save();
        }
    }
}
