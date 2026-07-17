<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * CI smoke: boot the app through the real Tiger_Application path and assert the platform is live —
 * the DB adapter is wired and the migrated schema + founding admin exist. Run from the app root
 * (cwd = the skeleton app dir): `php <tiger-core>/ci/smoke-boot.php`. Exit non-zero on any failure.
 */
$root = getcwd();
define('APPLICATION_ROOT', $root);
require $root . '/vendor/autoload.php';

(new Tiger_Application($root))->boot();

if (!class_exists('Tiger_Version') || Tiger_Version::VERSION === '') {
    fwrite(STDERR, "smoke: Tiger_Version::VERSION is empty after boot\n");
    exit(1);
}
$db = Zend_Db_Table_Abstract::getDefaultAdapter();
if (!$db) {
    fwrite(STDERR, "smoke: no default DB adapter after boot (check tiger.db.* in local.ini)\n");
    exit(1);
}

// migrations applied → core tables exist; install:admin ran → one founding org + user.
$orgs  = (int) $db->fetchOne('SELECT COUNT(*) FROM `org`');
$users = (int) $db->fetchOne('SELECT COUNT(*) FROM `user`');
if ($orgs < 1 || $users < 1) {
    fwrite(STDERR, "smoke: expected the founding org+user (org=$orgs user=$users)\n");
    exit(1);
}

echo "smoke boot OK — tiger-core v" . Tiger_Version::VERSION . ", org=$orgs user=$users\n";
