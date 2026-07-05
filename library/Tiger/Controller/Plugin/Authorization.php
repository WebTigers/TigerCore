<?php
/**
 * Tiger_Controller_Plugin_Authorization — the AUTHORIZATION gate.
 *
 * A front-controller plugin (NOT a base-controller preDispatch) so it runs for
 * EVERY dispatch regardless of what a controller extends. This is the deliberate
 * improvement over AskLevi's base-controller approach: authorization can't be
 * bypassed by forgetting to extend a base class — deny-by-default applies to every
 * controller, uniformly, for the default namespace and modules alike.
 *
 * Two design choices worth knowing:
 *   - LIVE ROLE. The session stores only "who + which org" (user_id + org_id). The
 *     role is resolved FRESH from org_user every request, so a revoked or changed
 *     membership takes effect on the very next request — no stale session
 *     permissions, no forced re-login. (This is where the actor is stamped too.)
 *   - EXEMPTIONS ARE DATA. Public entry points (login, /api, errors, the public
 *     home) aren't hardcoded here — they're `allow guest` rules in core acl.ini.
 *
 * @api
 */
class Tiger_Controller_Plugin_Authorization extends Zend_Controller_Plugin_Abstract
{
    const ROLE_GUEST         = 'guest';
    const ROLE_AUTHENTICATED = 'user';

    public function preDispatch(Zend_Controller_Request_Abstract $request)
    {
        // Don't gate a request with no dispatchable controller — let ZF's
        // ErrorHandler render a clean 404 for everyone, instead of bouncing a
        // guest to login for a URL that doesn't even exist. Real controllers
        // (dispatchable) are still gated deny-by-default below.
        $dispatcher = Zend_Controller_Front::getInstance()->getDispatcher();
        if (!$dispatcher->isDispatchable($request)) {
            return;
        }

        $role     = $this->_resolveRole();
        $resource = $this->_resourceFor($request);
        if ($resource === null) {
            return;   // nothing dispatchable to gate yet
        }

        // Fail OPEN only if the ACL never loaded (partial boot) — never lock the
        // whole app out. Once Tiger_Acl_Acl is registered this is fail-CLOSED.
        if (!Zend_Registry::isRegistered('Zend_Acl')) {
            return;
        }
        /** @var Zend_Acl $acl */
        $acl = Zend_Registry::get('Zend_Acl');

        // Deny-by-default: register the resource so the baseline deny governs it,
        // then evaluate. A resource with no explicit allow is denied, never open.
        if (!$acl->has($resource)) {
            $acl->addResource(new Zend_Acl_Resource($resource));
        }

        if ($acl->isAllowed($role, $resource, $request->getActionName())) {
            return;   // allowed — proceed to the action
        }
        $this->_deny($role);
    }

    /**
     * Resolve the caller's role for THIS request. Guests get 'guest'. Authenticated
     * callers get the role from their active org's membership, read LIVE from
     * org_user (immediate revocation). Also stamps the actor and refreshes the
     * in-memory identity so services see the fresh role.
     */
    protected function _resolveRole()
    {
        $identity = Zend_Auth::getInstance()->getIdentity();
        if (!$identity || empty($identity->user_id)) {
            return self::ROLE_GUEST;
        }

        Tiger_Model_Table::setActor($identity->user_id);   // created_by/updated_by flow

        $role = self::ROLE_AUTHENTICATED;
        if (!empty($identity->org_id)) {
            try {
                $live = (new Tiger_Model_OrgUser())->roleOf($identity->org_id, $identity->user_id);
                $role = $live ?: self::ROLE_AUTHENTICATED;  // membership gone -> base role
            } catch (Throwable $e) {
                $role = isset($identity->role) ? $identity->role : self::ROLE_AUTHENTICATED;
            }
        }
        $identity->role = $role;   // refresh for services (_isAdmin)
        return $role;
    }

    /** The ACL resource for this dispatch = the controller class name (ZF1 convention). */
    protected function _resourceFor(Zend_Controller_Request_Abstract $request)
    {
        $controller = (string) $request->getControllerName();
        if ($controller === '') {
            return null;
        }
        $module  = (string) $request->getModuleName();
        $default = Zend_Controller_Front::getInstance()->getDispatcher()->getDefaultModule();

        $class = $this->_studly($controller) . 'Controller';
        if ($module !== '' && $module !== $default) {
            $class = $this->_studly($module) . '_' . $class;
        }
        return $class;
    }

    /** Denied: guests go to login (302); authenticated-but-forbidden get a themed 403. */
    protected function _deny($role)
    {
        if ($role === self::ROLE_GUEST) {
            Zend_Controller_Action_HelperBroker::getStaticHelper('redirector')
                ->gotoUrlAndExit('/auth/login');
        }

        // Authenticated but forbidden: re-dispatch to the themed 403 page instead of
        // emitting a bare string. ErrorController is public in acl.ini, so the
        // re-dispatch passes this same gate cleanly (no deny loop).
        $request = $this->getRequest();
        $request->setModuleName('default')
                ->setControllerName('error')
                ->setActionName('forbidden')
                ->setDispatched(false);
        $this->getResponse()->setHttpResponseCode(403);
    }

    /** hyphen/dot/underscore-slug -> StudlyCase (user-admin -> UserAdmin). */
    private function _studly($name)
    {
        return str_replace(' ', '', ucwords(str_replace(array('-', '.', '_'), ' ', strtolower($name))));
    }
}
