<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Mcp — the facade for the MCP server (Tiger as an MCP server; see TIGERMCP.md).
 *
 * MCP lets an external AI client (Claude Desktop/Code, Cursor, ChatGPT) drive a Tiger install through the
 * SAME token-authenticated, ACL-gated `/api` surface the browser + in-app agent use — reach, not capability
 * (`Tiger_Mcp_Server` is the JSON-RPC engine; the `mcp` module's controller is the thin HTTP surface). This
 * facade holds the two cross-cutting bits: the OFF-by-default enable gate and protocol-version negotiation.
 *
 * @api
 * @see Tiger_Mcp_Server  the JSON-RPC protocol engine
 */
class Tiger_Mcp
{
    /** The MCP protocol version this server speaks by default (date-versioned, per the spec). */
    const PROTOCOL_VERSION = '2025-06-18';

    /** Versions we can negotiate — echo the client's back if it's one of these. */
    const SUPPORTED_VERSIONS = ['2025-06-18', '2025-03-26', '2024-11-05'];

    /** Config key — the endpoint is OFF unless an admin sets this (a shared-host install must opt in). */
    const CONFIG_ENABLED = 'tiger.mcp.enabled';

    /**
     * Is the `/mcp` endpoint enabled? Off by default — `/mcp` 404s until `tiger.mcp.enabled` is truthy.
     *
     * @return bool
     */
    public static function isEnabled()
    {
        try {
            if (!Zend_Registry::isRegistered('Zend_Config')) { return false; }
            $cfg = Zend_Registry::get('Zend_Config');
            $mcp = ($cfg->get('tiger') && $cfg->tiger->get('mcp')) ? $cfg->tiger->mcp : null;
            $v   = $mcp ? $mcp->get('enabled') : null;
            return $v !== null && (string) $v !== '0' && strtolower((string) $v) !== 'false';
        } catch (Throwable $e) {
            return false;
        }
    }

    /**
     * Negotiate the protocol version: echo the client's requested version if we support it, else respond
     * with our default (the spec requires responding with a version we do support).
     *
     * @param  string $requested the client's `protocolVersion`
     * @return string
     */
    public static function negotiateVersion($requested)
    {
        return in_array((string) $requested, self::SUPPORTED_VERSIONS, true)
            ? (string) $requested
            : self::PROTOCOL_VERSION;
    }
}
