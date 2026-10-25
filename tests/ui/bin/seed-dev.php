<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * seed-dev.php — give the probe account's org just enough content that a grid scenario can tell
 * "correctly empty" from "broken".
 *
 * Why this exists: on 2026-10-25 three admin grids were shipping an empty table behind HTTP 200
 * with no console error (TIGER-390). The probe org owned no content, so EVERY grid looked exactly
 * like those three — and separating the two took a SQL query, which a scenario cannot run. A grid
 * assertion is only meaningful when the screen is supposed to have rows.
 *
 * Runs ON THE SERVER (it needs the booted app + DB), not from a laptop:
 *   sudo -u apache php tests/ui/bin/seed-dev.php \\
 *       --base=https://tiger-dev.webtigers.com --email=uiprobe-super@tiger.test [--apply]
 *
 * Default is a DRY RUN; nothing is written without --apply. Idempotent: every row is matched before
 * insert, so a re-run adds nothing. Every row is labelled "UI Probe" and uses the reserved
 * `.invalid` domain for addresses, so the seed is identifiable and can never reach a real inbox.
 */

$opts  = getopt('', ['base::', 'email::', 'root::', 'config::', 'apply']);
$root  = $opts['root'] ?? dirname(__DIR__, 3);           // the app root (…/vendor/webtigers/tiger-core → up 3)
$email = (string) ($opts['email'] ?? '');
$apply = array_key_exists('apply', $opts);

if (!is_file($root . '/vendor/autoload.php')) {
    fwrite(STDERR, "seed-dev: no app at $root (pass --root=/var/www/tiger-dev)\n");
    exit(2);
}
define('APPLICATION_ROOT', $root);
require $root . '/vendor/autoload.php';
(new Tiger_Application($root))->boot();

/**
 * Dev only, enforced here and not merely documented. The probes refuse a production base URL; a
 * script that WRITES has to refuse a production install, and it checks the thing that cannot be
 * talked around — the configured site host, against tests/ui/config.json's never_target list.
 */
// ---------------------------------------------------------------- the dev-only guard
// There is NO reliable way for a Tiger install to tell you which site it is from the inside: on
// tiger-dev, APPLICATION_ENV is literally "production" (dev mirrors prod on purpose), tiger.site.url
// is unset, and gethostname() returns an EC2 internal name. An environment sniff would therefore
// either refuse the install this script exists for, or pass vacuously.
//
// So the operator NAMES the target and the guard refuses a forbidden one — the same --base +
// never_target contract the two probes already use. Three deliberate gates: --base is required,
// never_target must be loadable (fail closed), and nothing is written without --apply.
$base = rtrim((string) ($opts['base'] ?? ''), '/');
if ($base === '') {
    fwrite(STDERR, "seed-dev: --base=https://host is required — name the install you believe you are seeding\n");
    exit(2);
}
$cfgFile = $opts['config'] ?? (__DIR__ . '/../config.json');
$cfg     = is_file($cfgFile) ? (json_decode((string) file_get_contents($cfgFile), true) ?: []) : null;
$never   = is_array($cfg) ? ($cfg['never_target'] ?? null) : null;
if (!is_array($never) || !$never) {
    fwrite(STDERR, "REFUSING: no never_target list (looked in $cfgFile) — pass --config=<tests/ui/config.json>\n");
    exit(2);
}
// Hosts compare EXACTLY, never as substrings: tiger-dev.webtigers.com contains "webtigers.com", so a
// substring test would refuse the install this script is for. Prod and dev differ by one label.
$host = strtolower((string) parse_url((strpos($base, '//') === false ? 'https://' . $base : $base), PHP_URL_HOST));
foreach ($never as $forbidden) {
    $needle = strtolower((string) (parse_url(rtrim((string) $forbidden, '/'), PHP_URL_HOST) ?: trim((string) $forbidden, '/')));
    if ($needle !== '' && $needle === $host) {
        fwrite(STDERR, "REFUSING: $host is a production target (never_target in $cfgFile)\n");
        exit(2);
    }
}

if ($email === '') { fwrite(STDERR, "seed-dev: --email=<probe account> is required\n"); exit(2); }

$db  = Zend_Db_Table_Abstract::getDefaultAdapter();
$who = $db->fetchRow(
    $db->select()->from(['u' => 'user'], ['user_id'])
       ->join(['ou' => 'org_user'], 'ou.user_id = u.user_id', ['org_id'])
       ->where('u.email = ?', $email)->where('u.deleted = 0')->limit(1)
);
if (!$who) { fwrite(STDERR, "seed-dev: no account + membership for $email\n"); exit(1); }
[$org, $usr] = [(string) $who['org_id'], (string) $who['user_id']];

Tiger_Model_Table::setActor($usr, $org);
$now = date('Y-m-d H:i:s');
$std = ['org_id' => $org, 'deleted' => 0, 'created_by' => $usr, 'updated_by' => $usr,
        'created_at' => $now, 'updated_at' => $now];

$report = [];
$mk = function (string $what, string $table, array $where, array $row) use ($db, $apply, &$report) {
    $sel = $db->select()->from($table, ['c' => new Zend_Db_Expr('COUNT(*)')]);
    foreach ($where as $c => $v) { $sel->where("$c = ?", $v); }
    if ((int) $db->fetchOne($sel) > 0) { $report[] = "  have  $what"; return; }
    if ($apply) { $db->insert($table, $row); }
    $report[] = ($apply ? '  ADD   ' : '  would add  ') . $what;
};

// A CMS page the org genuinely OWNS (/cms/page).
$mk('cms page "UI Probe Page"', 'page', ['org_id' => $org, 'slug' => 'ui-probe-page'], $std + [
    'page_id' => Tiger_Uuid::v7(), 'title' => 'UI Probe Page', 'slug' => 'ui-probe-page',
    'type' => Tiger_Model_Page::TYPE_PAGE, 'locale' => 'en', 'format' => 'html',
    'status' => Tiger_Model_Page::STATUS_PUBLISHED, 'published_at' => $now,
    'body' => '<p>Seeded for functional UI testing. Safe to delete.</p>',
]);

// An article (/blog/post). An article IS a page row — type='article', NOT 'post'; seeding 'post'
// produced a row no grid could see, which is its own small lesson about guessing a constant.
$mk('blog article "UI Probe Article"', 'page', ['org_id' => $org, 'slug' => 'ui-probe-article'], $std + [
    'page_id' => Tiger_Uuid::v7(), 'title' => 'UI Probe Article', 'slug' => 'ui-probe-article',
    'type' => Blog_Model_Post::TYPE_ARTICLE, 'locale' => 'en', 'format' => 'html',
    'status' => Tiger_Model_Page::STATUS_PUBLISHED, 'published_at' => $now,
    'body' => '<p>Seeded for functional UI testing. Safe to delete.</p>',
]);

// A one-item menu (/cms/menu).
$mk('menu "uiprobe"', 'menu', ['org_id' => $org, 'menu_key' => 'uiprobe'], $std + [
    'menu_id' => Tiger_Uuid::v7(), 'menu_key' => 'uiprobe', 'source' => 'user', 'sort_order' => 0,
    'label' => 'UI Probe Home', 'url' => '/', 'status' => Tiger_Model_Menu::STATUS_PUBLISHED,
]);

// A snippet for the Code Area (/code) — CSS, INACTIVE, draft. Seeding executable PHP that actually
// runs would be reckless in a fixture; the grid only needs a row to draw.
$mk('code snippet "UI Probe snippet" (inactive)', 'code', ['org_id' => $org, 'name' => 'UI Probe snippet'], $std + [
    'code_id' => Tiger_Uuid::v7(), 'name' => 'UI Probe snippet', 'language' => Tiger_Model_Code::LANG_CSS,
    'description' => 'Seeded for functional UI testing. Inactive on purpose.',
    'code' => "/* seeded for UI testing */\n", 'run_location' => 'frontend', 'priority' => 50,
    'active' => 0, 'status' => Tiger_Model_Code::STATUS_DRAFT,
]);

// A subscriber (/newsletter/admin) on a reserved, undeliverable domain (RFC 2606).
$mk('newsletter subscriber (@example.invalid)', 'newsletter_subscriber',
    ['org_id' => $org, 'email' => 'uiprobe-subscriber@example.invalid'], $std + [
    'newsletter_subscriber_id' => Tiger_Uuid::v7(), 'email' => 'uiprobe-subscriber@example.invalid',
    'name' => 'UI Probe Subscriber', 'status' => Newsletter_Model_Subscriber::STATUS_CONFIRMED,
    'consent_source' => 'uiprobe-seed', 'consent_at' => $now, 'token' => Tiger_Uuid::v4(),
    'source' => 'uiprobe-seed',
]);

printf("%s — %s (org %s)\n%s\n", $apply ? 'seeded' : 'DRY RUN (pass --apply to write)', $email, $org,
       implode("\n", $report));
