<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Agent_Mcp_Client — the OUTBOUND MCP client: the in-app agent CONSUMING a remote MCP server's tools
 * (the mirror of the inbound `/mcp` server). Speaks JSON-RPC 2.0 over the Streamable HTTP transport (a POST
 * per message) to a connection's URL, with an optional Bearer token. See TIGERMCP.md §9.
 *
 * It's a plain HTTP caller — `listTools()` for discovery (initialize → tools/list) and `callTool()` for
 * execution (tools/call → the content text). No SSE/session in v1 (request/response is enough for tool use).
 * Fault-tolerant: a down/misbehaving server yields [] tools or an error result, never an exception into the
 * agent loop.
 *
 * @api
 * @see Tiger_Agent_Mcp  the connection registry + tool aggregation/cache
 */
class Tiger_Agent_Mcp_Client
{
    const PROTOCOL_VERSION = '2025-06-18';
    const TIMEOUT          = 60;

    /**
     * Discover a connection's tools: initialize, then tools/list.
     *
     * @param  array $conn {url, token?}
     * @return array<int,array{name:string,description:string,inputSchema:array}>
     */
    public static function listTools(array $conn)
    {
        self::rpc($conn, 'initialize', [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities'    => new stdClass(),
            'clientInfo'      => ['name' => 'TigerAgent', 'version' => Tiger_Version::VERSION],
        ]);
        $res = self::rpc($conn, 'tools/list', []);
        $tools = (is_array($res) && isset($res['tools']) && is_array($res['tools'])) ? $res['tools'] : [];

        $out = [];
        foreach ($tools as $t) {
            if (!is_array($t) || empty($t['name'])) { continue; }
            $out[] = [
                'name'        => (string) $t['name'],
                'description' => (string) ($t['description'] ?? ''),
                'inputSchema' => (isset($t['inputSchema']) && is_array($t['inputSchema'])) ? $t['inputSchema'] : ['type' => 'object'],
            ];
        }
        return $out;
    }

    /**
     * Call a tool on a connection. Returns {ok, text} — the tool's content flattened to text for the agent.
     *
     * @param  array  $conn {url, token?}
     * @param  string $name the remote tool name
     * @param  array  $args the tool arguments
     * @return array{ok:bool,text:string}
     */
    public static function callTool(array $conn, $name, array $args)
    {
        $res = self::rpc($conn, 'tools/call', ['name' => (string) $name, 'arguments' => (object) $args]);
        if ($res === null) {
            return ['ok' => false, 'text' => 'The MCP server could not be reached.'];
        }
        $isError = !empty($res['isError']);
        $text = '';
        foreach ((array) ($res['content'] ?? []) as $block) {
            if (is_array($block) && ($block['type'] ?? '') === 'text') { $text .= (string) ($block['text'] ?? ''); }
        }
        return ['ok' => !$isError, 'text' => $text !== '' ? $text : ($isError ? 'The tool returned an error.' : '(no content)')];
    }

    /**
     * One JSON-RPC request → the `result` payload, or null on any failure (network, HTTP error, RPC error,
     * non-JSON). Never throws.
     *
     * @param  array  $conn   {url, token?}
     * @param  string $method
     * @param  array|object $params
     * @return array|null
     */
    public static function rpc(array $conn, $method, $params)
    {
        $url = (string) ($conn['url'] ?? '');
        if ($url === '') { return null; }

        $payload = json_encode([
            'jsonrpc' => '2.0',
            'id'      => 1,
            'method'  => (string) $method,
            'params'  => $params,
        ], JSON_UNESCAPED_SLASHES);

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: TigerAgentMCP/1.0',
        ];
        if (!empty($conn['token'])) { $headers[] = 'Authorization: Bearer ' . $conn['token']; }

        $body = static::_post($url, $headers, $payload);   // static:: so tests can override the HTTP seam
        if ($body === null) { return null; }

        $msg = json_decode($body, true);
        if (!is_array($msg) || isset($msg['error']) || !array_key_exists('result', $msg)) {
            return null;
        }
        return is_array($msg['result']) ? $msg['result'] : [];
    }

    /** POST and return the body, or null on failure. curl with a stream fallback. */
    protected static function _post($url, array $headers, $payload)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => self::TIMEOUT,
                CURLOPT_CONNECTTIMEOUT => 15,
            ]);
            $body   = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            // curl_close() is a no-op + deprecated on 8.5; the handle frees on scope exit.
            return ($body === false || $status >= 400) ? null : (string) $body;
        }
        $ctx  = stream_context_create(['http' => [
            'method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $payload,
            'timeout' => self::TIMEOUT, 'ignore_errors' => true,
        ]]);
        $body = @file_get_contents($url, false, $ctx);
        return $body === false ? null : (string) $body;
    }
}
