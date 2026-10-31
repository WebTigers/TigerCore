<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Service_Service — abstract base for every /api service.
 *
 * This is the class services extend SO THAT they can consume and route API
 * requests. Ported from AskLevi's Levi_Service_Service (the proven TIGER pattern
 * from the "REST is Dying" design): the ServiceFactory constructs a concrete
 * child with the WHOLE request message, and the base routes it to the action
 * method named by the message. The action receives the entire `$params` payload —
 * you don't hand-wire arguments; the message IS the argument.
 *
 *   class Billing_Service_Invoice extends Tiger_Service_Service
 *   {
 *       public function create(array $params): void
 *       {
 *           if (!$this->_isAllowed('create')) { $this->_error('core.api.error.not_allowed'); return; }
 *           // ... do work with $params ...
 *           $this->_success($invoice);
 *       }
 *   }
 *
 * The caller then just reads getResponse() — all the work happened during
 * construction. That's the "slick" bit from the article: instantiate, and it's
 * already done.
 *
 * @api
 */
abstract class Tiger_Service_Service
{
    /** @var Tiger_Model_ResponseObject */
    protected $_response;

    /** @var Zend_Config|null */
    protected $_config = null;

    /** @var Zend_Translate|null */
    protected $_translate = null;

    /** @var mixed|null authenticated identity */
    protected $_identity = null;

    /** @var string|null current user id (from identity) */
    protected $_user_id = null;

    /** @var string|null current org id (the tenant the identity is acting in) */
    protected $_org_id = null;

    /**
     * Construct the service with the whole request message and dispatch it — by the
     * time this returns the named action has run and the response is ready.
     *
     * @param  array $params the full request message/payload
     * @return void
     */
    public function __construct(array $params = [])
    {
        $this->_response = new Tiger_Model_ResponseObject();

        if (Zend_Registry::isRegistered('Zend_Config')) {
            $this->_config = Zend_Registry::get('Zend_Config');
        }

        if (Zend_Registry::isRegistered('Zend_Translate')) {
            $this->_translate = Zend_Registry::get('Zend_Translate');
            // /api is never locale-prefixed, so response text takes its locale from
            // the payload (an explicit `locale`) or the request's current LANG.
            $payloadLang = isset($params['locale']) && preg_match('/^([a-zA-Z]{2,3})/', (string) $params['locale'], $lm)
                ? strtolower($lm[1])   // primary subtag, 2-3 letters (en, es, tlh)
                : null;
            if ($payloadLang && defined('SUPPORTED_LANGS') && in_array($payloadLang, SUPPORTED_LANGS, true)) {
                try { $this->_translate->setLocale($payloadLang); } catch (Throwable $e) {}
            }
        }

        $identity = Zend_Auth::getInstance()->getIdentity();
        if ($identity) {
            $this->_identity = $identity;
            $this->_user_id  = $identity->user_id ?? null;
            $this->_org_id   = $identity->org_id  ?? null;
        }

        $this->init();
        $this->_dispatch($this->_decodeB64($params));
    }

    /** Optional child setup that runs before dispatch. */
    protected function init() {}

    /**
     * WAF/base64 shim. A client may send any field as `<name>_b64` (base64 of a UTF-8 string); it's
     * decoded here into the plain `<name>` before dispatch, so services and forms are oblivious.
     *
     * Why: a shared ALB WAF (managed XSS/SQLi rules) 403s legitimate admin content — a page body
     * with `<script>`, PHP snippets, SQL-looking text — because it can't tell trusted authoring from
     * an attack. Base64 makes the payload opaque so the WAF stops false-positiving; the endpoint is
     * still ACL-gated and the content is still validated/sanitized on save, so security is unchanged.
     *
     * @param  array $params the incoming message
     * @return array         params with any `*_b64` fields decoded into their base name
     */
    protected function _decodeB64(array $params)
    {
        foreach ($params as $k => $v) {
            if (is_string($k) && is_string($v) && strlen($k) > 4 && substr($k, -4) === '_b64') {
                $decoded = base64_decode($v, true);
                if ($decoded !== false) {
                    $params[substr($k, 0, -4)] = $decoded;
                }
                unset($params[$k]);
            }
        }
        return $params;
    }

    // -------------------------------------------------------------------------

    /**
     * Route the message to the action method it names. The method receives the
     * full $params. Unknown/uncallable actions and thrown errors become a clean
     * error response — nothing leaks.
     */
    protected function _dispatch(array $params)
    {
        if (empty($params['action'])) {
            return;
        }
        $action = preg_replace('/[^A-Za-z0-9_]/', '', $params['action']);

        // Only real, callable methods dispatch. (The '_' guard below keeps the
        // message from reaching protected/internal helpers named like actions.)
        if ($action === '' || $action[0] === '_'
            || !method_exists($this, $action) || !is_callable([$this, $action])) {
            $this->_error('core.api.error.invalid_action');
            return;
        }

        try {
            $this->{$action}($params);
        } catch (Throwable $e) {
            // The client gets a safe, generic message (production) — but the REAL exception is always
            // recorded, so a swallowed error is never invisible to an operator. See Tiger_Log sink.
            Tiger_Log::error('api.service.exception', [
                'service' => static::class,
                'action'  => $action,
                'error'   => $e->getMessage(),
                'where'   => $e->getFile() . ':' . $e->getLine(),
            ]);
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    // ----- response helpers --------------------------------------------------

    protected function _success($data = null, $message = 'core.api.success', $redirect = null)
    {
        $this->_response->result     = 1;
        $this->_response->data       = $data;
        $this->_response->redirect   = $redirect;
        $this->_response->messages[] = new Tiger_Model_MessageObject($message, 'success');
    }

    protected function _error($message = 'core.api.error.general', $data = null)
    {
        $this->_response->result     = 0;
        $this->_response->data       = $data;
        $this->_response->messages[] = new Tiger_Model_MessageObject($message, 'error');
    }

    protected function _formErrors(Zend_Form $form)
    {
        $this->_response->result = 0;
        $errors = $form->getMessages();

        // A CSRF failure isn't a "fix this field" error — the token is a hidden element, it times out
        // per page render, and the only cure is a refresh (common after a long edit or an OAuth
        // round-trip). Say that plainly (with a wink) instead of pointing at a field the user can't see.
        if (isset($errors['_csrf'])) {
            $this->_response->messages[] = new Tiger_Model_MessageObject('core.api.error.csrf', 'error');
            return;
        }

        // Localize each field's validator messages before they go to the client. A validator may set its
        // message to a semantic KEY (e.g. 'password.too_short', 'core.form.password_mismatch') expecting
        // translation — the same courtesy messages[] already get via Tiger_Model_MessageObject. Non-key
        // prose (a stock Zend message) isn't translatable, so it passes through unchanged.
        $translate = Zend_Registry::isRegistered('Zend_Translate') ? Zend_Registry::get('Zend_Translate') : null;
        if ($translate) {
            foreach ($errors as $field => $messages) {
                if (!is_array($messages)) { continue; }
                foreach ($messages as $key => $text) {
                    if (is_string($text) && $translate->isTranslated($text)) {
                        $errors[$field][$key] = $translate->translate($text);
                    }
                }
            }
        }

        $this->_response->form       = $errors;
        $this->_response->messages[] = new Tiger_Model_MessageObject('core.api.error.form', 'error');
    }

    // ----- authorization -----------------------------------------------------

    /**
     * Does the CALLER hold $privilege on $resource? The ONE authorization primitive (TIGER-335).
     *
     * $privilege is REQUIRED and FIRST so a resource-level ("any privilege") check can never be
     * written by accident — that default is what made the TIGER-114/F5 anonymous-access bug possible.
     * $resource defaults to the calling service, which is exactly what the /api dispatcher gates on
     * (resource = the service class, privilege = the method). No role string appears anywhere; "who
     * counts as admin" is an ACL grant (role inheritance), never an `if ($role === 'admin')`.
     *
     * @param  string      $privilege the named privilege, e.g. 'moderate', 'save', 'delete'
     * @param  string|null $resource  defaults to static::class
     * @return bool
     */
    protected function _isAllowed($privilege, $resource = null)
    {
        $identity = Zend_Auth::getInstance()->getIdentity();
        return $this->_roleIsAllowed($identity->role ?? 'guest', $privilege, $resource);
    }

    /**
     * Does $role hold $privilege on $resource? Answers a question about ANY role — the caller's, or a
     * THIRD PARTY's (e.g. "may the person I'm about to block compose?") — entirely from ACL data
     * (TIGER-335). No role literal, no `inheritsRole()` seniority test: `Zend_Acl::isAllowed()` accepts
     * any role, so "is this other person admin-class" is answered by the same rule that gates the
     * caller. Fails CLOSED (no ACL registered, empty role, or ungoverned resource → false).
     *
     * @param  string      $role      the role to ask about (a third party's, or the caller's)
     * @param  string      $privilege the named privilege
     * @param  string|null $resource  defaults to static::class
     * @return bool
     */
    protected function _roleIsAllowed($role, $privilege, $resource = null)
    {
        if (!Zend_Registry::isRegistered('Zend_Acl')) {
            return false;                       // fail CLOSED
        }
        $acl      = Zend_Registry::get('Zend_Acl');
        $role     = (string) $role;
        $resource = $resource ?? static::class;

        if ($role === '' || !$acl->has($resource)) {
            return false;                       // an empty role or an ungoverned resource can't grant access
        }
        return $acl->isAllowed($role, $resource, $privilege);
    }

    // ----- data / transactions ----------------------------------------------

    /** The default DB adapter, or a clear failure if none is configured. */
    protected function _db()
    {
        $db = Zend_Db_Table_Abstract::getDefaultAdapter();
        if (!$db) {
            throw new RuntimeException('No database adapter is configured.');
        }
        return $db;
    }

    /**
     * Run $work inside a DB transaction: begin -> $work($db) -> commit. Any
     * throwable rolls back and re-throws, so the caller's catch turns it into an
     * error response. This is the canonical Tiger service flow — validate a form
     * FIRST, then wrap the mutation:
     *
     *   public function create(array $params): void
     *   {
     *       $form = new Some_Form();
     *       if (!$form->isValid($params)) { $this->_formErrors($form); return; }   // isValidPartial() for PATCH
     *       try {
     *           $id = $this->_transaction(function ($db) use ($params) {
     *               // ... inserts/updates; throw to abort + roll back ...
     *               return $newId;
     *           });
     *           $this->_success(['id' => $id], 'some.success');
     *       } catch (Throwable $e) {
     *           $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
     *       }
     *   }
     *
     * @param  callable $work receives the adapter; its return value is passed back
     * @return mixed
     */
    protected function _transaction(callable $work)
    {
        $db = $this->_db();
        $db->beginTransaction();
        try {
            $result = $work($db);
            $db->commit();
            return $result;
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    // ----- DataTables (server-side processing) -------------------------------

    /**
     * Normalize a DataTables server-side-processing request into a tidy shape:
     * `draw`, `start`, `length` (clamped to [1, $maxLength]), a single `search`
     * string, and `order` as [ ['column'=>int, 'dir'=>'ASC'|'DESC'], … ]. A grid's
     * `datatable` action reads these, runs its count + page queries, and answers
     * with _dtResponse(). Part of the client/server paradigm: data grids fetch rows
     * from /api, they are never server-rendered (see AGENTS.md, WEBSERVICES.md §5).
     *
     * @return array{draw:int,start:int,length:int,search:string,order:array}
     */
    /**
     * The ACL resource naming the "reach every org" capability.
     *
     * A CAPABILITY, never a role tier — per the TIGER-335 primitive, a service must not compare role
     * strings, and a resource named after a tier (`..._Admin`) is a role compare with one level of
     * indirection. Granted to `admin` in core's acl.ini, so `superadmin`/`developer` inherit it and
     * `manager`/`supermanager` do not (ACL.md §1a). An app may re-grant it like any other rule.
     */
    const RESOURCE_ALL_ORGS = 'Tiger_Tenancy_AllOrgs';

    /**
     * The caller's own org, or '' when they have no membership.
     *
     * @return string
     */
    protected function _orgId()
    {
        $identity = Zend_Auth::getInstance()->getIdentity();
        return ($identity && !empty($identity->org_id)) ? (string) $identity->org_id : '';
    }

    /**
     * The org scope this caller READS under — the one place that decision is made.
     *
     * Returns **null for an app-wide caller**, meaning *apply no org predicate at all*: an app-wide
     * role is not scoped, not "scoped with an opt-in" (ACL.md §1a). Otherwise it returns the tenant
     * scope — the caller's own org plus `''`, the shared tier that module-shipped and platform-owned
     * rows live in, matching what the public browse gates already honour.
     *
     * Scope belongs HERE, in the service, and travels into the model finder as a parameter. A finder
     * that hard-codes `org_id = <caller's org>` has silently decided that no app-wide role exists —
     * which is exactly how the Media Library came to wall an admin out of the orgs it administers.
     *
     * @return array<int,string>|null the org ids to read under, or null for every org
     */
    protected function _orgScope()
    {
        if ($this->_isAllowed('read', self::RESOURCE_ALL_ORGS)) {
            return null;
        }
        return array_values(array_unique([$this->_orgId(), '']));
    }

    /**
     * Is a row owned by `$orgId` within the caller's read scope?
     *
     * The row-level twin of `_orgScope()`, for guarding an action on a caller-supplied id: the ACL
     * grants the privilege, it says nothing about the target row. An app-wide caller passes for any
     * org; a tenant caller passes for their own org and the shared `''` tier.
     *
     * @param  string $orgId the target row's org_id
     * @return bool
     */
    protected function _inOrgScope($orgId)
    {
        $scope = $this->_orgScope();
        return ($scope === null) || in_array((string) $orgId, $scope, true);
    }

    protected function _dtParams(array $params, int $maxLength = 100): array
    {
        $length = (int) ($params['length'] ?? 25);
        $length = $length === -1 ? $maxLength : max(1, min($maxLength, $length));

        $search = '';
        if (isset($params['search'])) {
            $search = is_array($params['search'])
                ? (string) ($params['search']['value'] ?? '')
                : (string) $params['search'];
        }

        $order = [];
        foreach ((array) ($params['order'] ?? []) as $o) {
            if (!is_array($o) || !isset($o['column'])) { continue; }
            $order[] = [
                'column' => (int) $o['column'],
                'dir'    => (strtolower((string) ($o['dir'] ?? 'asc')) === 'desc') ? 'DESC' : 'ASC',
            ];
        }

        return [
            'draw'   => max(0, (int) ($params['draw'] ?? 0)),
            'start'  => max(0, (int) ($params['start'] ?? 0)),
            'length' => $length,
            'search' => trim($search),
            'order'  => $order,
        ];
    }

    /**
     * Emit the DataTables response envelope ({draw, recordsTotal, recordsFiltered,
     * data}) via the standard success response. The shared client helper
     * (tiger.datatable.js) unwraps the Tiger envelope into what DataTables consumes.
     * `$data` is structured rows only — never HTML; the client renders cells (and
     * gates controls off per-row permission flags the service puts on each row).
     */
    protected function _dtResponse(int $draw, int $recordsTotal, int $recordsFiltered, array $data, string $message = 'core.api.success'): void
    {
        $this->_success([
            'draw'            => $draw,
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $recordsFiltered,
            'data'            => $data,
        ], $message);
    }

    // -------------------------------------------------------------------------

    /**
     * The response object built during construction/dispatch.
     *
     * @return Tiger_Model_ResponseObject
     */
    public function getResponse()
    {
        return $this->_response;
    }
}
