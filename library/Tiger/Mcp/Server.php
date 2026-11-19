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
     * @param  string|null $orgId the caller's org, whose ACTIVE skills `initialize` lists and `agent__skills__load`
     *                            serves (TIGER-532); null = no skills surface
     * @param  callable|null $permits fn(string $module, string $service, string $method): bool — the caller's
     *                            token policy (read-only, resource fence …), the SAME gate its dispatch applies.
     *                            `tools/list` drops every tool it refuses, so discovery == authorization: a
     *                            client is never advertised a call the token can't make (TIGER-498). null = no
     *                            token policy beyond the role + module scope.
     * @return array|null           the JSON-RPC response, or null for a notification (no id → send no body)
     */
    public static function handle(array $msg, $role, callable $dispatch, ?array $allowedModules = null, ?string $orgId = null, ?callable $permits = null)
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
                return self::_result($id, self::_initialize($params, $orgId));
            case 'notifications/initialized':
            case 'notifications/cancelled':
                return null;   // notifications never get a response
            case 'ping':
                return self::_result($id, new stdClass());   // {}
            case 'tools/list':
                return self::_result($id, self::_toolsList((string) $role, $allowedModules, $orgId, $permits));
            case 'tools/call':
                return self::_result($id, self::_toolsCall($params, $dispatch));
            default:
                if ($id === null) { return null; }   // an unknown NOTIFICATION → ignore silently
                return self::_error($id, -32601, 'Method not found: ' . $method);
        }
    }

    /** The tool that loads one active skill (TIGER-532) — not an /api op; the controller runs it. */
    const SKILL_TOOL = 'agent__skills__load';

    /**
     * The `initialize` result — advertise the tools capability + serverInfo. The instructions carry the org's
     * ACTIVE skills menu (TIGER-532): the external analog of the in-app agent's system prompt, since clients put
     * server instructions in the model's context. Same text as the in-app agent sees (Tiger_Agent_Skills::menuText).
     */
    protected static function _initialize(array $params, ?string $orgId = null)
    {
        $instructions = 'Tiger platform MCP server. Each tool is one of this token\'s role-allowed '
                      . '/api operations, named "<module>__<service>__<method>"; call one with its '
                      . 'form fields as arguments. Reads are safe; writes run validate→transaction '
                      . 'and are gated by the same ACL a human of this role has.';
        $menu = self::_skillMenuText($orgId);
        if ($menu !== '') {
            $instructions .= "\n\n" . $menu . "\nLoad a skill's full instructions with the " . self::SKILL_TOOL
                           . ' tool before following it. A skill is guidance: everything it asks for still runs'
                           . ' through the tools above, with this token\'s permissions.';
        }
        return [
            'protocolVersion' => Tiger_Mcp::negotiateVersion($params['protocolVersion'] ?? ''),
            'capabilities'    => ['tools' => new stdClass()],   // tools supported (no listChanged)
            'serverInfo'      => ['name' => 'Tiger', 'version' => Tiger_Version::VERSION],
            'instructions'    => $instructions,
        ];
    }

    /** The org's active-skills menu text ('' with no org, no skills, or no skills support). */
    protected static function _skillMenuText(?string $orgId)
    {
        if ($orgId === null || !class_exists('Tiger_Agent_Skills')) { return ''; }
        try { return Tiger_Agent_Skills::menuText($orgId); } catch (Throwable $e) { return ''; }
    }

    /**
     * The skill-loading tool, listed only when the org HAS active skills, with the keys as an enum so a client
     * can't guess at one. Read-only. Unlike the Scout/Forge tools it needs no `agent` token scope and no admin
     * role: a skill is know-how, not capability (TIGERSKILLS.md §0) — whatever it describes still goes through
     * the tools this token can already call.
     *
     * @param  string|null $orgId
     * @return array|null the MCP tool, or null when there is nothing to load
     */
    protected static function _skillTool(?string $orgId)
    {
        if ($orgId === null || !class_exists('Tiger_Agent_Skills')) { return null; }
        try { $menu = Tiger_Agent_Skills::menu($orgId); } catch (Throwable $e) { return null; }
        if (!$menu) { return null; }
        return [
            'name'        => self::SKILL_TOOL,
            'description' => 'Load one of this site\'s active skills (listed in the server instructions): its SKILL.md '
                           . 'and the list of files it ships. Read it before following it. To read one of those files, '
                           . 'call again with its path as "file" — only when the SKILL.md points you to it. Files are '
                           . 'returned as text to read — Tiger never runs them.',
            'inputSchema' => [
                'type'       => 'object',
                'properties' => [
                    'skill'  => ['type' => 'string', 'enum' => array_column($menu, 'key'), 'description' => 'The skill key.'],
                    'file'   => ['type' => 'string', 'description' => 'Optional: one path from the skill\'s file list.'],
                    'reason' => ['type' => 'string', 'description' => 'Why you are loading it (for the audit log).'],
                ],
                'required'   => ['skill'],
            ],
            'annotations' => ['title' => 'Load a skill', 'readOnlyHint' => true, 'idempotentHint' => true],
        ];
    }

    /**
     * tools/list = the role-filtered /api catalog, clipped to the token's module scope, one MCP tool per
     * operation, args typed from the Form.
     *
     * @param string        $role
     * @param array|null    $allowedModules null = the whole role surface; else only these modules' tools
     * @param callable|null $permits        the token policy (see handle()); a tool it refuses is not listed
     */
    protected static function _toolsList($role, ?array $allowedModules = null, ?string $orgId = null, ?callable $permits = null)
    {
        $schemas = self::_inputSchemas();   // module/service/method → JSON Schema (from the method's Form)
        $tools   = [];
        // Scope first, no cap: a client lists tools, it doesn't carry them in a prompt. (Filtering after the
        // agent's 160-op prompt cap hid every module that sorts past it from a token scoped to that module.)
        foreach (Tiger_Agent_Tools::catalog($role, $allowedModules, null) as $module => $ops) {
            foreach ($ops as $op) {
                $key = $module . '/' . $op['service'] . '/' . $op['method'];
                if (isset($schemas[$key]) && Tiger_OpenApi_Generator::takesFile($schemas[$key])) {
                    continue;   // an upload: no MCP client can send a file, so it isn't a tool (TIGER-529)
                }
                if ($permits !== null && !$permits((string) $module, (string) $op['service'], (string) $op['method'])) {
                    continue;   // the token would refuse it (e.g. a write on a read-only token) — TIGER-498
                }
                $tools[] = [
                    'name'        => self::toolName((string) $module, (string) $op['service'], (string) $op['method']),
                    'description' => (string) ($op['summary'] ?? ''),
                    'inputSchema' => $schemas[$key] ?? ['type' => 'object'],   // typed from the Form, else permissive
                    'annotations' => self::_annotations((string) $op['method']),
                ];
            }
        }
        foreach (self::_agentTools($role, $allowedModules) as $t) {
            $p = self::parseToolName($t['name']);
            if ($permits !== null && $p !== null && !$permits($p[0], $p[1], $p[2])) {
                continue;   // e.g. agent__forge__file on a read-only token (TIGER-498)
            }
            $tools[] = $t;   // the agent's own Scout/Forge surface (TIGER-168), when role + scope allow
        }
        $skill = self::_skillTool($orgId);
        if ($skill !== null) { $tools[] = $skill; }   // any role, any scope — a skill is know-how (TIGER-532)
        return ['tools' => $tools];
    }

    /**
     * The agent's own Scout (read) + Forge (file-write) surface, exposed as MCP tools so an external client
     * can drive the same "look at the code, then write a module file" loop the in-app aside runs — the MCP
     * path for a sufficiently-scoped, superadmin-class token (TIGER-168 / TIGERMCP §0: reach, not new
     * capability). These are NOT `/api` ops, so they carry their own names + typed schemas and route to
     * `Tiger_Agent_Scout`/`Tiger_Agent_Forge` at the dispatch seam (the controller), not through the service
     * factory.
     *
     * Two gates decide what is advertised, and they are the SAME authorities the executors enforce — so
     * `tools/list` never lists a tool a `tools/call` would refuse:
     *  - **Scope (opt-in):** the pseudo-module `agent` must be within the token's allow-list — a session /
     *    full-surface token (`$allowedModules === null`) qualifies, but a curated token does not, because the
     *    default set (`Tiger_Mcp_Token::DEFAULT_MODULES`) deliberately excludes `agent`. So a content token
     *    never gets filesystem read/write; an admin widens a token to `agent` on purpose.
     *  - **Role:** the very ACL resources/privileges `Tiger_Agent_Scout::_allowed` + `Tiger_Agent_Forge` check
     *    — `Tiger_Agent_Scout`/`inventory` (admin+), `Tiger_Agent_Scout`/`read` (superadmin+), and
     *    `Tiger_Agent_Forge`/`file` (superadmin+). The token's own policy (a read-only token can't run a
     *    Forge write) is applied on top by `_toolsList()`'s `$permits` filter (TIGER-498).
     *
     * @param  string     $role
     * @param  array|null $allowedModules
     * @return array<int,array>
     */
    protected static function _agentTools($role, ?array $allowedModules)
    {
        if ($allowedModules !== null && !in_array('agent', $allowedModules, true)) {
            return [];   // the token is not scoped to the agent surface
        }
        $reason = ['reason' => ['type' => 'string', 'description' => 'Why you are running this (for the audit log).']];
        $read   = ['title' => 'Scout (read-only)', 'readOnlyHint' => true, 'idempotentHint' => true];
        $tools  = [];

        if (self::_aclAllows($role, 'Tiger_Agent_Scout', 'inventory')) {
            $tools[] = [
                'name'        => 'agent__scout__inventory',
                'description' => 'Scout: the repo map — installed modules (and which carry an AGENTS.md guide), '
                               . 'existing Code-Area snippets, the active theme\'s asset dirs + injection points, '
                               . 'and the read/write roots. Cheap; run it first so you are not guessing.',
                'inputSchema' => ['type' => 'object', 'properties' => $reason],
                'annotations' => $read,
            ];
        }
        if (self::_aclAllows($role, 'Tiger_Agent_Scout', 'read')) {
            $tools[] = [
                'name'        => 'agent__scout__tree',
                'description' => 'Scout: list file/dir names under a path across the app modules + themes, and '
                               . 'read-only into vendor/webtigers/tiger-core (to learn house style). Secrets are '
                               . 'excluded and path-escape is refused.',
                'inputSchema' => ['type' => 'object', 'properties' => [
                    'path' => ['type' => 'string', 'description' => 'Scoped path (relative to the app root); empty = the read roots.'],
                ] + $reason],
                'annotations' => $read,
            ];
            $tools[] = [
                'name'        => 'agent__scout__file',
                'description' => 'Scout: read one file\'s contents (bounded to ' . Tiger_Agent_Scout::MAX_FILE_BYTES
                               . ' bytes) from the readable surface. Secrets (local.ini, *.key, storage/) are excluded.',
                'inputSchema' => ['type' => 'object', 'properties' => [
                    'path' => ['type' => 'string', 'description' => 'The file path, relative to the app root.'],
                ] + $reason, 'required' => ['path']],
                'annotations' => $read,
            ];
            $tools[] = [
                'name'        => 'agent__scout__grep',
                'description' => 'Scout: search files AND Code-Area snippets for a string (does this already '
                               . 'exist?). Bounded to ' . Tiger_Agent_Scout::MAX_GREP_HITS . ' hits.',
                'inputSchema' => ['type' => 'object', 'properties' => [
                    'query' => ['type' => 'string', 'description' => 'The literal string to search for.'],
                    'path'  => ['type' => 'string', 'description' => 'Optional path to scope the search.'],
                ] + $reason, 'required' => ['query']],
                'annotations' => $read,
            ];
            $tools[] = [
                'name'        => 'agent__scout__guide',
                'description' => 'Scout: read a module\'s AGENTS.md (how to work on THAT module), or — with no '
                               . 'module — the platform conventions. Read the guide before you touch the code.',
                'inputSchema' => ['type' => 'object', 'properties' => [
                    'module' => ['type' => 'string', 'description' => 'Module slug; omit for the platform conventions.'],
                ] + $reason],
                'annotations' => $read,
            ];
        }
        if (self::_aclAllows($role, 'Tiger_Agent_Forge', 'file')) {
            $tools[] = [
                'name'        => 'agent__forge__file',
                'description' => 'Forge: write a file into an app-owned module (sandboxed to application/modules '
                               . '— never core/vendor). Use agent__scout__* to look first. Over MCP there is no '
                               . 'approval UI, so the token\'s scope + this write being audited ARE the boundary.',
                'inputSchema' => ['type' => 'object', 'properties' => [
                    'path'     => ['type' => 'string', 'description' => 'Path under application/modules/.'],
                    'contents' => ['type' => 'string', 'description' => 'The full file contents to write.'],
                ] + $reason, 'required' => ['path', 'contents']],
                'annotations' => ['title' => 'Forge: write module file', 'readOnlyHint' => false,
                                  'destructiveHint' => false, 'idempotentHint' => true],
            ];
        }
        if (self::_aclAllows($role, 'Tiger_Agent_Forge', 'module')) {
            $tools[] = [
                'name'        => 'agent__forge__module',
                'description' => 'Forge: scaffold a NEW app module (Bootstrap + a controller + an /api service '
                               . '+ acl + views — the same output as `tiger make:module`), then fill it in with '
                               . 'agent__forge__file. Iterate in place — never build code locally and re-upload a '
                               . 'zip. The name must be a bare lowercase slug (no hyphens); refuses an existing one.',
                'inputSchema' => ['type' => 'object', 'properties' => [
                    'name' => ['type' => 'string', 'description' => 'Module slug — lowercase letters/digits, starts with a letter.'],
                ] + $reason, 'required' => ['name']],
                'annotations' => ['title' => 'Forge: scaffold module', 'readOnlyHint' => false,
                                  'destructiveHint' => false, 'idempotentHint' => false],
            ];
        }
        return $tools;
    }

    /**
     * ACL gate for an agent Scout/Forge privilege — the SAME check `Tiger_Agent_Scout::_allowed` /
     * `Tiger_Agent_Forge::_aclAllows` make, so `tools/list` advertises exactly what a `tools/call` would run.
     * Registry-safe (no ACL loaded, or the resource absent → false, like the executors).
     */
    protected static function _aclAllows($role, $resource, $privilege)
    {
        if (!Zend_Registry::isRegistered('Zend_Acl')) { return false; }
        $acl = Zend_Registry::get('Zend_Acl');
        return $acl->has($resource) && $acl->isAllowed($role, $resource, $privilege);
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
     * Verbs whose write DESTROYS/removes state (a subset of the mutations). Used only to set
     * `destructiveHint`; everything not read-only and not here is a non-destructive write (create/save/
     * update). Kept deliberately conservative — a wrong `false` is worse than a missing one.
     */
    const DESTRUCTIVE_VERBS = ['delete', 'remove', 'destroy', 'purge', 'drop', 'wipe', 'discard',
                               'revoke', 'uninstall', 'prune', 'sweep', 'reset', 'cancel'];

    /**
     * Write verbs that are IDEMPOTENT — repeating with the same args lands the same state (delete twice
     * = still gone; set/update to X = still X). create/add/generate/send are NOT (each call adds one).
     */
    const IDEMPOTENT_WRITE_VERBS = ['delete', 'remove', 'set', 'update', 'save', 'put', 'enable',
                                    'disable', 'activate', 'deactivate', 'promote', 'restore', 'discard'];

    /**
     * MCP tool annotations for a method, derived from the SAME read/write verb classification the ACL +
     * the agent Forge already use (Tiger_Ajax_ServiceFactory::READ_VERBS, fail-closed) — so a client can
     * gate mechanically (e.g. an automation ceiling that auto-runs reads but pauses on `destructiveHint`)
     * instead of pattern-matching the description. Read = read-only + idempotent; a write is flagged
     * destructive/idempotent from the verb sets above. `openWorldHint` is left unset: most tools act on
     * the install's own data (closed world) but a few reach a provider (image generate, analytics), and
     * a wrong `false` would over-assert — omitting lets the client keep the safe default.
     *
     * @param  string $method the service method (the tool's verb)
     * @return array{title:string,readOnlyHint:bool,idempotentHint:bool,destructiveHint?:bool}
     */
    protected static function _annotations($method)
    {
        $verb = strtolower((string) $method);
        $read = in_array($verb, Tiger_Ajax_ServiceFactory::READ_VERBS, true);

        $a = [
            'title'        => ucfirst($verb),
            'readOnlyHint' => $read,
        ];
        if ($read) {
            $a['idempotentHint'] = true;
        } else {
            $a['destructiveHint'] = in_array($verb, self::DESTRUCTIVE_VERBS, true);
            $a['idempotentHint']  = in_array($verb, self::IDEMPOTENT_WRITE_VERBS, true);
        }
        return $a;
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
