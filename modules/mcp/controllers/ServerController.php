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

        $out = Tiger_Mcp_Server::handle($msg, $role, function ($module, $service, $method, $args) {
            // Dispatch the /api op through the SAME gateway the browser + Forge use — as the resolved
            // identity (a fresh request carries the Bearer from $_SERVER; else ServiceFactory falls back to
            // the identity written to Zend_Auth above). The target service's own ACL + form-validate +
            // transaction all run unchanged.
            $req = new Zend_Controller_Request_Http();
            $req->setParam('svc_module', $module);
            $req->setParam('svc_service', $service);
            $req->setParam('svc_action', $method);
            foreach ((array) $args as $k => $v) { $req->setParam((string) $k, $v); }
            return (new Tiger_Ajax_ServiceFactory($req))->getResponse();
        });

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
