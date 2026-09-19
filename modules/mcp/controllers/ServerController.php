<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Mcp_ServerController — the `/mcp` HTTP surface for the MCP server (TIGERMCP.md).
 *
 * A machine endpoint like ApiController: no layout, no view, JSON out. It reads the JSON-RPC body, resolves
 * the request identity (Bearer token, stateless — else the session, mirroring Tiger_Ajax_ServiceFactory),
 * and hands the message to Tiger_Mcp_Server with a dispatch seam that runs a tools/call through /api AS that
 * identity. OFF by default: /mcp 404s unless `tiger.mcp.enabled`. Public at the controller level (like
 * ApiController) — the token + every service's own ACL do the real gating.
 */
class Mcp_ServerController extends Zend_Controller_Action
{
    /** Machine endpoint: kill the layout + view renderer (so neither wraps the JSON), set the JSON header. */
    public function init()
    {
        $this->_helper->viewRenderer->setNoRender(true);
        if (Zend_Layout::getMvcInstance()) {
            Zend_Layout::getMvcInstance()->disableLayout();   // else the theme layout would wrap our JSON
        }
        $this->getResponse()->setHeader('Content-Type', 'application/json; charset=UTF-8', true);
    }

    /** The single MCP endpoint: one JSON-RPC request in, one response out (Streamable HTTP, request/response). */
    public function indexAction()
    {
        $resp = $this->getResponse();

        // OFF by default — the endpoint does not exist until an admin enables it.
        if (!Tiger_Mcp::isEnabled()) {
            $resp->setHttpResponseCode(404);
            $this->_emit(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32601, 'message' => 'MCP is not enabled']]);
            return;
        }

        // A non-POST (a browser GET, a link click) isn't a JSON-RPC call. Per the Streamable HTTP transport,
        // GET is for an SSE stream we don't offer in v1 → 405, but with a human-readable "what is this + how
        // to use it" body so hitting /mcp in a browser explains itself instead of a cryptic parse error.
        if (strtoupper((string) $this->getRequest()->getMethod()) !== 'POST') {
            $resp->setHttpResponseCode(405);
            $resp->setHeader('Allow', 'POST', true);
            $this->_emit([
                'name'            => 'Tiger',
                'version'         => Tiger_Version::VERSION,
                'protocolVersion' => Tiger_Mcp::PROTOCOL_VERSION,
                'transport'       => 'streamable-http',
                'message'         => 'This is Tiger\'s MCP endpoint. POST a JSON-RPC 2.0 request (Content-Type: '
                                   . 'application/json) — e.g. {"jsonrpc":"2.0","id":1,"method":"initialize"}. '
                                   . 'Interactive GET/SSE is not supported in v1; connect an MCP client, or test '
                                   . 'with the MCP Inspector (npx @modelcontextprotocol/inspector) or curl.',
            ]);
            return;
        }

        // JSON-RPC is JSON. Refusing any other Content-Type also closes the classic text/plain-form
        // CSRF: a cross-site <form enctype="text/plain"> cannot send application/json.
        $ct = strtolower((string) $this->getRequest()->getHeader('Content-Type'));
        if ($ct !== '' && strpos($ct, 'application/json') === false) {
            $resp->setHttpResponseCode(415);
            $this->_emit(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32700, 'message' => 'Content-Type must be application/json']]);
            return;
        }

        $msg = json_decode($this->_rawBody(), true);
        if (!is_array($msg)) {
            $resp->setHttpResponseCode(400);
            $this->_emit(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32700, 'message' => 'Parse error']]);
            return;
        }

        // A Bearer that is presented but does not verify is 401 — never a silent downgrade to guest.
        // A client with a bad key must learn it is bad, not receive the public tool list and wonder.
        $bearer = $this->_bearer();
        if ($bearer !== null && (new Tiger_Service_Authentication())->identityFromToken($bearer) === null) {
            $resp->setHttpResponseCode(401);
            $resp->setHeader('WWW-Authenticate', 'Bearer realm="Tiger MCP"', true);
            $this->_emit(['jsonrpc' => '2.0', 'id' => $msg['id'] ?? null, 'error' => ['code' => -32001, 'message' => 'Unauthorized: the Bearer token is not valid']]);
            return;
        }

        $identity = $this->_identity();
        // A session (cookie) identity is honoured only for a same-origin request. A cross-site POST
        // that rides the admin's cookie — the CSRF shape — is a guest here, whatever the cookie says.
        if ($bearer === null && $identity !== null && !$this->_sameOrigin()) {
            $resp->setHttpResponseCode(403);
            $this->_emit(['jsonrpc' => '2.0', 'id' => $msg['id'] ?? null, 'error' => ['code' => -32002, 'message' => 'Forbidden: a session may only call /mcp from its own origin; use a Bearer token']]);
            return;
        }
        $role     = ($identity && !empty($identity->role)) ? (string) $identity->role : 'guest';

        // Resolve the token's MCP policy (scope + read-only + org-scoping + metering key). null for a
        // session / no token → the full role surface, no per-token limits.
        [$config, $prefix] = $this->_tokenPolicy($identity);
        $allowed = ($config !== null) ? (array) $config['modules'] : null;
        $orgIdentity = ($config !== null && !empty($config['org_scoped'])) ? $this->_orgIdentity($identity, $config) : null;

        $out = Tiger_Mcp_Server::handle($msg, $role, function ($module, $service, $method, $args) use ($config, $prefix, $orgIdentity, $role) {
            return $this->_dispatchTool($module, $service, $method, (array) $args, $config, $prefix, $orgIdentity, $role);
        }, $allowed);

        if ($out === null) {
            $resp->setHttpResponseCode(202);   // a notification → accepted, no body
            return;
        }
        $resp->setHttpResponseCode(200);
        $this->_emit($out);
    }

    /**
     * The request identity: a Bearer token (stateless, wins) resolved to an identity and written to
     * Zend_Auth so the downstream dispatch sees it; else the session identity. Mirrors ServiceFactory — a
     * token request that presents an INVALID token stays guest (never falls back to a session).
     *
     * @return object|null the identity, or null (guest)
     */
    protected function _identity()
    {
        $h = Tiger_Ajax_ServiceFactory::authorizationHeader($this->getRequest());
        if (preg_match('/^\s*Bearer\s+(\S+)/i', $h, $m)) {
            $id = (new Tiger_Service_Authentication())->identityFromToken($m[1]);
            if ($id !== null) {
                $auth = Zend_Auth::getInstance();
                if (!($auth->getStorage() instanceof Zend_Auth_Storage_NonPersistent)) {
                    $auth->setStorage(new Zend_Auth_Storage_NonPersistent());
                }
                $auth->getStorage()->write($id);
            }
            return $id;
        }
        return Zend_Auth::getInstance()->getIdentity();
    }

    /**
     * The MCP policy for this request's token: [config|null, prefix]. Applies only for a VALID token (the
     * identity resolved) presented as a `tgr_…` Bearer; a session / no token gets [null, ''] → the full role
     * surface, no per-token scope or metering. The config is looked up by the (non-secret) prefix — the
     * secret was already verified by `_identity()`.
     *
     * @param  object|null $identity the resolved identity
     * @return array{0:?array,1:string}
     */
    protected function _tokenPolicy($identity)
    {
        if ($identity === null) { return [null, '']; }
        $h = Tiger_Ajax_ServiceFactory::authorizationHeader($this->getRequest());
        if (!preg_match('/^\s*Bearer\s+tgr_([a-f0-9]{12})_/i', $h, $m)) { return [null, '']; }
        $prefix = $m[1];
        $credId = (new Tiger_Model_UserCredential())->credentialIdByPrefix($prefix);
        return [Tiger_Mcp_Token::config((string) $credId), $prefix];
    }

    /** The presented Bearer token (verified or not), or null when the request carries none. */
    protected function _bearer()
    {
        $h = Tiger_Ajax_ServiceFactory::authorizationHeader($this->getRequest());
        return preg_match('/^\s*Bearer\s+(\S+)/i', $h, $m) ? $m[1] : null;
    }

    /**
     * Is this a same-origin request? Browsers say so themselves: Sec-Fetch-Site (same-origin / none)
     * on every modern cross-site-capable request, Origin on every cross-origin POST. A request with
     * neither header is not a browser (curl with a cookie jar, a test) and is taken at face value.
     */
    protected function _sameOrigin()
    {
        $req  = $this->getRequest();
        $site = strtolower((string) $req->getHeader('Sec-Fetch-Site'));
        if ($site !== '') { return in_array($site, ['same-origin', 'none'], true); }
        $origin = (string) $req->getHeader('Origin');
        if ($origin === '' || $origin === 'null') { return $origin === ''; }
        $host = strtolower((string) ($req->getHttpHost() ?: ($_SERVER['HTTP_HOST'] ?? '')));
        return strtolower((string) parse_url($origin, PHP_URL_HOST)) === preg_replace('/:\d+$/', '', $host)
            && (parse_url($origin, PHP_URL_PORT) === null || (string) parse_url($origin, PHP_URL_PORT) === (string) ($req->getServer('SERVER_PORT') ?? ''));
    }

    /**
     * Run one tool: enforce the token's scope + read-only, meter it, dispatch it (as the org for an
     * org-scoped token, else the token/session identity), and audit the outcome. Returns the /api envelope
     * (a denial is a `result=0` envelope the engine renders as an MCP error).
     */
    protected function _dispatchTool($module, $service, $method, array $args, $config, $prefix, $orgIdentity, $role = 'guest')
    {
        $tool = $module . '__' . $service . '__' . $method;

        // The agent surface (agent__scout__*, agent__forge__file) is not an /api op — it runs Scout/Forge
        // in-process, with its own scope + read-only + role gating (TIGER-168).
        if ($module === 'agent') {
            return $this->_dispatchAgentTool($service, $method, $args, $config, $prefix, (string) $role);
        }

        // Token policy (scope + read-only), then the soft rate limit. The service's own ACL still gates the
        // dispatch below regardless — this is an EXTRA, tighter fence on top.
        if ($config !== null) {
            $deny = Tiger_Mcp_Token::denyReason($config, $module, $method);
            if ($deny === 'out_of_scope') { return $this->_denied($tool, $prefix, $deny, 'This token cannot call that tool (out of scope).'); }
            if ($deny === 'read_only')    { return $this->_denied($tool, $prefix, $deny, 'This token is read-only.'); }
        }
        if ($prefix !== '' && !Tiger_Mcp_Token::meter($prefix)) {
            return $this->_denied($tool, $prefix, 'rate_limited', 'Rate limit exceeded for this token.');
        }

        // A tools/call is a JSON-RPC dispatch, NEVER a cross-site browser form POST — so it is CSRF-exempt,
        // exactly like the in-app agent's Forge and the Bearer-token /api path. The Bearer and org-scoped
        // paths already get this flag inside ServiceFactory (token → stateless); the SESSION-cookie path
        // did not, so cms/blog writes (services with a Tiger_Form CSRF token) were refused "security token
        // expired" over MCP even though the money-spending image generate — a form without CSRF — went
        // through (TigerImage round-4 B1). It is safe here because the action already proved this request
        // is application/json (415 otherwise) AND same-origin for a session identity (403 otherwise) — the
        // two things classic form-CSRF cannot forge — before ever reaching this dispatch.
        Zend_Registry::set('tiger.auth.stateless', true);

        $req = new Zend_Controller_Request_Http();
        $req->setParam('svc_module', $module);
        $req->setParam('svc_service', $service);
        $req->setParam('svc_action', $method);
        foreach ($args as $k => $v) { $req->setParam((string) $k, $v); }
        // Org-scoped token → dispatch AS THE ORG (user_id=null) via the identity override; else the
        // Bearer/session identity. The target service's own ACL + form-validate + transaction run unchanged.
        $env = ($orgIdentity !== null)
            ? (new Tiger_Ajax_ServiceFactory($req, $orgIdentity))->getResponse()
            : (new Tiger_Ajax_ServiceFactory($req))->getResponse();

        Tiger_Log::info('mcp.tools_call', [
            'token'      => $prefix,
            'org_scoped' => (bool) ($config['org_scoped'] ?? false),
            'tool'       => $tool,
            'result'     => (int) ($env->result ?? 0),
        ]);
        return $env;
    }

    /**
     * Run one agent tool (Scout read / Forge file-write) in-process AS the caller's role (TIGER-168). Unlike
     * an /api tool, this drives `Tiger_Agent_Scout`/`Tiger_Agent_Forge` directly — the same objects the in-app
     * aside uses — so the ACL role-gating those classes enforce (inventory=admin+, tree/file/grep/guide +
     * forge.file=superadmin+) is the real wall, and this method adds the token's fence on top:
     *  - **scope** — the token must include the pseudo-module `agent` (a session / full-surface token does);
     *  - **read-only** — only `forge.file` is a write, so it alone is refused on a read-only token; Scout reads
     *    always pass (unlike the /api verb classification, the agent verbs aren't in READ_VERBS, so we decide
     *    read-vs-write here explicitly rather than via `Tiger_Mcp_Token::denyReason`);
     *  - **metering + audit** — the same soft rate limit + `Tiger_Log` line as every other tool call.
     *
     * There is no approval UI on the MCP path, so a Forge write is dispatched `approved=true` — the boundary is
     * the deliberate `agent`+write token scope + the audit trail (TIGERMCP §5: no approval webhook).
     */
    protected function _dispatchAgentTool($service, $method, array $args, $config, $prefix, $role)
    {
        $tool    = 'agent__' . $service . '__' . $method;
        $isForge = ($service === 'forge');

        if ($config !== null) {
            if (!Tiger_Mcp_Token::allowsModule($config, 'agent')) {
                return $this->_denied($tool, $prefix, 'out_of_scope', 'This token is not scoped to the agent surface.');
            }
            if ($isForge && !empty($config['read_only'])) {
                return $this->_denied($tool, $prefix, 'read_only', 'This token is read-only; it cannot write files.');
            }
        }
        if ($prefix !== '' && !Tiger_Mcp_Token::meter($prefix)) {
            return $this->_denied($tool, $prefix, 'rate_limited', 'Rate limit exceeded for this token.');
        }

        try {
            if ($isForge) {
                $entry = (new Tiger_Agent_Forge($role))->execute([
                    'type'     => Tiger_Agent_Contract::ACTION_FILE,
                    'path'     => (string) ($args['path'] ?? ''),
                    'contents' => (string) ($args['contents'] ?? ''),
                    'reason'   => (string) ($args['reason'] ?? 'MCP client'),
                    'approved' => true,   // no approval UI over MCP — token scope + audit is the boundary
                ]);
            } else {
                $action = $this->_scoutAction($method, $args);
                if ($action === null) { return $this->_denied($tool, $prefix, 'unknown_tool', 'Unknown agent tool.'); }
                $entry = (new Tiger_Agent_Scout($role))->execute($action);
            }
        } catch (Throwable $e) {
            return $this->_denied($tool, $prefix, 'error', 'The agent tool failed.');
        }

        $status = (string) ($entry['status'] ?? 'error');
        Tiger_Log::info('mcp.tools_call', ['token' => $prefix, 'tool' => $tool, 'agent' => true, 'status' => $status]);
        return $this->_agentEnvelope($entry);
    }

    /** Build the normalized Scout action from the MCP tool method + arguments (null = unknown method). */
    protected function _scoutAction($method, array $args)
    {
        $reason = (string) ($args['reason'] ?? 'MCP client');
        switch ($method) {
            case 'inventory': return ['type' => Tiger_Agent_Contract::READ_INVENTORY, 'reason' => $reason];
            case 'tree':      return ['type' => Tiger_Agent_Contract::READ_TREE, 'path' => (string) ($args['path'] ?? ''), 'reason' => $reason];
            case 'file':      return ['type' => Tiger_Agent_Contract::READ_FILE, 'path' => (string) ($args['path'] ?? ''), 'reason' => $reason];
            case 'grep':      return ['type' => Tiger_Agent_Contract::READ_GREP, 'query' => (string) ($args['query'] ?? ''), 'path' => (string) ($args['path'] ?? ''), 'reason' => $reason];
            case 'guide':     return ['type' => Tiger_Agent_Contract::READ_GUIDE, 'module' => preg_replace('/[^a-z0-9]/', '', strtolower((string) ($args['module'] ?? ''))), 'reason' => $reason];
        }
        return null;
    }

    /**
     * Map an agent ledger entry {status, summary, feedback|detail} → the standard /api envelope, so the
     * MCP engine renders it into content exactly like an /api result. A denied/error/proposed status is a
     * failed call; anything else carries the heavy payload (Scout's `feedback`, Forge's `detail`) as data.
     */
    protected function _agentEnvelope(array $entry)
    {
        $status = (string) ($entry['status'] ?? 'error');
        $env    = new Tiger_Model_ResponseObject();
        $ok     = !in_array($status, ['denied', 'error', 'proposed'], true);
        $env->result = $ok ? 1 : 0;
        if ($ok) {
            $env->data = [
                'status' => $status,
                'output' => $entry['feedback'] ?? ($entry['detail'] ?? ($entry['summary'] ?? '')),
            ];
        }
        $env->messages[] = new Tiger_Model_MessageObject((string) ($entry['summary'] ?? ''), $ok ? 'info' : 'error');
        return $env;
    }

    /** An org-acting identity for an org-scoped token — acts AS THE ORG (no bound user_id → system actor). */
    protected function _orgIdentity($identity, array $config)
    {
        return (object) [
            'user_id'   => null,
            'org_id'    => $config['org_id'] !== '' ? $config['org_id'] : ($identity->org_id ?? null),
            'org_name'  => $identity->org_name ?? null,
            'role'      => $config['role'] !== '' ? $config['role'] : ($identity->role ?? 'guest'),
            'mcp_token' => true,
        ];
    }

    /** A denied tool call: an audited `result=0` envelope the engine renders as an MCP error. */
    protected function _denied($tool, $prefix, $key, $message)
    {
        Tiger_Log::warn('mcp.tools_call.denied', ['token' => $prefix, 'tool' => $tool, 'reason' => $key]);
        $env = new Tiger_Model_ResponseObject();
        $env->result     = 0;
        $env->messages[] = new Tiger_Model_MessageObject($message, 'error');
        return $env;
    }

    /** Raw request body (a seam so tests can inject a JSON-RPC message without php://input). */
    protected function _rawBody()
    {
        return (string) file_get_contents('php://input');
    }

    /** Emit a JSON payload as the response body. */
    protected function _emit(array $payload)
    {
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
