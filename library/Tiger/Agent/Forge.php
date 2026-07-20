<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Agent_Forge — the permission-gated hands of the agent (TIGERAGENT.md §0, §2a, §3).
 *
 * The model only ever *proposes* (Tiger_Agent_Contract). The Forge is the ONLY thing that
 * touches the system, and it does so under the acting user's authority — never the model's.
 * Every action is gated twice: by the ACL (deny-by-default, the same gate a human hits) and
 * by the human-in-the-loop rule (reads auto-run; writes wait for an explicit approval).
 *
 * The escalating tiers fall straight out of the ACL, so "capability = role" needs no role
 * string compare anywhere:
 *
 *   - api    → dispatch another /api service AS THE USER; allowed iff that call's own ACL
 *              allows it (a content editor's proposed `access/user/create` is simply denied).
 *   - code   → author a Code Area snippet (executable PHP); gated by Code_Service_Code (superadmin+).
 *   - file   → write a file into a PUBLIC APP MODULE; gated by the Forge `file` privilege
 *              (superadmin+). Sandboxed to MODULES_PATH — so it can never touch core or the
 *              agent itself, which live in vendor/ (the boundary is physical, not a check).
 *   - module → scaffold a new module; gated by the Forge `module` privilege (developer).
 *
 * Each execute() returns a ledger entry: {type, summary, status, detail, reason}. `status` is
 * `done` | `error` | `proposed` (awaiting approval) | `denied` (ACL refused). Nothing here is
 * a sandbox for untrusted PHP — like the Code Area (CODE.md §5), it's accountability + consent
 * behind superadmin, stated honestly.
 *
 * @api
 */
class Tiger_Agent_Forge
{
    /** api methods that only READ — auto-run without approval (everything else is a write). */
    const READ_VERBS = [
        'get', 'list', 'datatable', 'search', 'find', 'view', 'show', 'read',
        'test', 'options', 'discover', 'history', 'conversations', 'load', 'preview', 'count',
        'scan', 'inspect', 'report',
    ];

    /**
     * The auto-approve mode RANK an action needs before the Loop may run it without asking:
     * 0 = auto-approvable in `auto` mode (a guarded, reversible /api write); 1 = only in `yolo`
     * (the sharp tiers — executable PHP, raw file writes, module scaffolds). Read-class /api
     * calls return -1 (always auto). This is how auto-mode raises the approval threshold by
     * TIER without ever raising the ACL ceiling (TIGERAGENT.md §3a).
     *
     * @param  array $action a normalized write action
     * @return int           -1 (always) | 0 (auto+) | 1 (yolo only)
     */
    public static function autoRank(array $action)
    {
        switch ((string) ($action['type'] ?? '')) {
            case Tiger_Agent_Contract::ACTION_API:
                return in_array(strtolower((string) ($action['method'] ?? '')), self::READ_VERBS, true) ? -1 : 0;
            case Tiger_Agent_Contract::ACTION_CODE:
            case Tiger_Agent_Contract::ACTION_FILE:
            case Tiger_Agent_Contract::ACTION_MODULE:
                return 1;
        }
        return -1;
    }

    /** @var string the acting role */
    protected $_role;

    /**
     * @param  string $role the acting user's role (for ACL decisions)
     * @return void
     */
    public function __construct($role)
    {
        $this->_role = (string) $role;

        // Internal /api calls the Forge dispatches are same-request, server-side, and already
        // rode behind the aside POST's own CSRF + ACL. Flag the request stateless so the
        // downstream service's form doesn't demand a fresh CSRF token (Tiger_Form §82).
        Zend_Registry::set('tiger.auth.stateless', true);
    }

    /**
     * Execute (or refuse, or defer) one proposed action.
     *
     * @param  array $action a normalized action from Tiger_Agent_Contract
     * @return array         the ledger entry {type, summary, status, detail, reason}
     */
    public function execute(array $action)
    {
        $type = (string) ($action['type'] ?? '');
        switch ($type) {
            case Tiger_Agent_Contract::ACTION_API:    return $this->_api($action);
            case Tiger_Agent_Contract::ACTION_CODE:   return $this->_code($action);
            case Tiger_Agent_Contract::ACTION_FILE:   return $this->_file($action);
            case Tiger_Agent_Contract::ACTION_MODULE: return $this->_module($action);
        }
        return $this->_entry($action, 'error', 'Unknown action type.');
    }

    // ----- api ---------------------------------------------------------------

    /**
     * Dispatch another /api service as the acting user. A read runs immediately; a write
     * runs only when the action is approved. Either way the target service's own ACL is the
     * hard gate — the agent can reach exactly what the user could reach by hand.
     *
     * @param  array $action the api action
     * @return array
     */
    protected function _api(array $action)
    {
        $className = ucfirst($action['module']) . '_Service_' . ucfirst($action['service']);
        $method    = (string) $action['method'];
        $isWrite   = !in_array(strtolower($method), self::READ_VERBS, true);

        if (!$this->_aclAllows($className, $method)) {
            return $this->_entry($action, 'denied',
                "You don't have permission to run {$action['module']}/{$action['service']}/{$method}.");
        }
        if ($isWrite && empty($action['approved'])) {
            return $this->_entry($action, 'proposed',
                "Will call {$action['module']}/{$action['service']}/{$method}.");
        }
        if (!class_exists($className, true) || !is_subclass_of($className, 'Tiger_Service_Service')) {
            return $this->_entry($action, 'error', "Service {$className} is not available.");
        }

        try {
            $params   = array_merge((array) $action['params'], ['action' => $method]);
            $response = (new $className($params))->getResponse();
            $ok       = ((int) ($response->result ?? 0) === 1);
            $detail   = $this->_messages($response);
            return $this->_entry(
                $action,
                $ok ? 'done' : 'error',
                ($ok ? 'Called ' : 'Call failed: ') . "{$action['module']}/{$action['service']}/{$method}"
                    . ($detail !== '' ? ' — ' . $detail : ''),
                ['result' => $response->result ?? 0, 'data' => $response->data ?? null, 'redirect' => $response->redirect ?? null]
            );
        } catch (Throwable $e) {
            return $this->_entry($action, 'error', 'Call threw: ' . $e->getMessage());
        }
    }

    // ----- code (executable PHP via the Code Area) ---------------------------

    /**
     * Author a Code Area snippet through the existing, hardened Code service (it lints,
     * versions, and rebuilds the validated bundle — CODE.md §4). Always a write, always
     * approval-gated, and gated by Code_Service_Code's own superadmin ACL.
     *
     * @param  array $action the code action
     * @return array
     */
    protected function _code(array $action)
    {
        if (!$this->_aclAllows('Code_Service_Code', 'save')) {
            return $this->_entry($action, 'denied', 'Writing executable code requires the developer/superadmin role.');
        }
        if (empty($action['approved'])) {
            return $this->_entry($action, 'proposed', "Will add a {$action['language']} snippet “{$action['name']}”.");
        }
        if (!class_exists('Code_Service_Code', true)) {
            return $this->_entry($action, 'error', 'The Code Area module is not installed.');
        }
        try {
            $response = (new Code_Service_Code([
                'action'   => 'save',
                'name'     => $action['name'],
                'language' => $action['language'],
                'code'     => $action['code'],
                'active'   => 0,               // authored INACTIVE — activation stays a deliberate human step
            ]))->getResponse();
            $ok = ((int) ($response->result ?? 0) === 1);
            return $this->_entry($action, $ok ? 'done' : 'error',
                ($ok ? 'Saved snippet (inactive): ' : 'Could not save snippet: ') . $action['name']
                    . ' — ' . $this->_messages($response));
        } catch (Throwable $e) {
            return $this->_entry($action, 'error', 'Snippet save threw: ' . $e->getMessage());
        }
    }

    // ----- file (write into a public app module) -----------------------------

    /**
     * Write a file into a PUBLIC APP MODULE. Sandboxed to MODULES_PATH: the resolved target
     * must sit under application/modules — so core, the library, themes, and the agent's own
     * module (all in vendor/) are unreachable by construction. PHP bodies are lint-checked
     * before they land. Always approval-gated.
     *
     * @param  array $action the file action
     * @return array
     */
    protected function _file(array $action)
    {
        if (!$this->_aclAllows(Tiger_Agent::RESOURCE_FORGE, 'file')) {
            return $this->_entry($action, 'denied', 'Writing module files requires the developer/superadmin role.');
        }
        if (empty($action['approved'])) {
            return $this->_entry($action, 'proposed', "Will write {$action['path']}.");
        }

        $abs = $this->_resolveModulePath((string) $action['path']);
        if ($abs === null) {
            return $this->_entry($action, 'denied',
                'Refused — the Forge only writes inside application/modules, never core or itself.');
        }

        // Lint a PHP body before it can hit disk (a parse error is refused, never written).
        if (preg_match('/\.php$/i', $abs) && !$this->_lintPhp((string) $action['contents'], $err)) {
            return $this->_entry($action, 'error', 'Refused — PHP would not parse: ' . $err);
        }

        try {
            $dir = dirname($abs);
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                return $this->_entry($action, 'error', 'Could not create ' . $this->_rel($dir));
            }
            if (file_put_contents($abs, (string) $action['contents']) === false) {
                return $this->_entry($action, 'error', 'Could not write ' . $this->_rel($abs));
            }
            Tiger_Log::info('agent.forge.file', ['path' => $this->_rel($abs), 'role' => $this->_role]);
            return $this->_entry($action, 'done', 'Wrote ' . $this->_rel($abs), ['path' => $this->_rel($abs)]);
        } catch (Throwable $e) {
            return $this->_entry($action, 'error', 'Write threw: ' . $e->getMessage());
        }
    }

    // ----- module (scaffold) -------------------------------------------------

    /**
     * Scaffold a new module. Delegates to the platform scaffolder when present; otherwise
     * lays down the minimal skeleton directly inside the sandbox. Developer-gated.
     *
     * @param  array $action the module action
     * @return array
     */
    protected function _module(array $action)
    {
        if (!$this->_aclAllows(Tiger_Agent::RESOURCE_FORGE, 'module')) {
            return $this->_entry($action, 'denied', 'Scaffolding a module requires the developer role.');
        }
        if (empty($action['approved'])) {
            return $this->_entry($action, 'proposed', "Will scaffold module “{$action['name']}”.");
        }

        $name = (string) $action['name'];
        if ($name === '' || !defined('MODULES_PATH')) {
            return $this->_entry($action, 'error', 'A valid module name and a writable modules dir are required.');
        }
        $base = MODULES_PATH . '/' . $name;
        if (is_dir($base)) {
            return $this->_entry($action, 'error', "A module named “{$name}” already exists.");
        }
        try {
            if (class_exists('Tiger_Generator_Module')) {
                // The canonical scaffolder — the SAME output as `bin/tiger make:module`
                // (Bootstrap + IndexController + an example /api service + acl + views),
                // written into app modules. It validates the name + refuses an existing dir.
                $created = Tiger_Generator_Module::generate($name, MODULES_PATH);
                $summary = "Scaffolded module “{$name}” — " . count($created) . ' files (activate it under Modules).';
            } else {
                $this->_minimalScaffold($base, $name);   // fallback for a stripped build with no generator
                $summary = "Scaffolded module “{$name}” (minimal skeleton; activate it under Modules).";
            }
            Tiger_Log::info('agent.forge.module', ['name' => $name, 'role' => $this->_role]);
            return $this->_entry($action, 'done', $summary, ['path' => 'application/modules/' . $name]);
        } catch (Throwable $e) {
            return $this->_entry($action, 'error', 'Scaffold failed: ' . $e->getMessage());
        }
    }

    // ----- helpers -----------------------------------------------------------

    /**
     * ACL gate for a Forge decision — the acting role against a resource/privilege.
     *
     * @param  string $resource  the resource class
     * @param  string $privilege the privilege
     * @return bool
     */
    protected function _aclAllows($resource, $privilege)
    {
        if (!Zend_Registry::isRegistered('Zend_Acl')) {
            return false;
        }
        $acl = Zend_Registry::get('Zend_Acl');
        if (!$acl->has($resource)) {
            return false;
        }
        return $acl->isAllowed($this->_role, $resource, $privilege);
    }

    /**
     * Resolve a model-supplied path to an absolute path INSIDE the app-modules sandbox, or
     * null if it escapes. Accepts paths written with or without a leading `modules/`.
     *
     * @param  string $path the requested path
     * @return string|null   the safe absolute path, or null when out of bounds
     */
    protected function _resolveModulePath($path)
    {
        if (!defined('MODULES_PATH')) {
            return null;
        }
        $root = rtrim(MODULES_PATH, '/');
        $rel  = ltrim(str_replace('\\', '/', $path), '/');
        // Tolerate a leading "modules/" or "application/modules/" the model may have added.
        $rel  = preg_replace('#^(application/)?modules/#', '', $rel);

        if ($rel === '' || strpos($rel, '..') !== false || strpos($rel, "\0") !== false) {
            return null;
        }
        $abs = $root . '/' . $rel;

        // Canonicalize the deepest existing ancestor and confirm it stays under the root
        // (defends against symlink/`..` escapes even before the file exists).
        $probe = $abs;
        while (!file_exists($probe) && $probe !== $root && $probe !== dirname($probe)) {
            $probe = dirname($probe);
        }
        $realProbe = realpath($probe);
        $realRoot  = realpath($root);
        if ($realRoot === false || $realProbe === false || strpos($realProbe, $realRoot) !== 0) {
            return null;
        }
        return $abs;
    }

    /**
     * Lint a PHP body out-of-process (same gate the Code Area uses).
     *
     * @param  string      $code the PHP source
     * @param  string|null $err  set to the parser error on failure
     * @return bool
     */
    protected function _lintPhp($code, &$err = null)
    {
        // Reuse the model's linter when available (identical behavior to the Code Area).
        if (class_exists('Tiger_Model_Code', true)) {
            $res = (new Tiger_Model_Code())->lint($code);
            if (empty($res['ok'])) { $err = (string) ($res['error'] ?? 'parse error'); return false; }
            return true;
        }
        $err = null;
        return true;
    }

    /** Path relative to the app root, for user-facing detail. */
    protected function _rel($abs)
    {
        if (defined('APPLICATION_ROOT') && strpos($abs, APPLICATION_ROOT) === 0) {
            return ltrim(substr($abs, strlen(APPLICATION_ROOT)), '/');
        }
        return $abs;
    }

    /** Flatten a response's messages into one human string. */
    protected function _messages($response)
    {
        $out = [];
        foreach ((array) ($response->messages ?? []) as $m) {
            $text = is_object($m) ? ($m->message ?? '') : (is_array($m) ? ($m['message'] ?? '') : (string) $m);
            if ($text !== '') { $out[] = $text; }
        }
        return implode('; ', $out);
    }

    /** Build a ledger entry, preserving the action so approval can re-run it. */
    protected function _entry(array $action, $status, $summary, array $detail = [])
    {
        return [
            'type'    => $action['type'] ?? '',
            'reason'  => $action['reason'] ?? '',
            'status'  => $status,
            'summary' => $summary,
            'detail'  => $detail,
            'action'  => $action,   // the original proposal, so approve() can execute it verbatim
        ];
    }

    /**
     * The minimal module skeleton used when no platform scaffolder is present — enough to
     * be a real, activatable module (Bootstrap + a controller + a view + acl).
     *
     * @param  string $base the module dir
     * @param  string $name the module slug
     * @return void
     */
    protected function _minimalScaffold($base, $name)
    {
        $Class = ucfirst($name);
        mkdir($base . '/controllers', 0755, true);
        mkdir($base . '/views/scripts/index', 0755, true);
        mkdir($base . '/configs', 0755, true);

        file_put_contents($base . '/Bootstrap.php',
            "<?php\nclass {$Class}_Bootstrap extends Zend_Application_Module_Bootstrap {}\n");
        file_put_contents($base . '/controllers/IndexController.php',
            "<?php\nclass {$Class}_IndexController extends Zend_Controller_Action\n{\n    public function indexAction() {}\n}\n");
        file_put_contents($base . '/views/scripts/index/index.phtml',
            "<h1>" . htmlspecialchars($Class) . "</h1>\n<p>Scaffolded by TigerAgent.</p>\n");
        file_put_contents($base . '/configs/acl.ini',
            "[production]\nacl.resources.{$name}_index_ctrl.resource = \"{$Class}_IndexController\"\n"
            . "acl.rules.{$name}_index_ctrl.role = \"guest\"\n"
            . "acl.rules.{$name}_index_ctrl.resource = \"{$Class}_IndexController\"\n"
            . "acl.rules.{$name}_index_ctrl.permission = \"allow\"\n\n"
            . "[staging : production]\n[testing : production]\n[development : production]\n");
    }
}
