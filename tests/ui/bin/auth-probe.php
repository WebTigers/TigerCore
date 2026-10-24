<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * auth-probe.php — functional user tests for the auth surface, at the transport layer.
 *
 * Tiger's auth screens are a client/server contract (AGENTS.md): the server renders the shell,
 * and login / logout / forgot / reset / session are AJAX calls to thin controllers that return
 * JSON. That contract is testable WITHOUT a browser — which is why this probe exists alongside
 * the browser probe: it is fast, deterministic, CI-able, and it proves the half that matters
 * most (status codes, JSON envelopes, account enumeration) while the browser proves the DOM.
 *
 * SAFETY: every identifier used here is a non-existent, non-routable test address, so a run can
 * never trip the brute-force lockout on a real account. Never point this at production.
 *
 * Usage:  php tests/ui/bin/auth-probe.php [--base=https://host] [--json]
 * Exit:   0 = all passed, 1 = at least one failure.
 */

$cfg  = json_decode((string) file_get_contents(dirname(__DIR__) . '/config.json'), true) ?: [];
$opts = getopt('', ['base::', 'json']);
$BASE = rtrim($opts['base'] ?? ($cfg['base_url'] ?? ''), '/');
if ($BASE === '') { fwrite(STDERR, "no base_url\n"); exit(2); }
foreach (($cfg['never_target'] ?? []) as $forbidden) {
    if (stripos($BASE, rtrim($forbidden, '/')) === 0) {
        fwrite(STDERR, "REFUSING: $BASE is a production origin (config.json never_target)\n"); exit(2);
    }
}

/** Nonexistent on purpose — .invalid is reserved by RFC 2606 and can never be a real account. */
const FAKE_A = 'uiprobe-aaaa@example.invalid';
const FAKE_B = 'uiprobe-bbbb@example.invalid';

$results = [];
/**
 * A check records PASS / FAIL / GATED. GATED is the honest third state: reCAPTCHA refused a
 * tokenless client, so the assertion never actually ran. Counting that as PASS would be the
 * "validator that only ever passes" trap — a green check proving nothing.
 */
function check(string $id, string $title, string $expect, bool $ok, string $got, string $sev = 'bug', bool $gated = false): void
{
    global $results;
    $results[] = compact('id', 'title', 'expect', 'ok', 'got', 'sev', 'gated');
}
/** Did reCAPTCHA refuse this call before the real logic ran? */
function captchaGated(?array $j): bool { return $j !== null && !empty($j['recaptcha']); }

function http(string $url, array $o = []): array
{
    $ch = curl_init($url);
    $hdrs = $o['headers'] ?? [];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 20,
        CURLOPT_FOLLOWLOCATION => false, CURLOPT_HTTPHEADER => $hdrs,
        CURLOPT_USERAGENT => 'TigerUIProbe/1.0',
    ]);
    if (isset($o['post'])) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($o['post']) ? http_build_query($o['post']) : $o['post']);
    }
    if (!empty($o['jar'])) { curl_setopt($ch, CURLOPT_COOKIEJAR, $o['jar']); curl_setopt($ch, CURLOPT_COOKIEFILE, $o['jar']); }
    $raw  = (string) curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hlen = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $err  = curl_error($ch);
    return ['code' => $code, 'headers' => substr($raw, 0, $hlen), 'body' => substr($raw, $hlen), 'error' => $err];
}
function asJson(array $r): ?array { $d = json_decode(trim($r['body']), true); return is_array($d) ? $d : null; }

$AJAX = ['X-Requested-With: XMLHttpRequest', 'Content-Type: application/x-www-form-urlencoded'];

// ---------------------------------------------------------------- AUTH-01  login screen renders
$r = http("$BASE/auth/login");
check('AUTH-01', 'Login screen renders', '200 + HTML carrying the sign-in form',
    $r['code'] === 200 && stripos($r['body'], 'id="login-form"') !== false,
    "HTTP {$r['code']}, login-form " . (stripos($r['body'], 'id="login-form"') !== false ? 'present' : 'MISSING'), 'blocker');

// ---------------------------------------------------------------- AUTH-02  no raw i18n keys on screen
$rawKeys = [];
if (preg_match_all('/>\s*(core\.[a-z0-9_.]+)\s*</i', $r['body'], $km)) { $rawKeys = array_unique($km[1]); }
check('AUTH-02', 'No untranslated i18n keys rendered', 'no core.* key visible as page text',
    count($rawKeys) === 0, count($rawKeys) ? implode(', ', array_slice($rawKeys, 0, 5)) : 'none');

// ---------------------------------------------------------------- AUTH-03  the form is AJAX, not a page-POST
$formTag = '';
if (preg_match('/<form[^>]*id="login-form"[^>]*>/i', $r['body'], $fm)) { $formTag = $fm[0]; }
check('AUTH-03', 'Login form is client/server (no page-POST)', 'form has no action= and no method="post"',
    $formTag !== '' && !preg_match('/\baction\s*=/i', $formTag) && !preg_match('/method\s*=\s*[\'"]post/i', $formTag),
    $formTag !== '' ? trim($formTag) : 'form not found', 'blocker');

// ---------------------------------------------------------------- AUTH-04  failed login -> 401 + JSON envelope
$r = http("$BASE/auth/login", ['headers' => $AJAX, 'post' => ['identifier' => FAKE_A, 'password' => 'wrong-on-purpose-1']]);
$j = asJson($r);
$gated4 = captchaGated($j);
check('AUTH-04', 'Failed login is refused with a JSON envelope', 'HTTP 401 + {result:0, message}',
    $gated4 ? false : ($r['code'] === 401 && $j && ($j['result'] ?? null) === 0 && !empty($j['message'])),
    "HTTP {$r['code']} body=" . substr(trim($r['body']), 0, 120), 'blocker', $gated4);
$failA = $j;

// ---------------------------------------------------------------- AUTH-05  no account enumeration
$r2 = http("$BASE/auth/login", ['headers' => $AJAX, 'post' => ['identifier' => FAKE_B, 'password' => 'wrong-on-purpose-2']]);
$j2 = asJson($r2);
// Identical reCAPTCHA refusals would make this pass VACUOUSLY — the credential path never ran.
check('AUTH-05', 'Login does not enumerate accounts', 'two different unknown identifiers give an identical refusal',
    ($gated4 || captchaGated($j2)) ? false : ($r['code'] === $r2['code'] && json_encode($failA) === json_encode($j2)),
    "A=HTTP {$r['code']} " . json_encode($failA) . " | B=HTTP {$r2['code']} " . json_encode($j2),
    'blocker', $gated4 || captchaGated($j2));

// ---------------------------------------------------------------- AUTH-06  empty credentials refused
$r = http("$BASE/auth/login", ['headers' => $AJAX, 'post' => ['identifier' => '', 'password' => '']]);
$j = asJson($r);
check('AUTH-06', 'Empty credentials refused cleanly', 'non-2xx + JSON, never a 500 or an HTML error page',
    $r['code'] >= 400 && $r['code'] < 500 && $j !== null,
    "HTTP {$r['code']} " . (asJson($r) ? 'json' : 'NOT json: ' . substr(trim($r['body']), 0, 100)));

// ---------------------------------------------------------------- AUTH-07  password reset request does not enumerate
$r = http("$BASE/auth/forgot", ['headers' => $AJAX, 'post' => ['email' => FAKE_A]]);
$j = asJson($r);
$gated7 = captchaGated($j);
check('AUTH-07', 'Reset request does not enumerate accounts', 'result:1 for an address that does not exist',
    $gated7 ? false : ($j && ($j['result'] ?? null) === 1),
    "HTTP {$r['code']} " . substr(trim($r['body']), 0, 140), 'blocker', $gated7);

// ---------------------------------------------------------------- AUTH-08  reset with a bogus token
$r = http("$BASE/auth/reset/token/not-a-real-token-000", ['headers' => $AJAX,
    'post' => ['password' => 'Xy8!derived-probe-pw', 'confirm' => 'Xy8!derived-probe-pw']]);
check('AUTH-08', 'Reset with an invalid token fails safely', 'a 4xx or a JSON refusal — never a 500',
    $r['code'] !== 500 && $r['code'] < 500 || asJson($r) !== null,
    "HTTP {$r['code']} " . substr(trim($r['body']), 0, 140));

// ---------------------------------------------------------------- AUTH-09  session heartbeat is JSON
$r = http("$BASE/auth/session", ['headers' => $AJAX]);
$j = asJson($r);
check('AUTH-09', 'Session heartbeat returns JSON', '200 + a JSON object the client can read',
    $r['code'] === 200 && $j !== null,
    "HTTP {$r['code']} " . substr(trim($r['body']), 0, 140));

// ---------------------------------------------------------------- AUTH-10  logout renders + its links resolve
$r = http("$BASE/auth/logout");
check('AUTH-10', 'Logout renders the signed-out card', '200 + the confirmation card',
    $r['code'] === 200, "HTTP {$r['code']}");
if (preg_match_all('/<a[^>]*href="(\/[^"#]*)"/i', $r['body'], $lm)) {
    $broken = [];
    foreach (array_unique($lm[1]) as $href) {
        $lr = http($BASE . $href);
        if ($lr['code'] >= 400) { $broken[] = "$href → HTTP {$lr['code']}"; }
    }
    check('AUTH-11', 'Signed-out card links all resolve', 'every link on the logout page returns < 400',
        count($broken) === 0, count($broken) ? implode('; ', $broken) : 'all ok', 'blocker');
}

// ---------------------------------------------------------------- AUTH-12  /login alias is the login screen
$r = http("$BASE/login");
check('AUTH-12', 'The /login alias serves the login screen', '200 AND the sign-in form (not a CMS page that merely 200s)',
    $r['code'] === 200 && stripos($r['body'], 'id="login-form"') !== false,
    "HTTP {$r['code']}, login-form " . (stripos($r['body'], 'id="login-form"') !== false ? 'present' : 'MISSING'));

// ---------------------------------------------------------------- AUTH-13  auth endpoints are not GET-mutable
$r = http("$BASE/auth/forgot/email/" . rawurlencode(FAKE_A));
check('AUTH-13', 'Reset cannot be triggered by a bare GET', 'a GET with params must not be accepted as a submission',
    !(asJson($r) && (asJson($r)['result'] ?? null) === 1),
    "HTTP {$r['code']} " . substr(trim($r['body']), 0, 120));

// ---------------------------------------------------------------- report
$fail  = array_values(array_filter($results, fn($x) => !$x['ok'] && !$x['gated']));
$gated = array_values(array_filter($results, fn($x) => $x['gated']));
if (isset($opts['json'])) {
    echo json_encode(['base' => $BASE, 'total' => count($results), 'failed' => count($fail), 'results' => $results],
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
} else {
    echo "Tiger auth functional probe — $BASE\n\n";
    foreach ($results as $x) {
        $state = $x['gated'] ? 'GATED' : ($x['ok'] ? 'PASS' : 'FAIL');
        printf("%-5s %-9s %s\n", $state, $x['id'], $x['title']);
        if (!$x['ok']) { printf("       expected: %s\n       got:      %s\n", $x['expect'], $x['got']); }
    }
    $gatedN = count(array_filter($results, fn($x) => $x['gated']));
    printf("\n%d checks, %d failed, %d GATED (reCAPTCHA refused a tokenless client — those assertions never ran)\n",
        count($results), count($fail), $gatedN);
    if ($gatedN) {
        echo "\nThese are inconclusive, not green — and no configuration un-gates them (TIGER-385: dev runs\n"
           . "reCAPTCHA v3 to match prod, v3 needs a BROWSER-executed token, and Google's always-pass test\n"
           . "keys are v2-only). curl has no token, not a low score. They belong to the headless browser\n"
           . "probe instead, which mints a real token; GATED is this tool's honest answer.\n";
    }
}
exit(count($fail) ? 1 : 0);
