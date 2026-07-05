<?php
/**
 * Tiger_Service_Authentication — the AUTHENTICATION kernel service.
 *
 * Authentication = "who are you" (identity + session). Distinct from AUTHORIZATION
 * = "what may you do" (the ACL layer: Tiger_Acl_* + the authorization plugin).
 * Named in full to keep that boundary unambiguous.
 *
 * Ported from AskLevi's Core_Service_Auth, adapted to Tiger's substrate:
 *   - Password lives in `user_credential` (Tiger_Model_UserCredential::verifyPassword),
 *     NOT on the user row.
 *   - THE ROLE IS PER-ORG. AskLevi put a single global `role` on the user; Tiger
 *     resolves the role from the caller's org_user MEMBERSHIP for the active org.
 *     So the identity carries { user_id, org_id, role } where `role` is the role in
 *     THAT org. Switching orgs (useOrg) re-resolves the role. This is the multi-
 *     tenant heart of Tiger.
 *
 * This is a KERNEL service (`Tiger_Service_*`): reserved from /api (see
 * Tiger_Ajax_ServiceFactory), called in-process by a login controller. It is NOT
 * itself an /api service and does not extend Tiger_Service_Service.
 *
 * On success it also sets the request-wide actor (Tiger_Model_Table::setActor) so
 * created_by/updated_by stamps start flowing.
 *
 * @api
 */
class Tiger_Service_Authentication
{
    /** Role for an authenticated user who isn't (yet) a member of any org. */
    const ROLE_AUTHENTICATED = 'user';

    /** Role for an unauthenticated request. */
    const ROLE_GUEST = 'guest';

    /**
     * Authenticate by identifier (email) + password. Returns the identity object on
     * success, or false on any failure (unknown user, inactive, bad password).
     *
     * @return object|false
     */
    public function login($identifier, $password)
    {
        $password = (string) $password;
        $user     = (new Tiger_Model_User())->findByEmail($identifier);

        // Constant-time / no user-enumeration: on an unknown or inactive user, still
        // run a bcrypt verify against a dummy hash so response timing doesn't reveal
        // whether the email is registered.
        if (!$user || $user->status !== 'active') {
            password_verify($password, $this->_dummyHash());
            return false;
        }

        $credModel = new Tiger_Model_UserCredential();
        $cred      = $credModel->passwordCredential($user->user_id);
        if (!$cred || $cred->secret === null) {
            password_verify($password, $this->_dummyHash());
            return false;
        }

        // Brute-force lockout: too many recent failures -> refuse without checking.
        if ($credModel->isLockedOut($cred)) {
            return false;
        }

        if (!password_verify($password, (string) $cred->secret)) {
            $credModel->recordFailure($cred->credential_id);
            return false;
        }

        $credModel->recordSuccess($cred->credential_id);
        $identity = $this->_buildIdentity($user);
        $this->_establish($identity, $user->user_id);
        return $identity;
    }

    /** A valid bcrypt hash to verify against for timing-equalization (computed once). */
    private function _dummyHash()
    {
        static $hash = null;
        if ($hash === null) {
            $hash = password_hash('tiger-timing-equalizer', PASSWORD_DEFAULT);
        }
        return $hash;
    }

    /** Destroy the authenticated session. */
    public function logout()
    {
        Zend_Auth::getInstance()->clearIdentity();
        if (Zend_Session::isStarted()) {
            Zend_Session::regenerateId();
        }
        Tiger_Model_Table::setActor(null);
    }

    /**
     * Switch the active org for the already-authenticated user, re-resolving the
     * role from that org's membership. Returns the new identity, or false if the
     * caller isn't an active member of the target org.
     *
     * @return object|false
     */
    public function useOrg($orgId)
    {
        $current = $this->getIdentity();
        if (!$current) {
            return false;
        }
        $user = (new Tiger_Model_User())->findById($current->user_id);
        if (!$user) {
            return false;
        }
        $identity = $this->_buildIdentity($user, $orgId);
        if ($identity->org_id !== $orgId) {
            return false;   // not an active member of the requested org
        }
        Zend_Auth::getInstance()->getStorage()->write($identity);
        return $identity;
    }

    public function isAuthenticated()
    {
        return Zend_Auth::getInstance()->hasIdentity();
    }

    public function getIdentity()
    {
        $auth = Zend_Auth::getInstance();
        return $auth->hasIdentity() ? $auth->getIdentity() : null;
    }

    // -------------------------------------------------------------------------

    /**
     * Build the session identity for a user, resolving the ACTIVE ORG + the ROLE
     * held in that org (role-on-membership). If $orgId is given, that org is used
     * (when the user is an active member); otherwise the user's first active
     * membership is the primary org. A user with no membership is authenticated
     * with the base role and a null org (they can then create/join an org).
     *
     * @return object
     */
    protected function _buildIdentity($user, $orgId = null)
    {
        $ouModel = new Tiger_Model_OrgUser();

        $activeOrgId = null;
        $role        = self::ROLE_AUTHENTICATED;

        if ($orgId !== null) {
            $m = $ouModel->membership($orgId, $user->user_id);
            if ($m && $m->status === 'active') {
                $activeOrgId = $m->org_id;
                $role        = $m->role;
            }
        } else {
            foreach ($ouModel->orgsForUser($user->user_id) as $m) {
                if ($m->status === 'active') {
                    $activeOrgId = $m->org_id;
                    $role        = $m->role;
                    break;   // first active membership = primary org
                }
            }
        }

        $orgName = null;
        if ($activeOrgId !== null) {
            $org = (new Tiger_Model_Org())->findById($activeOrgId);
            $orgName = $org ? $org->name : null;
        }

        return (object) array(
            'user_id'  => $user->user_id,
            'email'    => $user->email,
            'username' => $user->username,
            'org_id'   => $activeOrgId,
            'org_name' => $orgName,
            'role'     => $role,   // the role IN the active org (or the base role)
        );
    }

    /** Write the identity to session with fixation protection, and set the actor. */
    protected function _establish($identity, $userId)
    {
        if (!Zend_Session::isStarted()) {
            Zend_Session::start();
        }
        Zend_Auth::getInstance()->clearIdentity();
        Zend_Session::regenerateId(true);              // session-fixation protection
        Zend_Auth::getInstance()->getStorage()->write($identity);

        Tiger_Model_Table::setActor($userId);          // created_by/updated_by now flow
    }
}
