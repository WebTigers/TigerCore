<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Migration 0044 — activate the app modules already present (the opt-in flip).
 *
 * Module activation became AREA-AWARE (Tiger_Model_Module::inactiveSlugs): CORE modules
 * (`tiger-core/modules`) stay active-unless-deactivated (opt-out, no rows needed), but APP modules
 * (`application/modules`) are now INERT unless activated (opt-in — a row with active=1). Before this,
 * an app module was live just by being on disk. So on an existing install, an app module currently
 * present with NO row would suddenly go dark.
 *
 * This preserves the live state: for each present APP module that has no row yet, write an active=1
 * row (with its module.json provenance, so the update checker can diff it too). Core modules are left
 * alone (opt-out needs no rows); an app module with an active=0 row stays deactivated. Idempotent —
 * any module that already has a row is skipped, so re-running changes nothing.
 *
 * PHP callable (not SQL): it must enumerate the filesystem to know which app modules are present.
 */
return [
    'up' => [
        function ($db) {
            if (!class_exists('Tiger_Module_Discovery') || !class_exists('Tiger_Model_Module')) {
                return;
            }
            $existing = array_flip(array_map('strval', $db->fetchCol($db->select()->from('module', ['slug']))));
            $model    = new Tiger_Model_Module();
            foreach (Tiger_Module_Discovery::all() as $slug => $d) {
                $slug = (string) $slug;
                if ($slug === '' || ($d['area'] ?? 'app') !== 'app') { continue; }  // core = opt-out, no row
                if (isset($existing[$slug])) { continue; }                          // respect an existing row
                $mf = Tiger_Module_Discovery::manifestFor($slug);
                $model->install($slug, [
                    'name'       => $d['name']    ?? $slug,
                    'version'    => $d['version'] ?? null,
                    'repository' => is_array($mf) ? ($mf['repository'] ?? null) : null,
                    'type'       => $d['type']    ?? null,
                    'source'     => Tiger_Model_Module::SOURCE_DISCOVERED,
                ]);
            }
        },
    ],
    // No down: removing these rows would dark-out the very modules this activated (opt-in gate).
    'down' => [],
];
