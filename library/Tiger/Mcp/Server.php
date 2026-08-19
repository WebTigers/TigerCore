<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Mcp_Server — the MCP JSON-RPC 2.0 protocol engine (transport-agnostic; TIGERMCP.md §3-5).
 *
 * Handles one decoded MCP message and returns the JSON-RPC response (or null for a notification). It owns the
 * `initialize` / `tools/list` / `tools/call` / `ping` methods and nothing else — the `mcp` module's controller
 * is the HTTP surface (reads the body, resolves the Bearer identity, echoes the result), and the `$dispatch`
 * callback is the seam that runs a `tools/call` through `/api` as the caller. This split keeps the protocol
 * logic pure + unit-testable with no HTTP, DB, or network.
 *
 * The tool surface IS the role-filtered `/api` catalog (`Tiger_Agent_Tools::catalog`) — MCP adds reach, not
 * capability; every `tools/call` is gated by the target service's own ACL via the dispatch seam.
 *
 * @api
 * @see Tiger_Mcp             the enable gate + version negotiation
 * @see Tiger_Agent_Tools     the role-filtered /api catalog reflected into tools/list
 */
class Tiger_Mcp_Server
{
    /**
     * Handle one decoded JSON-RPC 2.0 message.
     *
     * @param  array    $msg      the decoded request: {jsonrpc, id?, method, params?}
     * @param  string   $role     the caller's role (filters tools/list)
     * @param  callable   $dispatch fn(string $module, string $service, string $method, array $args): object
     *                              — runs the named /api op and returns its response envelope. This seam is
     *                              where the MCP layer enforces the token's scope/read-only, meters, + audits.
     * @param  array|null $allowedModules the token's module scope for tools/list (null = the full role surface)
     * @return array|null           the JSON-RPC response, or null for a notification (no id → send no body)
     */
    public static function handle(array $msg, $role, callable $dispatch, ?array $allowedModules = null)
    {
        // A JSON array = a batch, which MCP removed in 2025-06-18. Refuse it.
        if (array_is_list($msg)) {
            return self::_error(null, -32600, 'Batch requests are not supported');
        }

        $id     = $msg['id'] ?? null;
        $method = (string) ($msg['method'] ?? '');
        $params = (isset($msg['params']) && is_array($msg['params'])) ? $msg['params'] : [];

        switch ($method) {
            case 'initialize':
                return self::_result($id, self::_initialize($params));
            case 'notifications/initialized':
            case 'notifications/cancelled':
                return null;   // notifications never get a response
            case 'ping':
                return self::_result($id, new stdClass());   // {}
            case 'tools/list':
                return self::_result($id, self::_toolsList((string) $role, $allowedModules));
            case 'tools/call':
                return self::_result($id, self::_toolsCall($params, $dispatch));
            default:
                if ($id === null) { return null; }   // an unknown NOTIFICATION → ignore silently
                return self::_error($id, -32601, 'Method not found: ' . $method);
        }
    }

    /** The `initialize` result — advertise the tools capability + serverInfo. */
    protected static function _initialize(array $params)
    {
        return [
            'protocolVersion' => Tiger_Mcp::negotiateVersion($params['protocolVersion'] ?? ''),
            'capabilities'    => ['tools' => new stdClass()],   // tools supported (no listChanged)
            'serverInfo'      => ['name' => 'Tiger', 'version' => Tiger_Version::VERSION],
            'instructions'    => 'Tiger platform MCP server. Each tool is one of this token\'s role-allowed '
                               . '/api operations, named "<module>__<service>__<method>"; call one with its '
                               . 'form fields as arguments. Reads are safe; writes run validate→transaction '
                               . 'and are gated by the same ACL a human of this role has.',
        ];
    }

    /**
     * tools/list = the role-filtered /api catalog, clipped to the token's module scope, one MCP tool per
     * operation, args typed from the Form.
     *
     * @param string     $role
     * @param array|null $allowedModules null = the whole role surface; else only these modules' tools
     */
    protected static function _toolsList($role, ?array $allowedModules = null)
    {
        $schemas = self::_inputSchemas();   // module/service/method → JSON Schema (from the method's Form)
        $tools   = [];
        foreach (Tiger_Agent_Tools::catalog($role) as $module => $ops) {
            if ($allowedModules !== null && !in_array((string) $module, $allowedModules, true)) {
                continue;   // outside the token's scope
            }
            foreach ($ops as $op) {
                $key = $module . '/' . $op['service'] . '/' . $op['method'];
                $tools[] = [
                    'name'        => self::toolName((string) $module, (string) $op['service'], (string) $op['method']),
                    'description' => (string) ($op['summary'] ?? ''),
                    'inputSchema' => $schemas[$key] ?? ['type' => 'object'],   // typed from the Form, else permissive
                ];
            }
        }
        return ['tools' => $tools];
    }

    /**
     * The Form-derived input schemas keyed by `<module>/<service>/<method>`, reflected once from the OpenAPI
     * generator (which maps each method's `@apiRequest` Form → a JSON Schema). Fault-tolerant: a schema is a
     * nicety, so any failure falls back to a permissive object per tool.
     *
     * @return array<string,array>
     */
    protected static function _inputSchemas()
    {
        try {
            $gen = new Tiger_OpenApi_Generator();
            return $gen->schemasByOp($gen->discover($gen->moduleServiceDirs()));
        } catch (Throwable $e) {
            return [];
        }
    }

    /** tools/call → dispatch the named /api op through the seam, wrap the envelope as MCP content. */
    protected static function _toolsCall(array $params, callable $dispatch)
    {
        $name = (string) ($params['name'] ?? '');
        $args = (isset($params['arguments']) && is_array($params['arguments'])) ? $params['arguments'] : [];

        $t = self::parseToolName($name);
        if ($t === null) {
            return self::_toolError('Unknown tool: ' . ($name !== '' ? $name : '(none)'));
        }
        try {
            $env = $dispatch($t[0], $t[1], $t[2], $args);   // the /api Tiger_Model_ResponseObject
        } catch (Throwable $e) {
            return self::_toolError('Dispatch failed');
        }

        $ok   = is_object($env) && (int) ($env->result ?? 0) === 1;
        $text = json_encode([
            'result'   => $ok ? 1 : 0,
            'data'     => is_object($env) ? ($env->data ?? null) : null,
            'messages' => is_object($env) ? ($env->messages ?? []) : [],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return ['content' => [['type' => 'text', 'text' => $text]], 'isError' => !$ok];
    }

    /** The MCP tool name for an /api op: `<module>__<service>__<method>` (module/service are alpha). */
    public static function toolName($module, $service, $method)
    {
        return $module . '__' . $service . '__' . $method;
    }

    /**
     * Reverse a tool name → [module, service, method], or null if malformed. `explode(…, 3)` keeps any
     * underscores that belong to the method name (module/service are alpha, so the first two `__` delimit).
     *
     * @param  string $name
     * @return array{0:string,1:string,2:string}|null
     */
    public static function parseToolName($name)
    {
        $p = explode('__', (string) $name, 3);
        if (count($p) !== 3 || $p[0] === '' || $p[1] === '' || $p[2] === '') { return null; }
        return $p;
    }

    /** A tool-execution error is a SUCCESSFUL JSON-RPC result with isError=true (not a protocol error). */
    protected static function _toolError($message)
    {
        return ['content' => [['type' => 'text', 'text' => (string) $message]], 'isError' => true];
    }

    protected static function _result($id, $result)
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'result' => $result];
    }

    protected static function _error($id, $code, $message)
    {
        return ['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => (int) $code, 'message' => (string) $message]];
    }
}
