<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * paradigm-scan.php — the STATIC half of the client/server conformance check.
 *
 * Tiger apps are client/server (AGENTS.md "Client/server: the UI calls /api, it does not post
 * to pages", WEBSERVICES.md §5): the server renders the SHELL, and from there the browser is a
 * client exchanging JSON with /api over AJAX. A <form> that page-POSTs to a controller, or a
 * grid whose rows are server-rendered, is a paradigm break — it still "works", which is exactly
 * why nothing catches it.
 *
 * This scanner reads every view script and reports breaks. It is deliberately the cheap,
 * exhaustive half: it reads ALL views in milliseconds where a browser probe can only visit the
 * pages someone wrote a scenario for. The browser probe (tiger-ui-probe) then CONFIRMS the
 * live behaviour on the pages it visits — static finds candidates, the browser proves them.
 *
 * Usage:  php tests/ui/bin/paradigm-scan.php [--json] [--root=<dir>]
 * Exit:   0 = no violations, 1 = violations found, 2 = bad usage.
 */

const CHECKS = [
    'page_post'      => ['Form page-POSTs to a controller',        'blocker'],
    'native_submit'  => ['Form has no AJAX handler (native submit)','review'],
    'rendered_rows'  => ['Table rows server-rendered, not from /api','bug'],
    'inline_script'  => ['<script>/<style> tag in a view, not an asset','batch'],
];

/** Views whose page-POST is a DOCUMENTED exception (AGENTS.md: SSO and the like). */
const EXEMPT_POST = [
    // path fragment => why
    'sso'      => 'SSO hand-off',
    'oauth'    => 'OAuth hand-off',
    'checkout' => 'hosted checkout hand-off',
];

function rel(string $p, string $root): string { return ltrim(str_replace($root, '', $p), '/'); }

function viewFiles(string $root): array
{
    $out = [];
    $skip = '#/(vendor|node_modules|\.git|var|storage|tests)/#';
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        $p = $f->getPathname();
        if (substr($p, -6) !== '.phtml' || preg_match($skip, $p)) { continue; }
        $out[] = $p;
    }
    sort($out);
    return $out;
}

/** Does this view (or a JS asset it references) carry an AJAX submit handler? */
function hasAjaxHandler(string $src, string $file, string $root): bool
{
    if (preg_match('/fetch\s*\(|TigerButton\.run|XMLHttpRequest|\$\.(post|ajax)|data-tiger-validate|tigerDataTable/i', $src)) {
        return true;
    }
    // The house rule pushes JS into assets, registered with pageScript() (or, for a vendored lib a
    // view pulls in by theme path, asset()). Follow either and look in the real file — otherwise every
    // conforming screen reads as a native-submit form, which is the opposite of the truth.
    if (preg_match_all('/(?:pageScript|pageStyle|asset)\([^)]*?[\'"]([^\'"]*\.js)[\'"]/i', $src, $m)) {
        foreach ($m[1] as $a) {
            foreach (glob($root . '/**/' . basename($a)) ?: [] as $hit) {
                if (preg_match('/fetch\s*\(|TigerButton\.run|XMLHttpRequest/i', (string) @file_get_contents($hit))) { return true; }
            }
            $found = shell_exec('find ' . escapeshellarg($root) . ' -name ' . escapeshellarg(basename($a))
                . ' -not -path "*/vendor/*" -print -quit 2>/dev/null');
            if ($found && preg_match('/fetch\s*\(|TigerButton\.run|XMLHttpRequest/i', (string) @file_get_contents(trim($found)))) {
                return true;
            }
        }
    }
    return false;
}

function lineOf(string $src, int $offset): int { return substr_count(substr($src, 0, $offset), "\n") + 1; }

function scanFile(string $file, string $root): array
{
    $src  = (string) file_get_contents($file);
    $r    = rel($file, $root);
    $hits = [];

    // ---- 1. a form that page-POSTs ------------------------------------------------------
    if (preg_match_all('/<form\b[^>]*>/i', $src, $m, PREG_OFFSET_CAPTURE)) {
        foreach ($m[0] as [$tag, $off]) {
            $hasMethodPost = (bool) preg_match('/\bmethod\s*=\s*[\'"]post[\'"]/i', $tag);
            $hasAction     = preg_match('/\baction\s*=\s*[\'"]([^\'"]*)[\'"]/i', $tag, $am);
            $action        = $hasAction ? trim($am[1]) : '';
            $external      = $action !== '' && preg_match('#^https?://#i', $action);
            $exempt        = false;
            foreach (array_keys(EXEMPT_POST) as $frag) {
                if (stripos($r, $frag) !== false || stripos($action, $frag) !== false) { $exempt = true; break; }
            }
            // A method="get" form is NAVIGATION (a search box), not a page-POST of data. Only
            // POST — the shape that submits data and re-renders a whole page — is the violation.
            if ($hasMethodPost && !$external && !$exempt) {
                $hits[] = ['check' => 'page_post', 'file' => $r, 'line' => lineOf($src, $off),
                           'detail' => 'form tag: ' . trim(preg_replace('/\s+/', ' ', $tag))];
            }
        }
        // ---- 2. a form with a submit control but no AJAX handler anywhere -----------------
        $allGet = true;
        foreach ($m[0] as [$tag, $_o]) {
            if (!preg_match('/\bmethod\s*=\s*[\'"]get[\'"]/i', $tag)) { $allGet = false; break; }
        }
        if (!$allGet
            && preg_match('/type\s*=\s*[\'"]submit[\'"]|<button(?![^>]*type\s*=)/i', $src)
            && !hasAjaxHandler($src, $file, $root)
            && !preg_match('/onsubmit\s*=\s*[\'"]\s*return\s+false/i', $src)) {
            $hits[] = ['check' => 'native_submit', 'file' => $r, 'line' => lineOf($src, $m[0][0][1]),
                       'detail' => 'form + submit control, no fetch/TigerButton/validate hook found in the view or its JS assets'];
        }
    }

    // ---- 3. server-rendered table rows --------------------------------------------------
    // The rule is about list/table DATA (WEBSERVICES.md §5), not a static content table. The
    // discriminator: a loop over a CONTROLLER-SUPPLIED view var ($this->x) is data; a loop over
    // an array literal built in the view (a WordPress-vs-Tiger comparison) is copy. Only the
    // former is a paradigm break.
    if (preg_match('/<table\b/i', $src)
        && preg_match_all('/foreach\s*\(\s*(\$[A-Za-z_][\w>\-\[\]\'"\$]*)/i', $src, $fm, PREG_OFFSET_CAPTURE)) {
        foreach ($fm[1] as $i => [$expr, $_x]) {
            $off = $fm[0][$i][1];
            if (strpos($expr, '$this->') !== 0) { continue; }           // view-local array = content
            // Cut the window at the loop's own end, so a <tr> further down the page (a <select>
            // loop followed by an unrelated table) can't be mistaken for this loop's output.
            $window = substr($src, $off, 1200);
            if (preg_match('/endforeach|<\\?php\\s*\\}/i', $window, $em, PREG_OFFSET_CAPTURE)) {
                $window = substr($window, 0, $em[0][1]);
            }
            if (!preg_match('/<tr\b/i', $window)) { continue; }        // the loop must actually emit rows
            $hits[] = ['check' => 'rendered_rows', 'file' => $r, 'line' => lineOf($src, $off),
                       'detail' => 'foreach (' . $expr . ' …) emits <tr> — list data must be fetched from /api (WEBSERVICES.md §5)'];
            break;
        }
    }

    // ---- 4. <script>/<style>/stylesheet-<link> in a view (a style="" ATTRIBUTE is fine) ----
    // A view owns MARKUP; behavior and styling are asset files it REGISTERS — $this->pageScript()
    // / $this->pageStyle() — and the theme LAYOUT is the one place a tag is emitted. So an inline
    // blob, a hand-written `<script src>` and a `<link rel="stylesheet">` are all the same
    // violation: the view emitted a tag instead of registering an asset.
    //
    // Two exemptions, both structural rather than preference:
    //   • an email template — mail clients drop <link>, so inline CSS is the correct answer there;
    //   • a view that IS the document (a full-screen builder whose controller disabled the layout),
    //     because there is no layout to echo the pageScripts/pageHead slot into. It is a layout
    //     wearing a view's filename, and a layout may emit tags.
    // Matching runs on PHP-STRIPPED source: `'#<script\b[^>]*>#'` inside a preg pattern, and the
    // word <script> in a comment, are not markup — three of these were reported against
    // cms/page/design.phtml, which emits exactly one.
    $isEmail    = (strpos($r, 'views/emails/') !== false);
    $isDocument = (bool) preg_match('/<!doctype\s+html|<html\b/i', $src);
    $isLayout   = (strpos($r, '/layouts/') !== false);
    if (!$isEmail && !$isDocument && !$isLayout
        && preg_match_all('~<(script|style)\b[^>]*>|<link\b[^>]*\brel\s*=\s*["\']stylesheet["\'][^>]*>~i',
                          stripPhp($src), $sm, PREG_OFFSET_CAPTURE)) {
        foreach ($sm[0] as [$tag, $off]) {
            $hits[] = ['check' => 'inline_script', 'file' => $r, 'line' => lineOf($src, $off),
                       'detail' => trim(preg_replace('/\s+/', ' ', $tag))];
        }
    }
    return $hits;
}

/**
 * Blank out every PHP region, preserving length + newlines so offsets and line numbers still line up.
 * Markup is what is left — which is what the tag rules are about.
 */
function stripPhp(string $src): string
{
    return (string) preg_replace_callback('/<\?(?:php|=)?.*?(?:\?>|$)/s', function ($m) {
        return preg_replace('/[^\n]/', ' ', $m[0]);
    }, $src);
}

// ---- main -------------------------------------------------------------------------------
$opts = getopt('', ['json', 'root::']);
$root = rtrim($opts['root'] ?? dirname(__DIR__, 3), '/');
if (!is_dir($root)) { fwrite(STDERR, "no such root: $root\n"); exit(2); }

$all = [];
foreach (viewFiles($root) as $f) { $all = array_merge($all, scanFile($f, $root)); }

$by = [];
foreach ($all as $h) { $by[$h['check']][] = $h; }

if (isset($opts['json'])) {
    echo json_encode(['root' => $root, 'total' => count($all), 'by_check' => $by], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
} else {
    printf("Tiger client/server paradigm scan — %s\n%d view scripts, %d findings\n\n",
        $root, count(viewFiles($root)), count($all));
    foreach (CHECKS as $key => [$label, $sev]) {
        $n = count($by[$key] ?? []);
        printf("== %-14s %-48s %s (%d)\n", $key, $label, strtoupper($sev), $n);
        foreach (array_slice($by[$key] ?? [], 0, 12) as $h) {
            printf("   %s:%d\n      %s\n", $h['file'], $h['line'], $h['detail']);
        }
        if ($n > 12) { printf("   … and %d more\n", $n - 12); }
        echo "\n";
    }
}
$blocking = count($by['page_post'] ?? []) + count($by['rendered_rows'] ?? []) + count($by['native_submit'] ?? []);
exit($blocking > 0 ? 1 : 0);
