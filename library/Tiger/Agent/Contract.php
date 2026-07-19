<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Agent_Contract — the request/response contract between the app and the model.
 *
 * This is the load-bearing seam of TigerAgent (TIGERAGENT.md §5a). The app hands the model
 * a turn; the model MUST reply with exactly ONE JSON object of this shape, which the Forge
 * then acts on. Keeping the contract here — parser + shape + the prompt fragment that
 * teaches it — means every provider adapter targets the same structure, so swapping
 * Anthropic for OpenAI changes the transport, never the language the app speaks.
 *
 * ── THE REQUEST (app → model) ────────────────────────────────────────────────
 * Delivered as the conversation messages plus a system prompt (Tiger_Agent_Tools builds it).
 * The final user turn carries the human's text and a small `context` block, e.g.:
 *
 *   { "message": "Add an FAQ page and link it in the footer",
 *     "context": { "path": "/cms/admin/pages", "role": "admin",
 *                  "capabilities": { "api": true, "code": false, "file": false, "module": false } } }
 *
 * ── THE RESPONSE (model → app) ───────────────────────────────────────────────
 * ONE JSON object. `say` is human-facing markdown; `actions` are what the Forge should do;
 * `navigate` optionally moves the user; `done` says whether the model considers the request
 * complete (false = it expects a follow-up turn once actions run):
 *
 *   {
 *     "say": "I'll create the FAQ page and add the footer link.",
 *     "actions": [
 *       { "type": "api",    "module": "cms", "service": "page", "method": "save",
 *         "params": { "title": "FAQ", "slug": "faq", "body_md": "## FAQ\n..." },
 *         "reason": "create the page" },
 *       { "type": "file",   "path": "modules/acme/views/scripts/index/index.phtml",
 *         "contents": "<h1>...</h1>", "reason": "landing view" },
 *       { "type": "code",   "name": "faq-helper", "language": "php",
 *         "code": "<?php function faq_count(){ return 12; }", "reason": "helper fn" },
 *       { "type": "module", "name": "acme", "reason": "scaffold a home for the feature" }
 *     ],
 *     "navigate": "/cms/admin/pages",
 *     "done": false
 *   }
 *
 * Every action carries a `reason` (shown to the human in the approval chip). Unknown action
 * types are dropped by the parser (fail-closed), and a response that isn't valid JSON becomes
 * a plain `say`-only turn so a chatty model never breaks the loop.
 *
 * @api
 */
class Tiger_Agent_Contract
{
    /** Call another /api service as the acting user (inherited ACL). */
    const ACTION_API = 'api';
    /** Author/activate a Code Area snippet — executable PHP (superadmin+). */
    const ACTION_CODE = 'code';
    /** Write a file into a public app module (superadmin+; never core/self). */
    const ACTION_FILE = 'file';
    /** Scaffold a new module (developer). */
    const ACTION_MODULE = 'module';

    /** Read tools (the Scout) — ALWAYS auto-run, never a write; results are fed back to the model. */
    const READ_INVENTORY = 'read.inventory';   // the "repo map": modules, snippets, theme, roots
    const READ_TREE      = 'read.tree';         // list file/dir names under a scoped path
    const READ_FILE      = 'read.file';         // one file's contents (bounded)
    const READ_GREP      = 'read.grep';         // search files + Code-Area snippets for a string

    /** Client tools (the DOM) — executed in the BROWSER by tiger.agent.js, not on the server. The
     *  page declares editable TARGETS (`data-agent-target`); the model reads/writes them by name. */
    const DOM_READ  = 'dom.read';    // read a registered page target's current content
    const DOM_WRITE = 'dom.write';   // write content into a registered page target (HTML welcome here)

    /** The Forge (write) action types. */
    const WRITE_TYPES = [self::ACTION_API, self::ACTION_CODE, self::ACTION_FILE, self::ACTION_MODULE];
    /** The Scout (read) action types. */
    const READ_TYPES = [self::READ_INVENTORY, self::READ_TREE, self::READ_FILE, self::READ_GREP];
    /** The client (browser-executed) action types. */
    const CLIENT_TYPES = [self::DOM_READ, self::DOM_WRITE];
    /** Every action type the parser accepts. */
    const TYPES = [self::ACTION_API, self::ACTION_CODE, self::ACTION_FILE, self::ACTION_MODULE,
                   self::READ_INVENTORY, self::READ_TREE, self::READ_FILE, self::READ_GREP,
                   self::DOM_READ, self::DOM_WRITE];

    /**
     * Is this a Scout (read) action — auto-run, no approval, handed to Tiger_Agent_Scout?
     *
     * @param  string $type an action type
     * @return bool
     */
    public static function isRead($type)
    {
        return in_array($type, self::READ_TYPES, true);
    }

    /**
     * Is this a CLIENT action — the server can't execute it; it's returned to tiger.agent.js,
     * which reads/writes the DOM target and posts results back to `resume` (the client leg of the
     * loop, TIGERAGENT.md §5c).
     *
     * @param  string $type an action type
     * @return bool
     */
    public static function isClient($type)
    {
        return in_array($type, self::CLIENT_TYPES, true);
    }

    /**
     * Parse a model's raw reply into the normalized contract. Never throws — a malformed
     * reply degrades to a `say`-only turn (the raw text becomes the prose), because a model
     * that forgets the JSON should still be able to answer a question.
     *
     * @param  string $raw the model's full text reply
     * @return array{say:string,actions:array,navigate:?string,done:bool}
     */
    public static function parse($raw)
    {
        $raw  = (string) $raw;
        $json = self::extractJson($raw);

        if (!is_array($json)) {
            // Not a contract object — treat the whole reply as prose.
            return ['say' => trim($raw), 'actions' => [], 'navigate' => null, 'done' => true];
        }

        $say = isset($json['say']) ? (string) $json['say'] : '';

        $actions = [];
        foreach ((array) ($json['actions'] ?? []) as $a) {
            $norm = self::normalizeAction($a);
            if ($norm !== null) {
                $actions[] = $norm;
            }
        }

        $navigate = null;
        if (!empty($json['navigate']) && is_string($json['navigate'])) {
            // Path-style only (AGENTS.md): must start with '/', no scheme, no host.
            $nav = trim($json['navigate']);
            if ($nav !== '' && $nav[0] === '/' && strpos($nav, '//') === false) {
                $navigate = $nav;
            }
        }

        $done = array_key_exists('done', $json) ? (bool) $json['done'] : true;

        return ['say' => $say, 'actions' => $actions, 'navigate' => $navigate, 'done' => $done];
    }

    /**
     * Normalize + validate one proposed action. Returns null for anything unrecognized
     * (fail-closed), so a hallucinated action type can never reach the Forge.
     *
     * @param  mixed $a a raw action from the model
     * @return array|null
     */
    public static function normalizeAction($a)
    {
        if (!is_array($a) || empty($a['type']) || !in_array($a['type'], self::TYPES, true)) {
            return null;
        }
        $type   = (string) $a['type'];
        $reason = isset($a['reason']) ? (string) $a['reason'] : '';

        switch ($type) {
            case self::ACTION_API:
                if (empty($a['module']) || empty($a['service']) || empty($a['method'])) {
                    return null;
                }
                return [
                    'type'    => $type,
                    'module'  => preg_replace('/[^a-zA-Z]/', '', (string) $a['module']),
                    'service' => preg_replace('/[^a-zA-Z]/', '', (string) $a['service']),
                    'method'  => preg_replace('/[^a-zA-Z0-9_]/', '', (string) $a['method']),
                    'params'  => is_array($a['params'] ?? null) ? $a['params'] : [],
                    'reason'  => $reason,
                ];

            case self::ACTION_CODE:
                if (!isset($a['code']) || (string) $a['code'] === '') {
                    return null;
                }
                return [
                    'type'     => $type,
                    'name'     => (string) ($a['name'] ?? 'agent-snippet'),
                    'language' => (string) ($a['language'] ?? 'php'),
                    'code'     => (string) $a['code'],
                    'reason'   => $reason,
                ];

            case self::ACTION_FILE:
                if (empty($a['path']) || !array_key_exists('contents', $a)) {
                    return null;
                }
                return [
                    'type'     => $type,
                    'path'     => (string) $a['path'],
                    'contents' => (string) $a['contents'],
                    'reason'   => $reason,
                ];

            case self::ACTION_MODULE:
                if (empty($a['name'])) {
                    return null;
                }
                // Module slugs must satisfy Tiger_Generator_Module: ^[a-z][a-z0-9]*$ — no hyphens,
                // must start with a letter. Normalize here so a proposed "book-store" becomes a
                // valid "bookstore" rather than failing at the generator.
                $slug = preg_replace('/[^a-z0-9]/', '', strtolower((string) $a['name']));
                $slug = preg_replace('/^[0-9]+/', '', $slug);
                if ($slug === '') {
                    return null;
                }
                return [
                    'type'   => $type,
                    'name'   => $slug,
                    'reason' => $reason,
                ];

            case self::READ_INVENTORY:
                return ['type' => $type, 'reason' => $reason];

            case self::READ_TREE:
                return ['type' => $type, 'path' => (string) ($a['path'] ?? ''), 'reason' => $reason];

            case self::READ_FILE:
                if (empty($a['path'])) {
                    return null;
                }
                return ['type' => $type, 'path' => (string) $a['path'], 'reason' => $reason];

            case self::READ_GREP:
                if (empty($a['query'])) {
                    return null;
                }
                return ['type' => $type, 'query' => (string) $a['query'], 'path' => (string) ($a['path'] ?? ''), 'reason' => $reason];

            case self::DOM_READ:
                if (empty($a['target'])) {
                    return null;
                }
                return ['type' => $type, 'target' => (string) $a['target'], 'reason' => $reason];

            case self::DOM_WRITE:
                if (empty($a['target']) || !array_key_exists('value', $a)) {
                    return null;
                }
                return [
                    'type'   => $type,
                    'target' => (string) $a['target'],
                    'value'  => (string) $a['value'],   // HTML is intentional here — a registered editor target
                    'kind'   => (string) ($a['kind'] ?? ''),
                    'reason' => $reason,
                ];
        }
        return null;
    }

    /**
     * Pull the first balanced JSON object out of a reply that may be wrapped in prose or a
     * ```json fence. Returns the decoded array, or null if none parses.
     *
     * @param  string $raw the model's reply
     * @return array|null
     */
    protected static function extractJson($raw)
    {
        $raw = trim($raw);

        // Whole thing is JSON.
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        // Strip a ```json … ``` fence if present.
        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $raw, $m)) {
            $decoded = json_decode($m[1], true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        // Scan for the first balanced { … } block.
        $start = strpos($raw, '{');
        if ($start === false) {
            return null;
        }
        $depth = 0;
        $inStr = false;
        $esc   = false;
        $len   = strlen($raw);
        for ($i = $start; $i < $len; $i++) {
            $c = $raw[$i];
            if ($inStr) {
                if ($esc) { $esc = false; }
                elseif ($c === '\\') { $esc = true; }
                elseif ($c === '"') { $inStr = false; }
                continue;
            }
            if ($c === '"') { $inStr = true; }
            elseif ($c === '{') { $depth++; }
            elseif ($c === '}') {
                $depth--;
                if ($depth === 0) {
                    $decoded = json_decode(substr($raw, $start, $i - $start + 1), true);
                    return is_array($decoded) ? $decoded : null;
                }
            }
        }
        return null;
    }
}
