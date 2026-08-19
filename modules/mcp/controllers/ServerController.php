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

        $msg = json_decode($this->_rawBody(), true);
        if (!is_array($msg)) {
            $resp->setHttpResponseCode(400);
            $this->_emit(['jsonrpc' => '2.0', 'id' => null, 'error' => ['code' => -32700, 'message' => 'Parse error']]);
            return;
        }

        $identity = $this->_identity();
        $role     = ($identity && !empty($identity->role)) ? (string) $identity->role : 'guest';

        // Resolve the token's MCP policy (scope + read-only + org-scoping + metering key). null for a
        // session / no token → the full role surface, no per-token limits.
        [$config, $prefix] = $this->_tokenPolicy($identity);
        $allowed = ($config !== null) ? (array) $config['modules'] : null;
        $orgIdentity = ($config !== null && !empty($config['org_scoped'])) ? $this->_orgIdentity($identity, $config) : null;

        $out = Tiger_Mcp_Server::handle($msg, $role, function ($module, $service, $method, $args) use ($config, $prefix, $orgIdentity) {
            return $this->_dispatchTool($module, $service, $method, (array) $args, $config, $prefix, $orgIdentity);
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
        $h = (string) $this->getRequest()->getHeader('Authorization');
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
        $h = (string) $this->getRequest()->getHeader('Authorization');
        if (!preg_match('/^\s*Bearer\s+tgr_([a-f0-9]{12})_/i', $h, $m)) { return [null, '']; }
        $prefix = $m[1];
        $credId = (new Tiger_Model_UserCredential())->credentialIdByPrefix($prefix);
        return [Tiger_Mcp_Token::config((string) $credId), $prefix];
    }

    /**
     * Run one tool: enforce the token's scope + read-only, meter it, dispatch it (as the org for an
     * org-scoped token, else the token/session identity), and audit the outcome. Returns the /api envelope
     * (a denial is a `result=0` envelope the engine renders as an MCP error).
     */
    protected function _dispatchTool($module, $service, $method, array $args, $config, $prefix, $orgIdentity)
    {
        $tool = $module . '__' . $service . '__' . $method;

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
