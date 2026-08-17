<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * build-capabilities.php — generate CAPABILITIES.md, the agent-first "what already exists, and where" map.
 *
 * Tiger is large; an agent (or human) needs to answer "is X already built?" with a lookup, not a guess.
 * This scans every `Tiger_*` class in `library/Tiger` + every module, reads the ONE-LINE docblock summary
 * (the AGENTS.md convention: `Tiger_X — <what this IS>.`) + its `@api`/`@internal` tag, groups by
 * CAPABILITY (not by layer), and writes a committed, greppable CAPABILITIES.md at the repo root.
 *
 * Token-based — no app boot, no autoload (can't fatal on a class's dependencies). Because it's generated
 * from the docblocks we already write, it can't drift; a CI check regenerates it and fails a stale diff.
 *
 *   Usage:  php bin/build-capabilities.php            # writes CAPABILITIES.md
 *           php bin/build-capabilities.php --check     # exit 1 if CAPABILITIES.md is stale (CI)
 */

$root = dirname(__DIR__);
$check = in_array('--check', $argv, true);

// Capability map — ordered [label, [class-name prefixes/exact]]. FIRST match wins, so specific
// entries precede general ones (e.g. Tiger_Model_Media -> Media, before the Tiger_Model_ catch-all).
// It maps NAMESPACES (which are architectural and don't churn), so it's small + stable; a class that
// matches nothing lands in "Other" — a visible signal to add it here, not silent drift.
$CAPS = [
    ['Kernel / bootstrap',            ['Tiger_Application', 'Tiger_Version']],
    ['Authentication',                ['Tiger_Auth_', 'Tiger_Service_Authentication', 'Tiger_Model_AuthChallenge', 'Tiger_Model_UserCredential', 'Tiger_Model_Password', 'Tiger_Model_Login', 'Tiger_Validate_Password', 'Tiger_Policy_Password', 'Tiger_Service_Token', 'Tiger_Model_Token']],
    ['Authorization (ACL)',           ['Tiger_Acl_', 'Tiger_Model_AclResource', 'Tiger_Model_AclRole', 'Tiger_Model_AclRule', 'Tiger_Model_Policy']],
    ['Identity & tenancy',            ['Tiger_Model_User', 'Tiger_Model_Org', 'Tiger_Model_Profile', 'Tiger_Model_Contact', 'Tiger_Model_Address', 'Tiger_Profile_', 'Tiger_Account_']],
    ['Crypto & secrets',              ['Tiger_Crypto', 'Tiger_Security']],
    ['Web services (/api)',           ['Tiger_Ajax_', 'Tiger_Service_Service', 'Tiger_Model_ResponseObject', 'Tiger_Model_MessageObject', 'Tiger_Service_Validate']],
    ['API discovery (OpenAPI)',       ['Tiger_OpenApi_']],
    ['CMS / content',                 ['Tiger_Cms_', 'Tiger_Model_Page', 'Tiger_Model_Sitemap']],
    ['Media',                         ['Tiger_Media_', 'Tiger_Model_Media']],
    ['Theming',                       ['Tiger_Theme']],
    ['Menus',                         ['Tiger_Menu']],
    ['Site search',                   ['Tiger_Search']],
    ['Custom fields',                 ['Tiger_Fields']],
    ['Modules, marketplace & updates',['Tiger_Module_', 'Tiger_License_', 'Tiger_Vendor', 'Tiger_Update', 'Tiger_Model_Module', 'Tiger_Model_UpdateHistory', 'Tiger_Generator']],
    ['Install & first-run',           ['Tiger_Install']],
    ['Config, i18n & options',        ['Tiger_I18n_', 'Tiger_Model_Config', 'Tiger_Model_Translation', 'Tiger_Model_Option']],
    ['Mail',                          ['Tiger_Mail']],
    ['Location',                      ['Tiger_Location', 'Tiger_Service_Location']],
    ['Logging',                       ['Tiger_Log']],
    ['Sessions',                      ['Tiger_Session']],
    ['AI agent',                      ['Tiger_Agent']],
    ['Agent skills',                  ['Tiger_Skill_']],
    ['MCP server',                    ['Tiger_Mcp']],
    ['Scheduling',                    ['Tiger_Schedule', 'Tiger_Model_ScheduleRun']],
    ['Backup',                        ['Tiger_Backup', 'Tiger_Model_Backup']],
    ['Code area',                     ['Tiger_Code', 'Tiger_Model_Code']],
    ['Audience, analytics & consent', ['Tiger_Audience', 'Tiger_Consent', 'Tiger_Tracking', 'Tiger_Google', 'Tiger_Model_Consent', 'Tiger_Model_Tracking']],
    ['SEO & accessibility',           ['Tiger_Sitemap', 'Tiger_Ally']],
    ['Admin shell & dashboard',       ['Tiger_Admin_', 'Tiger_Dashboard', 'Tiger_Controller_Admin', 'Tiger_Controller_Account']],
    ['Forms & views',                 ['Tiger_Form', 'Tiger_View_', 'Tiger_Validate_', 'Tiger_Filter_', 'Tiger_Recaptcha']],
    ['Routing & controllers',         ['Tiger_Routing_', 'Tiger_Controller_']],
    ['Data layer (base)',             ['Tiger_Model_Table', 'Tiger_Model_', 'Tiger_Db_', 'Tiger_Uuid']],
];

/** The capability label for a class (first matching map entry), else 'Other'. */
function cap_for(string $class): string {
    foreach ($GLOBALS['CAPS'] as [$label, $prefixes]) {
        foreach ($prefixes as $p) {
            if ($class === $p || strpos($class, $p) === 0) { return $label; }
        }
    }
    return 'Other (unmapped — add to $CAPS)';
}

/** Pull the one-line "what this IS" summary + @api status from a class docblock. */
function parse_doc(string $doc, string $class): array {
    $status = preg_match('/@api\b/', $doc) ? '@api' : (preg_match('/@internal\b/', $doc) ? '@internal' : '');
    $lines = [];
    foreach (explode("\n", $doc) as $l) {
        $l = preg_replace('#^\s*/\*\*+#', '', $l);
        $l = preg_replace('#\*/\s*$#', '', $l);
        $l = preg_replace('#^\s*\*\s?#', '', $l);
        $lines[] = trim($l);
    }
    // text up to the first @tag paragraph
    $text = trim(implode(' ', array_filter($lines, static fn($x) => $x !== '')));
    $text = preg_split('/\s+@[a-zA-Z]/', $text)[0];
    // drop the "ClassName — " prefix (em-dash / en-dash / hyphen)
    if (preg_match('/' . preg_quote($class, '/') . '\s*[\x{2014}\x{2013}-]\s*(.+)/u', $text, $m)) {
        $text = $m[1];
    }
    // first sentence
    if (preg_match('/^(.+?[.!?])(\s|$)/u', $text, $m)) { $text = $m[1]; }
    $text = trim($text);
    if (mb_strlen($text) > 180) { $text = rtrim(mb_substr($text, 0, 177)) . '…'; }
    return [$text, $status];
}

/** Top-level classes (name => [summary, status]) declared in a PHP file, via tokens (no execution). */
function classes_in(string $file): array {
    $src = @file_get_contents($file);
    if ($src === false) { return []; }
    $tokens = token_get_all($src);
    $out = [];
    $lastDoc = '';
    $n = count($tokens);
    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];
        if (!is_array($t)) { continue; }
        if ($t[0] === T_DOC_COMMENT) { $lastDoc = $t[1]; continue; }
        if (in_array($t[0], [T_CLASS, T_INTERFACE, T_TRAIT], true)) {
            // skip ::class
            $p = $i - 1;
            while ($p >= 0 && is_array($tokens[$p]) && $tokens[$p][0] === T_WHITESPACE) { $p--; }
            if ($p >= 0 && is_array($tokens[$p]) && $tokens[$p][0] === T_DOUBLE_COLON) { continue; }
            for ($j = $i + 1; $j < $n; $j++) {
                if (is_array($tokens[$j]) && $tokens[$j][0] === T_STRING) {
                    $out[$tokens[$j][1]] = parse_doc($lastDoc, $tokens[$j][1]);
                    break;
                }
            }
            $lastDoc = '';
        }
    }
    return $out;
}

// --- Scan library/Tiger -> capability buckets --------------------------------------------------
$buckets = [];   // capability => [ [class, summary, status, relpath], ... ]
$libDir  = $root . '/library/Tiger';
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($libDir, FilesystemIterator::SKIP_DOTS));
$total = 0;
foreach ($it as $f) {
    if (!$f->isFile() || strtolower($f->getExtension()) !== 'php') { continue; }
    $rel = 'library/' . str_replace('\\', '/', substr($f->getPathname(), strlen($root . '/library/')));
    foreach (classes_in($f->getPathname()) as $class => [$summary, $status]) {
        if (strpos($class, 'Tiger_') !== 0) { continue; }
        $buckets[cap_for($class)][] = [$class, $summary, $status, $rel];
        $total++;
    }
}

// --- Scan modules ------------------------------------------------------------------------------
$modules = [];
foreach (glob($root . '/modules/*', GLOB_ONLYDIR) ?: [] as $dir) {
    $slug = basename($dir);
    $man = [];
    foreach (['module.json', 'theme.json'] as $mf) {
        if (is_file("$dir/$mf")) { $man = json_decode((string) file_get_contents("$dir/$mf"), true) ?: []; break; }
    }
    $services = array_map(static fn($f) => basename($f, '.php'), glob("$dir/services/*.php") ?: []);
    $modules[$slug] = [
        'name'     => (string) ($man['name'] ?? ucfirst($slug)),
        'type'     => (string) ($man['type'] ?? ''),
        'desc'     => (string) ($man['description'] ?? ''),
        'services' => $services,
    ];
}
ksort($modules, SORT_NATURAL | SORT_FLAG_CASE);

// --- Render ------------------------------------------------------------------------------------
// Sections in the deliberate $CAPS order (only those with members), then any "Other" bucket last.
$capNames = [];
foreach ($CAPS as [$label]) { if (isset($buckets[$label]) && !in_array($label, $capNames, true)) { $capNames[] = $label; } }
foreach (array_keys($buckets) as $label) { if (!in_array($label, $capNames, true)) { $capNames[] = $label; } }

$out  = "# Tiger — Capabilities\n\n";
$out .= "> **GENERATED** by `bin/build-capabilities.php` from class docblocks — **do not edit by hand**;\n";
$out .= "> run the generator. This is the agent's FIRST STOP: *\"does X already exist, and where?\"* Grep it\n";
$out .= "> before assuming something isn't built. `@api` = stable to build on; `@internal` = may change.\n";
$out .= "> Grouped by **capability** (across layers), not by directory.\n\n";
$out .= sprintf("**%d classes** across **%d capabilities** · **%d modules**. Full prose: [FEATURES.md](FEATURES.md) (what) · [ARCHITECTURE.md](ARCHITECTURE.md) (why). Not-yet-built: [BACKLOG.md](BACKLOG.md).\n\n", $total, count($buckets), count($modules));

$out .= "## Capabilities (`library/Tiger`)\n\n";
foreach ($capNames as $cap) {
    $rows = $buckets[$cap];
    usort($rows, static fn($a, $b) => strcmp($a[0], $b[0]));
    $out .= '### ' . $cap . "\n\n";
    foreach ($rows as [$class, $summary, $status, $rel]) {
        $tag = $status ? " `{$status}`" : '';
        $sum = $summary !== '' ? " — {$summary}" : '';
        $out .= "- **{$class}**{$tag}{$sum}  ·  `{$rel}`\n";
    }
    $out .= "\n";
}

$out .= "## Modules (`modules/*` — activatable features)\n\n";
foreach ($modules as $slug => $m) {
    $meta = trim(($m['type'] !== '' ? $m['type'] : 'module'));
    $desc = $m['desc'] !== '' ? " — {$m['desc']}" : '';
    $svc  = $m['services'] ? '  ·  services: ' . implode(', ', $m['services']) : '';
    $out .= "- **{$m['name']}** (`{$slug}`, {$meta}){$desc}{$svc}  ·  `modules/{$slug}`\n";
}
$out .= "\n";

$target = $root . '/CAPABILITIES.md';
if ($check) {
    $current = is_file($target) ? file_get_contents($target) : '';
    if ($current !== $out) {
        fwrite(STDERR, "CAPABILITIES.md is STALE — run `php bin/build-capabilities.php` and commit.\n");
        exit(1);
    }
    fwrite(STDERR, "CAPABILITIES.md is current.\n");
    exit(0);
}
file_put_contents($target, $out);
fwrite(STDERR, sprintf("Wrote CAPABILITIES.md — %d classes, %d capabilities, %d modules.\n", $total, count($buckets), count($modules)));
