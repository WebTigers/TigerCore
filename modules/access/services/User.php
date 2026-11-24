<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Access_Service_User — /api service for the Users admin (datatable / save / delete).
 *
 * Users are thin identities (email / username / status); the list also summarizes
 * each user's membership (org count + the distinct roles they hold, via org_user).
 * Rows are structured data with per-row ACL flags (can_edit / can_delete) — the
 * client renders controls off them; you can never delete your own account. ACL:
 * admin+ (modules/access/configs/acl.ini).
 *
 * @api
 */
class Access_Service_User extends Tiger_Service_Service
{
    private const STATUSES = ['active', 'suspended'];

    /**
     * DataTables server-side source: user identity + a membership summary.
     *
     * @param  array $params the DataTables request (draw/start/length/search/order) + status filter
     * @apiRequest datatable
     * @apiParam string(active|suspended|deleted) status Only users in this status (deleted = the soft-deleted ones).
     * @return void
     */
    public function datatable(array $params): void
    {
        if (!$this->_isAllowed('datatable')) { $this->_error('core.api.error.not_allowed'); return; }

        $dt = $this->_dtParams($params);

        $data = (new Tiger_Model_User())->datatable([
            'search'   => $dt['search'],
            // 'deleted' lists the soft-deleted users (so one can be opened and restored).
            'status'   => in_array(($params['status'] ?? ''), array_merge(self::STATUSES, ['deleted']), true) ? (string) $params['status'] : '',
            'orderCol' => isset($dt['order'][0]) ? $dt['order'][0]['column'] : -1,
            'orderDir' => isset($dt['order'][0]) ? $dt['order'][0]['dir'] : '',
            'offset'   => $dt['start'],
            'limit'    => $dt['length'],
        ]);

        $canEdit   = $this->_isAllowed('save');
        $canDelete = $this->_isAllowed('delete');

        $rows = [];
        foreach ($data['rows'] as $r) {
            $isSelf = ($r['user_id'] === $this->_user_id);
            // A row whose user holds a role the caller may not manage is not editable here (TIGER-511) —
            // the flag only draws the controls; save/delete re-check against the live memberships.
            $manageable = true;
            foreach (array_filter(array_map('trim', explode(',', (string) ($r['role_names'] ?? '')))) as $heldRole) {
                if (!$this->_mayManageRole($heldRole)) { $manageable = false; break; }
            }
            $rows[] = [
                'user_id'    => $r['user_id'],
                'email'      => $r['email'],
                'username'   => ($r['username'] !== null && $r['username'] !== '') ? $r['username'] : '',
                'roles'      => (string) ($r['role_names'] ?? ''),
                'org_count'  => (int) $r['org_count'],
                'status'     => $r['status'],
                'deleted'    => (int) ($r['deleted'] ?? 0) === 1,
                'created'    => substr((string) $r['created_at'], 0, 10),
                'is_self'    => $isSelf,
                'can_edit'   => $canEdit && $manageable,
                'can_delete' => $canDelete && $manageable && !$isSelf && (int) ($r['deleted'] ?? 0) !== 1,   // never yourself; not twice
            ];
        }

        $this->_dtResponse($dt['draw'], $data['total'], $data['filtered'], $rows);
    }

    /**
     * Create or update a user identity (insert when user_id is empty).
     *
     * @param  array $params the submitted form values + user_id
     * @apiRequest Access_Form_User
     * @return void
     */
    public function save(array $params): void
    {
        if (!$this->_isAllowed('save')) { $this->_error('core.api.error.not_allowed'); return; }

        $form = new Access_Form_User();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        $v = $form->getValues();

        $userId = !empty($params['user_id']) ? (string) $params['user_id'] : null;
        $user   = new Tiger_Model_User();
        $orgUser = new Tiger_Model_OrgUser();
        if ($userId !== null) {
            // An edit may target a soft-deleted user (the Deleted switch restores one), so load deleted rows.
            if (!$user->findById($userId, true)) { $this->_error('access.user.not_found'); return; }
            // TIGER-511: editing a user — and above all setting their password — is acting AS them. The
            // caller must be allowed to manage EVERY role the target holds, or an admin could re-password a
            // superadmin and sign in with it.
            if (!$this->_mayManageUser($userId)) { $this->_error('access.user.role_not_manageable'); return; }
        }

        $email    = strtolower(trim((string) $v['email']));
        $username = trim((string) $v['username']);
        $username = $username !== '' ? $username : null;
        $deleted  = ((string) ($v['deleted'] ?? '0')) === '1';

        if ($user->isTaken('email', $email, $userId)) { $this->_error('access.user.email_taken'); return; }
        if ($username !== null && $user->isTaken('username', $username, $userId)) { $this->_error('access.user.username_taken'); return; }
        // email/username are plain UNIQUE: a deleted user still holds theirs — restore that user instead.
        if ($user->heldByDeleted('email', $email, $userId)) { $this->_error('access.user.email_deleted'); return; }
        if ($username !== null && $user->heldByDeleted('username', $username, $userId)) { $this->_error('access.user.username_deleted'); return; }

        // ── Memberships: the roles this user holds, org by org ──
        $current = [];
        if ($userId !== null) {
            foreach ($orgUser->membershipsWithOrgs($userId) as $m) { $current[(string) $m['org_id']] = (string) $m['role']; }
        }
        ksort($current);
        // An edit that doesn't SEND memberships (an API caller changing just an email) leaves them exactly as
        // they are — absence never means "remove them all". A create must send them.
        $sent   = array_key_exists('memberships', $params);
        $wanted = $sent ? $this->_parseMemberships($params['memberships']) : ($userId !== null ? $current : []);
        if ($wanted === null) { $this->_fieldError($form, 'memberships', 'access.user.membership.invalid'); return; }

        // You can't lock yourself out: no self-delete, no self-suspend, no changing your own memberships.
        if ($userId !== null && $userId === $this->_user_id) {
            if ($deleted)                         { $this->_error('access.user.no_self_delete'); return; }
            if ((string) $v['status'] !== 'active') { $this->_error('access.user.no_self_suspend'); return; }
            if ($wanted != $current)              { $this->_error('access.user.no_self_memberships'); return; }
        }
        // A new user starts in at least one org; an existing user can't be left with none (a legacy user who
        // never had one can still be edited).
        if (!$wanted && ($userId === null || $current)) { $this->_fieldError($form, 'memberships', 'access.user.membership.required'); return; }

        $orgs = new Tiger_Model_Org();
        foreach ($wanted as $orgId => $role) {
            $org = $orgs->findById($orgId);
            if (!$org || !$this->_inOrgScope($orgId)) { $this->_fieldError($form, 'memberships', 'access.user.membership.org'); return; }
            // Granting (or keeping a changed) role is acting at that tier — the same guard as editing.
            if (($current[$orgId] ?? null) !== $role && !$this->_mayGrantRole($role)) {
                $this->_fieldError($form, 'memberships', 'access.user.membership.role'); return;
            }
        }
        foreach (array_diff_key($current, $wanted) as $orgId => $role) {
            if (!$this->_inOrgScope($orgId)) { $this->_fieldError($form, 'memberships', 'access.user.membership.org'); return; }
        }

        $locale   = trim((string) $v['locale']);
        $timezone = trim((string) $v['timezone']);
        $newPw    = (string) $v['new_password'];
        $data = [
            'email'    => $email,
            'username' => $username,
            'status'   => $v['status'],
            'locale'   => $locale !== '' ? $locale : null,
            'timezone' => $timezone !== '' ? $timezone : null,
            'deleted'  => $deleted ? 1 : 0,
        ];

        try {
            $id = $this->_transaction(function () use ($user, $orgUser, $userId, $data, $newPw, $wanted, $current) {
                if ($userId) {
                    $user->update($data, ['user_id = ?' => $userId]);
                    $newId = $userId;
                } else {
                    $newId = $user->insert($data);
                }
                // Admin authority: set/reset the password outright when provided (no current-password
                // step — that's the self-service Profile_Service_Security path). Blank = unchanged.
                // Route through the auth write seam so a registered credential provider is honoured:
                // for an ordinary DB user this is the same setPassword; for a provider-owned identity
                // (e.g. the account owner on TigerServer) it rewrites the system password instead.
                if ($newPw !== '' && !(new Tiger_Service_Authentication())->setPasswordFor($newId, $newPw)) {
                    throw new RuntimeException('Could not set the password for this user.');
                }
                foreach ($wanted as $orgId => $role) { $orgUser->assign($orgId, $newId, $role); }
                foreach (array_diff_key($current, $wanted) as $orgId => $role) { $orgUser->revoke($orgId, $newId); }
                return $newId;
            });
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
            return;
        }

        // A new, active user with no password chosen by the admin is emailed a set-your-password link.
        $invited = false;
        if ($userId === null && $newPw === '' && !$deleted && (string) $v['status'] === 'active') {
            $invited = (new Tiger_Service_Authentication())->sendWelcome($id, $this->_siteBaseUrl(), $this->_siteName());
        }
        $this->_success(['user_id' => $id, 'invited' => $invited],
            $invited ? 'access.user.created_invited' : ($userId === null ? 'access.user.created' : 'access.user.saved'));
    }

    /**
     * One user for the Users admin modal: identity, status, the deleted flag and every membership.
     *
     * @apiParam string user_id required The user (a soft-deleted user is returned too, so it can be restored).
     * @param  array $params `user_id`
     * @return void
     */
    public function get(array $params): void
    {
        if (!$this->_isAllowed('get')) { $this->_error('core.api.error.not_allowed'); return; }
        $id   = (string) ($params['user_id'] ?? '');
        $user = $id !== '' ? (new Tiger_Model_User())->findById($id, true) : null;
        if (!$user) { $this->_error('access.user.not_found'); return; }
        $manageable = $this->_mayManageUser($id);
        $this->_success([
            'user_id'     => (string) $user->user_id,
            'email'       => (string) $user->email,
            'username'    => (string) ($user->username ?? ''),
            'locale'      => (string) ($user->locale ?? ''),
            'timezone'    => (string) ($user->timezone ?? ''),
            'status'      => (string) $user->status,
            'deleted'     => (int) $user->deleted === 1,
            'is_self'     => $id === $this->_user_id,
            'can_edit'    => $manageable,
            'memberships' => array_map(static fn ($m) => [
                'org_id' => (string) $m['org_id'], 'org_name' => (string) $m['org_name'],
                'role'   => (string) $m['role'],   'status'   => (string) $m['status'],
            ], (new Tiger_Model_OrgUser())->membershipsWithOrgs($id)),
        ]);
    }

    /**
     * What the Users admin modal offers: the roles the caller may grant and the caller's own org (the
     * default for a new user's first membership).
     *
     * @apiRequest none
     * @param  array $params (none)
     * @return void
     */
    public function options(array $params): void
    {
        if (!$this->_isAllowed('options')) { $this->_error('core.api.error.not_allowed'); return; }
        $acl   = Zend_Registry::isRegistered('Zend_Acl') ? Zend_Registry::get('Zend_Acl') : null;
        $roles = [];
        foreach (($acl ? $acl->getRoles() : []) as $role) {
            // Only tiers the caller may manage (Tiger_Access_ManageRole) — data, never a role-name compare.
            if ($this->_mayGrantRole((string) $role)) { $roles[] = (string) $role; }
        }
        $org = (new Tiger_Model_Org())->findById((string) $this->_orgId());
        $this->_success([
            'roles'       => $roles,
            'default_org' => $org ? ['org_id' => (string) $org->org_id, 'name' => (string) $org->name] : null,
        ]);
    }

    /**
     * Orgs matching a search, for the Memberships tab's org picker — limited to the orgs the caller may see.
     *
     * @apiParam string term What the admin typed (empty = the first page alphabetically).
     * @param  array $params `term`
     * @return void
     */
    public function orgSearch(array $params): void
    {
        if (!$this->_isAllowed('orgSearch')) { $this->_error('core.api.error.not_allowed'); return; }
        $term = is_string($params['term'] ?? null) ? $params['term'] : '';
        $this->_success(['orgs' => (new Tiger_Model_Org())->search($term, 20, $this->_orgScope())]);
    }

    /**
     * Normalise the submitted memberships to [org_id => role], or null when the shape is wrong.
     * Accepts JSON text or an array of {org_id, role}; a duplicate org is a shape error.
     */
    private function _parseMemberships($raw): ?array
    {
        if ($raw === null || $raw === '') { return []; }
        if (is_string($raw)) {
            $raw = json_decode($raw, true);
            if (!is_array($raw)) { return null; }
        }
        if (!is_array($raw)) { return null; }
        $out = [];
        foreach ($raw as $m) {
            if (!is_array($m)) { return null; }
            $org  = trim((string) ($m['org_id'] ?? ''));
            $role = trim((string) ($m['role'] ?? ''));
            if ($org === '' || $role === '' || isset($out[$org])) { return null; }
            $out[$org] = $role;
        }
        ksort($out);
        return $out;
    }

    /** A role the caller may hand out: it exists in the ACL and the caller may manage that tier. */
    private function _mayGrantRole(string $role): bool
    {
        $acl = Zend_Registry::isRegistered('Zend_Acl') ? Zend_Registry::get('Zend_Acl') : null;
        return $acl && $acl->hasRole($role) && $this->_mayManageRole($role);
    }

    /** Report a translated error on one form field and return the form errors. */
    private function _fieldError(Tiger_Form $form, string $field, string $key): void
    {
        $msg = Zend_Registry::isRegistered('Zend_Translate') ? Zend_Registry::get('Zend_Translate')->translate($key) : $key;
        $form->getElement($field)->addError($msg);
        $this->_formErrors($form);
    }

    /** The site's base URL for emailed links: this request's scheme + host, else tiger.site.url. */
    private function _siteBaseUrl(): string
    {
        $req = Zend_Controller_Front::getInstance()->getRequest();
        if ($req instanceof Zend_Controller_Request_Http && $req->getHttpHost() !== '') {
            return $req->getScheme() . '://' . $req->getHttpHost();
        }
        $cfg = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        return ($cfg && $cfg->tiger && $cfg->tiger->site) ? rtrim((string) $cfg->tiger->site->get('url'), '/') : '';
    }

    /** The site name for emails (tiger.site.name), or ''. */
    private function _siteName(): string
    {
        $cfg = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        return ($cfg && $cfg->tiger && $cfg->tiger->site) ? (string) $cfg->tiger->site->get('name') : '';
    }

    /**
     * Soft-delete a user (never yourself).
     *
     * @param  array $params the request payload carrying user_id
     * @apiRequest Access_Form_UserDelete
     * @apiParam string user_id required The user to delete.
     * @return void
     */
    public function delete(array $params): void
    {
        if (!$this->_isAllowed('delete')) { $this->_error('core.api.error.not_allowed'); return; }
        // The Users list's token (TIGER-528) — a delete-by-id is still a write.
        $form = new Access_Form_UserDelete();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        $id = !empty($params['user_id']) ? (string) $params['user_id'] : '';
        if ($id === '') { $this->_error('core.api.error.general'); return; }
        if ($id === $this->_user_id) { $this->_error('access.user.no_self_delete'); return; }
        if (!$this->_mayManageUser($id)) { $this->_error('access.user.role_not_manageable'); return; }   // TIGER-511

        try {
            (new Tiger_Model_User())->softDelete(['user_id = ?' => $id]);
            $this->_success(['user_id' => $id], 'access.user.deleted', '/access/user');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }
}
