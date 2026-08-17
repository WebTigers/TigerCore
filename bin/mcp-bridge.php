#!/usr/bin/env php
<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * mcp-bridge.php — a zero-dependency stdio <-> HTTP bridge for Tiger's MCP server (TIGERMCP.md §6).
 *
 * MCP clients (Claude Desktop, Cursor, Claude Code) launch a LOCAL process and speak newline-delimited
 * JSON-RPC over stdio. This relays each message to a Tiger install's `POST /mcp` with a Bearer token and
 * writes the response back — so a user configures Tiger like any other MCP server, needing only PHP (no
 * Node, no Composer). Config comes from the environment (or argv):
 *
 *   TIGER_MCP_URL    the install's /mcp endpoint, e.g. https://my-site.com/mcp   [required] (or argv[1])
 *   TIGER_MCP_TOKEN  a Tiger personal access token (tgr_...)                      [recommended] (or argv[2])
 *
 * ONLY JSON-RPC ever goes to STDOUT (the protocol channel); diagnostics go to STDERR. Notifications (the
 * server answers 202 no-body) relay nothing; a transport failure or a non-JSON response becomes a JSON-RPC
 * error carrying the request id, so the client never hangs.
 */

/** True only when this file is the script being run (not when a test include()s it). */
function mcp_bridge_is_main()
{
    return PHP_SAPI === 'cli'
        && isset($_SERVER['argv'][0])
        && @realpath($_SERVER['argv'][0]) === @realpath(__FILE__);
}

/** The read/relay loop: stdin JSON-RPC -> POST /mcp -> stdout. */
function mcp_bridge_run(array $argv)
{
    $url   = getenv('TIGER_MCP_URL')   ?: ($argv[1] ?? '');
    $token = getenv('TIGER_MCP_TOKEN') ?: ($argv[2] ?? '');

    if ($url === '') {
        fwrite(STDERR, "mcp-bridge: set TIGER_MCP_URL (or pass the /mcp URL as the first argument)\n");
        return 1;
    }

    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'User-Agent: TigerMCPBridge/1.0',   // an outbound UA is required — some WAFs 403 a UA-less request
    ];
    if ($token !== '') { $headers[] = 'Authorization: Bearer ' . $token; }

    while (($line = fgets(STDIN)) !== false) {
        $line = trim($line);
        if ($line === '') { continue; }

        [$ok, $status, $body, $err] = mcp_bridge_post($url, $headers, $line);
        $out = mcp_bridge_response($line, $ok, $status, $body, $err);
        if ($out !== null) {
            fwrite(STDOUT, $out . "\n");
            fflush(STDOUT);
        }
    }
    return 0;
}

/**
 * Decide what (if anything) to write to stdout for one message + its HTTP result. Pure — the unit-tested
 * heart of the bridge.
 *
 * @return string|null the JSON-RPC line to emit, or null to emit nothing (a notification)
 */
function mcp_bridge_response($requestLine, $ok, $status, $body, $err)
{
    $id  = null;
    $req = json_decode((string) $requestLine, true);
    if (is_array($req) && array_key_exists('id', $req)) { $id = $req['id']; }

    // Transport failure (no HTTP at all).
    if (!$ok) {
        return $id === null ? null : mcp_bridge_error($id, -32000, 'bridge transport error: ' . $err);
    }
    // A notification: the server accepts it with 202 and no body → relay nothing.
    if ((int) $status === 202 || trim((string) $body) === '') {
        return null;
    }
    // Guard the protocol channel: a non-JSON response (a WAF/HTML error page) must never reach stdout.
    $t = ltrim((string) $body);
    if ($t === '' || ($t[0] !== '{' && $t[0] !== '[')) {
        return $id === null ? null : mcp_bridge_error($id, -32000, 'bridge: non-JSON response (HTTP ' . (int) $status . ')');
    }
    return (string) $body;
}

/** POST $payload to $url. Returns [ok(bool), status(int), body(string), error(string)]. */
function mcp_bridge_post($url, array $headers, $payload)
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 300,
            CURLOPT_CONNECTTIMEOUT => 15,
        ]);
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = (string) curl_error($ch);
        curl_close($ch);
        return $body === false ? [false, 0, '', ($err ?: 'curl failed')] : [true, $status, (string) $body, ''];
    }

    // No curl → stream context fallback (needs allow_url_fopen).
    $ctx  = stream_context_create(['http' => [
        'method'        => 'POST',
        'header'        => implode("\r\n", $headers),
        'content'       => $payload,
        'timeout'       => 300,
        'ignore_errors' => true,
    ]]);
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) { return [false, 0, '', 'stream request failed (no curl, allow_url_fopen?)']; }
    // Status: use the 8.4+ API when present; else default 200 (an empty body already flags a 202
    // notification, and non-JSON is caught by content) — avoids the deprecated $http_response_header.
    $status = 200;
    if (function_exists('http_get_last_response_headers')) {
        $h = http_get_last_response_headers();
        if (isset($h[0]) && preg_match('#\s(\d{3})\s#', (string) $h[0], $m)) { $status = (int) $m[1]; }
    }
    return [true, $status, (string) $body, ''];
}

/** A JSON-RPC error line. */
function mcp_bridge_error($id, $code, $message)
{
    return json_encode(['jsonrpc' => '2.0', 'id' => $id, 'error' => ['code' => (int) $code, 'message' => (string) $message]]);
}

if (mcp_bridge_is_main()) {
    exit(mcp_bridge_run($_SERVER['argv']));
}
