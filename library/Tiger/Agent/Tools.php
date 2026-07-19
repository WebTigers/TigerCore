<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Agent_Tools — build the model's tool catalog + system prompt from the LIVE, role-
 * filtered /api surface (TIGERAGENT.md §2, §5a).
 *
 * The agent's tools are not a hand-kept list — they ARE the platform's `/api` services, the
 * exact same ones a human of this role can reach, discovered by reflection and filtered
 * through the ACL. So the day a module is installed, its services become agent-callable for
 * free; the day a user's role changes, the agent's reach changes with it. Nothing to
 * maintain, nothing to drift.
 *
 * @api
 */
class Tiger_Agent_Tools
{
    /** Keep the catalog compact so it doesn't blow the context budget on big installs. */
    const MAX_OPERATIONS = 160;

    /**
     * The role-filtered catalog of callable operations, grouped by module:
     * `['cms' => [['service'=>'page','method'=>'save','summary'=>'…'], …], …]`.
     *
     * @param  string $role the acting role
     * @return array
     */
    public static function catalog($role)
    {
        $acl = Zend_Registry::isRegistered('Zend_Acl') ? Zend_Registry::get('Zend_Acl') : null;
        if (!$acl) {
            return [];
        }

        $gen     = new Tiger_OpenApi_Generator();
        $classes = $gen->discover($gen->moduleServiceDirs());
        $doc     = $gen->generate($classes);

        $catalog = [];
        $count   = 0;
        foreach ((array) ($doc['paths'] ?? []) as $path => $ops) {
            if ($count >= self::MAX_OPERATIONS) {
                break;
            }
            // /api/{module}/{service}/{method}
            $parts = explode('/', trim($path, '/'));
            if (count($parts) !== 4 || $parts[0] !== 'api') {
                continue;
            }
            [, $module, $service, $method] = $parts;
            $class = ucfirst($module) . '_Service_' . ucfirst($service);

            if (!$acl->has($class) || !$acl->isAllowed($role, $class, $method)) {
                continue;   // the role can't call it → the model never sees it
            }
            $summary = (string) ($ops['post']['summary'] ?? '');
            $catalog[$module][] = ['service' => $service, 'method' => $method, 'summary' => $summary];
            $count++;
        }
        ksort($catalog);
        return $catalog;
    }

    /**
     * Assemble the full system prompt: who the agent is, the response contract, the read tools
     * (Scout) + the loop, the capability tiers this role unlocks, the current auto-mode, and the
     * callable catalog.
     *
     * @param  string $role         the acting role
     * @param  array  $capabilities the Tiger_Agent::capabilities() map
     * @param  array  $context      optional request context (path, etc.)
     * @param  string $mode         ask | auto | yolo
     * @return string
     */
    public static function systemPrompt($role, array $capabilities, array $context = [], $mode = 'ask')
    {
        $catalog = self::catalog($role);
        $tools   = self::renderCatalog($catalog);
        $path    = (string) ($context['path'] ?? '');

        $caps = [];
        if (!empty($capabilities['inventory'])) { $caps[] = 'inspect the system (inventory)'; }
        if (!empty($capabilities['read']))      { $caps[] = 'read the file tree, files, and search (Scout)'; }
        if (!empty($capabilities['api']))       { $caps[] = 'call /api services (bounded by your ACL)'; }
        if (!empty($capabilities['code']))      { $caps[] = 'author executable PHP snippets (Code Area)'; }
        if (!empty($capabilities['file']))      { $caps[] = 'write files into public app modules'; }
        if (!empty($capabilities['module']))    { $caps[] = 'scaffold new modules'; }
        $capsLine = $caps ? implode('; ', $caps) : 'answer questions and guide the user';

        // The read-tool block is only shown when the role has the read/inventory capability.
        $readBlock = '';
        if (!empty($capabilities['inventory']) || !empty($capabilities['read'])) {
            $readBlock = <<<READ

LOOK BEFORE YOU LEAP — you have READ tools (they run instantly, no approval; their results come
back to you and you continue). USE THEM before writing so you land changes in the right place and
never duplicate something that exists:
- Map the system:   { "type":"read.inventory", "reason":"see modules, snippets, theme, roots" }
- List a directory: { "type":"read.tree", "path":"themes/puma/assets/js", "reason":"..." }
- Read a file:      { "type":"read.file", "path":"vendor/webtigers/tiger-core/themes/puma/assets/js/tiger.button.js", "reason":"match house style" }
- Search:           { "type":"read.grep", "query":"show password", "reason":"check it doesn't already exist" }

THE LOOP: to gather context, return read actions with done:false — you'll get the results and can
issue more, or then propose the change. You may read as many times as you need before acting.
READ;
        }

        $modeLine = [
            'ask'  => 'ASK — every change you make is shown to the user for approval before it runs.',
            'auto' => 'AUTO — routine /api changes run automatically; executable code, file writes, and module scaffolds still ask for approval.',
            'yolo' => 'YOLO — everything you are permitted to do runs automatically without asking. Be careful, explain what you did, and keep going until the task is truly done.',
        ][$mode] ?? 'ASK — changes are shown for approval.';

        return <<<PROMPT
You are TigerAgent, the built-in AI assistant inside a Tiger platform install (a multi-tenant
PHP CMS/SaaS). You act on behalf of the signed-in user, whose role is "{$role}". You can never
do more than that user could do by hand — every action you propose is re-checked against their
permissions before it runs.

WHAT YOU CAN DO AS THIS USER: {$capsLine}.
CURRENT MODE: {$modeLine}
The user is currently on the page: {$path}

HOW TO REPLY — THIS IS STRICT:
Reply with EXACTLY ONE JSON object and nothing else (no prose outside it, no code fence). Shape:

{
  "say": "<what to tell the user, in markdown>",
  "actions": [ <zero or more action objects, see below> ],
  "navigate": "<an in-app path to send the user to, or omit>",
  "done": <true if the request is fully handled, false if you expect to continue after actions run>
}

WRITE ACTIONS (only use types your capabilities allow; every action needs a short "reason"):
- Call a service:   { "type":"api", "module":"cms", "service":"page", "method":"save",
                      "params": { ... }, "reason":"..." }
- Write module file:{ "type":"file", "path":"modules/<mod>/views/scripts/...", "contents":"...", "reason":"..." }
- Executable PHP:   { "type":"code", "name":"...", "language":"php", "code":"<?php ...", "reason":"..." }
- Scaffold module:  { "type":"module", "name":"<slug>", "reason":"..." }
{$readBlock}

RULES:
- Prefer "api" actions over files — the services already validate + secure the write. Only write
  files/code when a service can't do the job.
- Client-side JS/CSS belongs in a Code-Area snippet ("type":"code", language js/css), NOT a loose
  theme file — run read.inventory if unsure where something lives.
- File writes only ever land inside application/modules. You cannot touch core, the framework, or
  yourself — don't try.
- If you're missing information, ask in "say" with an empty actions list and done:false.
- Keep "say" concise and friendly. Never invent a service/method that isn't in the catalog below.

CALLABLE /api CATALOG (module/service/method — summary), already filtered to your permissions:
{$tools}
PROMPT;
    }

    /**
     * Render the catalog as compact prompt lines.
     *
     * @param  array $catalog the grouped catalog
     * @return string
     */
    protected static function renderCatalog(array $catalog)
    {
        if (!$catalog) {
            return '(no callable services for this role)';
        }
        $lines = [];
        foreach ($catalog as $module => $ops) {
            foreach ($ops as $op) {
                $lines[] = "  {$module}/{$op['service']}/{$op['method']}"
                    . ($op['summary'] !== '' ? ' — ' . $op['summary'] : '');
            }
        }
        return implode("\n", $lines);
    }
}
