<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Agent_Scout — the agent's EYES: the read twin of the Forge (TIGERAGENT.md §2b).
 *
 * The Forge writes; the Scout reads. Together they let the model do the ReAct loop the way a
 * human developer does — look before you leap: inventory what exists, read the file you're
 * about to change, grep to make sure you're not duplicating something. Scout actions ALWAYS
 * auto-run (no approval — reading is safe) and their results are fed back to the model so it
 * can query 0+ times before proposing a single write.
 *
 * Read scope is DELIBERATELY WIDER than write scope: the Scout can read app modules + themes
 * AND read-only into `vendor/webtigers/tiger-core` (so the agent learns house style — read
 * `tiger.button.js` to match it) even though the Forge can never WRITE there. Secrets are
 * excluded by construction (local.ini, *.key, storage/) regardless of role. Gated to the same
 * tier as the write it serves: `inventory` (metadata) at admin+, `tree`/`file`/`grep` at
 * superadmin+ (paired with forge.file).
 *
 * @api
 */
class Tiger_Agent_Scout
{
    const MAX_FILE_BYTES   = 24000;   // one read.file is bounded (context budget)
    const MAX_TREE_ENTRIES = 400;
    const MAX_GREP_HITS    = 60;
    const MAX_GREP_FILES   = 4000;
    const TEXT_EXT = ['php','phtml','js','css','html','ini','md','json','txt','sql','xml','yml','yaml'];

    /** @var string the acting role */
    protected $_role;

    /**
     * @param  string $role the acting user's role (for ACL decisions)
     * @return void
     */
    public function __construct($role)
    {
        $this->_role = (string) $role;
    }

    /**
     * Execute one read action and return a ledger entry. The heavy payload (file contents,
     * grep hits) rides in `feedback` — what the Loop hands back to the model — while `summary`
     * stays light for the UI + the persisted ledger.
     *
     * @param  array $action a normalized read.* action from Tiger_Agent_Contract
     * @return array         {type, reason, status, summary, feedback}
     */
    public function execute(array $action)
    {
        switch ((string) ($action['type'] ?? '')) {
            case Tiger_Agent_Contract::READ_INVENTORY: return $this->_inventory($action);
            case Tiger_Agent_Contract::READ_TREE:      return $this->_tree($action);
            case Tiger_Agent_Contract::READ_FILE:      return $this->_file($action);
            case Tiger_Agent_Contract::READ_GREP:      return $this->_grep($action);
        }
        return $this->_entry($action, 'error', 'Unknown read action.');
    }

    // ----- read.inventory ----------------------------------------------------

    /**
     * A concise map of the writable/readable surface — the agent's "repo map". Cheap enough to
     * inject on turn one so the agent is never blind: what modules exist, what Code-Area
     * snippets already exist (so it doesn't reinvent one), the active theme's asset dirs +
     * injection points, and the read/write roots.
     *
     * @param  array $action the read.inventory action
     * @return array
     */
    protected function _inventory(array $action)
    {
        if (!$this->_allowed('inventory')) {
            return $this->_entry($action, 'denied', 'Inventory requires the admin role or higher.');
        }

        $lines = [];

        // Modules
        try {
            $inactive = array_flip((array) (new Tiger_Model_Module())->inactiveSlugs());
            $mods = [];
            foreach (Tiger_Module_Discovery::all() as $slug => $m) {
                $mods[] = $slug . '(' . ($m['area'] ?? '?') . (isset($inactive[$slug]) ? ',inactive' : '') . ')';
            }
            $lines[] = 'MODULES: ' . implode(', ', $mods);
        } catch (Throwable $e) {}

        // Code-Area snippets (module files + local rows) — where JS/PHP plugins live.
        try {
            $snips = [];
            if (class_exists('Tiger_Code_Modules')) {
                foreach (Tiger_Code_Modules::all() as $key => $s) {
                    $snips[] = $key . ' [' . ($s['scope'] ?? 'global') . '] ' . ($s['label'] ?? '');
                }
            }
            if (class_exists('Tiger_Model_Code')) {
                $rows = (new Tiger_Model_Code())->fetchAll((new Tiger_Model_Code())->activeSelect()->limit(50));
                foreach ($rows as $r) {
                    $snips[] = ($r->name ?: '(untitled)') . ' [' . $r->language . '/' . $r->run_location . ']';
                }
            }
            $lines[] = 'CODE-AREA SNIPPETS (' . count($snips) . '): ' . ($snips ? implode('; ', array_slice($snips, 0, 40)) : 'none');
            $lines[] = 'NOTE: client JS/CSS belongs in a Code-Area snippet (type:code, language:js|css, run_location:frontend|admin|page, auto_insert:head|footer), NOT a loose theme file.';
        } catch (Throwable $e) {}

        // Theme + roots
        $theme = $this->_activeTheme();
        $lines[] = 'ACTIVE THEME: ' . $theme . '  (assets read-only at vendor/webtigers/tiger-core/themes/' . $theme . '/assets)';
        $lines[] = 'INJECTION POINTS: page <head> and footer (Code-Area auto_insert).';

        // The permissioned root map — [rw] = the Forge may write here; [ro] = read-only.
        $lines[] = 'ROOTS ([rw]=writable by the Forge, [ro]=read-only):';
        foreach ($this->_readRoots() as $label => $abs) {
            $lines[] = '  [' . $this->_perm($abs) . '] ' . $label . ' -> ' . $this->_rel($abs) . '/';
        }

        // The writable tree up front (where new files/modules land).
        if (defined('MODULES_PATH') && is_dir(MODULES_PATH)) {
            $mods = [];
            foreach (glob(MODULES_PATH . '/*', GLOB_ONLYDIR) ?: [] as $md) { $mods[] = basename($md) . '/'; }
            $lines[] = 'WRITABLE MODULES [rw] (application/modules): ' . ($mods ? implode(', ', $mods) : '(none yet — scaffold one)');
        }

        $text = implode("\n", $lines);
        return $this->_entry($action, 'done', 'Read the system inventory (' . count($lines) . ' facts).', $text);
    }

    // ----- read.tree ---------------------------------------------------------

    /**
     * List file/dir names under a scoped path (names, not contents). Defaults to a synthesized
     * top-level view of the roots when no path is given.
     *
     * @param  array $action the read.tree action (optional `path`)
     * @return array
     */
    protected function _tree(array $action)
    {
        if (!$this->_allowed('read')) {
            return $this->_entry($action, 'denied', 'Reading the file tree requires the superadmin role or higher.');
        }
        $path = (string) ($action['path'] ?? '');

        // No path → a permissioned map of every root with a shallow listing under each.
        if ($path === '') {
            $out = ['ROOT MAP ([rw] = the Forge may write here, [ro] = read-only):'];
            foreach ($this->_readRoots() as $label => $abs) {
                $out[] = '[' . $this->_perm($abs) . '] ' . $label . '  (' . $this->_rel($abs) . '/)';
                foreach (array_slice($this->_children($abs), 0, 40) as $c) {
                    $out[] = '      ' . $c;
                }
            }
            return $this->_entry($action, 'done', 'Mapped the read roots with permissions.', implode("\n", $out));
        }

        $abs = $this->_resolve($path);
        if ($abs === null) {
            return $this->_entry($action, 'denied', 'Path is outside the readable surface (or excluded).');
        }
        if (is_file($abs)) {
            return $this->_entry($action, 'done', 'That path is a file.',
                '[' . $this->_perm($abs) . '] FILE ' . $this->_rel($abs) . ' (' . filesize($abs) . ' bytes) — use read.file to see it.');
        }

        // Deep listing. Every entry under one root shares that root's permission, so it's stated
        // once in the header rather than repeated per line.
        $perm = $this->_perm($abs);
        $entries = [];
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($abs, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $f) {
            if ($this->_denied($f->getPathname())) { continue; }
            $rel = ltrim(str_replace($abs, '', $f->getPathname()), '/');
            $entries[] = $rel . ($f->isDir() ? '/' : ' (' . $f->getSize() . 'b)');
            if (count($entries) >= self::MAX_TREE_ENTRIES) { $entries[] = '… (truncated)'; break; }
        }
        sort($entries);
        $header = 'TREE [' . $perm . '] ' . $this->_rel($abs) . '/  — all '
                . count($entries) . ' entries below are ' . ($perm === 'rw' ? 'WRITABLE by the Forge' : 'READ-ONLY') . ':';
        return $this->_entry($action, 'done', 'Listed ' . count($entries) . ' [' . $perm . '] entries under ' . $this->_rel($abs) . '.',
            $header . "\n  " . implode("\n  ", $entries));
    }

    /** The immediate child names of a directory (dirs suffixed '/'), denied paths skipped. */
    protected function _children($abs)
    {
        $out = [];
        foreach (glob(rtrim($abs, '/') . '/*') ?: [] as $p) {
            if ($this->_denied($p)) { continue; }
            $out[] = basename($p) . (is_dir($p) ? '/' : '');
        }
        sort($out);
        return $out;
    }

    /**
     * The agent's effective permission on a path: `rw` when it sits under the Forge's writable
     * root (application/modules), else `ro`. This is the "permission" the AI needs — what it may
     * write vs only read — not the unix mode.
     *
     * @param  string $abs an absolute path
     * @return string      'rw' | 'ro'
     */
    protected function _perm($abs)
    {
        if (defined('MODULES_PATH')) {
            $rw   = realpath(MODULES_PATH);
            $real = realpath($abs);
            if ($real === false) { $real = $abs; }
            if ($rw !== false && strpos($real, $rw) === 0) {
                return 'rw';
            }
        }
        return 'ro';
    }

    // ----- read.file ---------------------------------------------------------

    /**
     * Return a file's contents (bounded).
     *
     * @param  array $action the read.file action (`path` required)
     * @return array
     */
    protected function _file(array $action)
    {
        if (!$this->_allowed('read')) {
            return $this->_entry($action, 'denied', 'Reading a file requires the superadmin role or higher.');
        }
        $abs = $this->_resolve((string) ($action['path'] ?? ''));
        if ($abs === null || !is_file($abs)) {
            return $this->_entry($action, 'denied', 'File not found in the readable surface (or excluded).');
        }
        $bytes = (int) filesize($abs);
        $data  = (string) file_get_contents($abs, false, null, 0, self::MAX_FILE_BYTES);
        $trunc = $bytes > self::MAX_FILE_BYTES;
        $lines = substr_count($data, "\n") + 1;

        $body = 'FILE ' . $this->_rel($abs) . ' (' . $bytes . ' bytes, ' . $lines . ' lines'
              . ($trunc ? ', TRUNCATED to first ' . self::MAX_FILE_BYTES . ' bytes' : '') . "):\n"
              . "```\n" . $data . "\n```";
        return $this->_entry($action, 'done', 'Read ' . $this->_rel($abs) . ' (' . $lines . ' lines).', $body);
    }

    // ----- read.grep ---------------------------------------------------------

    /**
     * Search the surface for a string — files under a scoped path (or the defaults) AND the
     * Code-Area snippet bodies (the crucial "does this JS already exist?" check).
     *
     * @param  array $action the read.grep action (`query` required, optional `path`)
     * @return array
     */
    protected function _grep(array $action)
    {
        if (!$this->_allowed('read')) {
            return $this->_entry($action, 'denied', 'Searching requires the superadmin role or higher.');
        }
        $query = trim((string) ($action['query'] ?? ''));
        if ($query === '') {
            return $this->_entry($action, 'error', 'A search query is required.');
        }
        $needle = strtolower($query);
        $hits = [];

        // 1. Code-Area snippets first (where plugins live).
        try {
            if (class_exists('Tiger_Model_Code')) {
                $rows = (new Tiger_Model_Code())->fetchAll((new Tiger_Model_Code())->activeSelect()->limit(200));
                foreach ($rows as $r) {
                    $hay = strtolower(($r->name ?? '') . ' ' . ($r->description ?? '') . ' ' . ($r->code ?? ''));
                    if (strpos($hay, $needle) !== false) {
                        $hits[] = 'code-area snippet: "' . ($r->name ?: '(untitled)') . '" [' . $r->language . ']';
                    }
                }
            }
        } catch (Throwable $e) {}

        // 2. Files.
        $bases = [];
        if (!empty($action['path'])) {
            $abs = $this->_resolve((string) $action['path']);
            if ($abs !== null) { $bases[] = $abs; }
        }
        if (!$bases) {
            $bases = [MODULES_PATH];
            if (defined('TIGER_CORE_PATH')) { $bases[] = TIGER_CORE_PATH . '/themes/' . $this->_activeTheme() . '/assets'; }
        }
        $scanned = 0;
        foreach ($bases as $base) {
            if (!is_dir($base) || count($hits) >= self::MAX_GREP_HITS) { break; }
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
            foreach ($it as $f) {
                if ($scanned >= self::MAX_GREP_FILES || count($hits) >= self::MAX_GREP_HITS) { break; }
                if (!$f->isFile() || $this->_denied($f->getPathname())) { continue; }
                $ext = strtolower($f->getExtension());
                if (!in_array($ext, self::TEXT_EXT, true)) { continue; }
                $scanned++;
                $n = 0;
                foreach (@file($f->getPathname(), FILE_IGNORE_NEW_LINES) ?: [] as $i => $line) {
                    if (stripos($line, $query) !== false) {
                        $hits[] = $this->_rel($f->getPathname()) . ':' . ($i + 1) . ': ' . trim(substr($line, 0, 160));
                        if (++$n >= 3 || count($hits) >= self::MAX_GREP_HITS) { break; }
                    }
                }
            }
        }

        $body = $hits
            ? 'GREP "' . $query . '" — ' . count($hits) . " match(es):\n  " . implode("\n  ", $hits)
            : 'GREP "' . $query . '" — no matches. (Safe to create it — nothing like this exists.)';
        return $this->_entry($action, 'done', count($hits) . ' match(es) for "' . $query . '".', $body);
    }

    // ----- scope + helpers ---------------------------------------------------

    /** The named read roots (label => absolute), all guaranteed to exist. */
    protected function _readRoots()
    {
        $roots = [];
        if (defined('MODULES_PATH') && is_dir(MODULES_PATH)) { $roots['app-modules'] = MODULES_PATH; }
        if (defined('APPLICATION_PATH') && is_dir(APPLICATION_PATH . '/themes')) { $roots['app-themes'] = APPLICATION_PATH . '/themes'; }
        if (defined('TIGER_CORE_PATH')) {
            foreach (['modules' => '/modules', 'core-themes' => '/themes', 'core-library' => '/library/Tiger'] as $k => $sub) {
                if (is_dir(TIGER_CORE_PATH . $sub)) { $roots[$k] = TIGER_CORE_PATH . $sub; }
            }
        }
        return $roots;
    }

    /**
     * Resolve a model-supplied path to an absolute path under an allowed read root, or null.
     * Tries the path both app-root-relative and core-relative for convenience.
     *
     * @param  string $path the requested path
     * @return string|null
     */
    protected function _resolve($path)
    {
        $path = ltrim(str_replace('\\', '/', (string) $path), '/');
        if ($path === '' || strpos($path, '..') !== false || strpos($path, "\0") !== false) {
            return null;
        }
        $bases = [];
        if (defined('APPLICATION_ROOT')) { $bases[] = APPLICATION_ROOT; }
        if (defined('TIGER_CORE_PATH'))  { $bases[] = TIGER_CORE_PATH; }
        if (defined('APPLICATION_PATH')) { $bases[] = APPLICATION_PATH; }

        foreach ($bases as $base) {
            $real = realpath($base . '/' . $path);
            if ($real !== false && $this->_underReadRoot($real) && !$this->_denied($real)) {
                return $real;
            }
        }
        return null;
    }

    /** True when $real sits inside one of the allowed read roots. */
    protected function _underReadRoot($real)
    {
        foreach ($this->_readRoots() as $root) {
            $rr = realpath($root);
            if ($rr !== false && strpos($real, $rr) === 0) {
                return true;
            }
        }
        return false;
    }

    /** Secrets + noise excluded regardless of role/scope. */
    protected function _denied($abs)
    {
        $b = strtolower(basename($abs));
        if ($b === 'local.ini' || $b === '.env' || substr($b, -4) === '.key' || substr($b, -4) === '.pem') {
            return true;
        }
        $p = str_replace('\\', '/', $abs);
        return strpos($p, '/storage/') !== false || strpos($p, '/.git/') !== false || strpos($p, '/node_modules/') !== false;
    }

    /** The active theme name (config, defaulting to puma). */
    protected function _activeTheme()
    {
        if (Zend_Registry::isRegistered('Zend_Config')) {
            $t = Zend_Registry::get('Zend_Config')->get('tiger');
            $t = $t ? $t->get('theme') : null;
            $name = $t ? $t->get('name') : null;
            if (is_scalar($name) && $name !== '') { return (string) $name; }
        }
        return 'puma';
    }

    /** ACL gate for a Scout privilege against the acting role. */
    protected function _allowed($privilege)
    {
        if (!Zend_Registry::isRegistered('Zend_Acl')) {
            return false;
        }
        $acl = Zend_Registry::get('Zend_Acl');
        if (!$acl->has('Tiger_Agent_Scout')) {
            return false;
        }
        return $acl->isAllowed($this->_role, 'Tiger_Agent_Scout', $privilege);
    }

    /** Path relative to the app root, for readable output. */
    protected function _rel($abs)
    {
        if (defined('APPLICATION_ROOT') && strpos($abs, APPLICATION_ROOT) === 0) {
            return ltrim(substr($abs, strlen(APPLICATION_ROOT)), '/');
        }
        return $abs;
    }

    /** Build a ledger entry; `feedback` is the model-facing payload (stripped before persist). */
    protected function _entry(array $action, $status, $summary, $feedback = '')
    {
        return [
            'type'     => $action['type'] ?? '',
            'reason'   => $action['reason'] ?? '',
            'status'   => $status,
            'summary'  => $summary,
            'feedback' => $feedback !== '' ? $feedback : $summary,
            'action'   => ['type' => $action['type'] ?? '', 'path' => $action['path'] ?? '', 'query' => $action['query'] ?? ''],
        ];
    }
}
