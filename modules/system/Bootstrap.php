<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * System module bootstrap — platform administration (the Module manager, for now).
 *
 * First-party, always-on module (it manages the OTHER modules' activation, so it's in the
 * protected set and can never be deactivated). Auto-discovered like any module.
 */
class System_Bootstrap extends Zend_Application_Module_Bootstrap
{
    /** Contribute the System page to the admin Settings tree (ACL-gated in the menu). */
    protected function _initAdminSettings()
    {
        Tiger_Admin_Settings::register([
            'key'      => 'system',
            'label'    => 'system.nav.label',
            'icon'     => 'fa-server',
            'href'     => '/system/settings',
            'resource' => 'System_SettingsController',
            'order'    => 20,
        ]);
        // ACL Simulator — a read-only "why am I locked out?" diagnostic. It's a tool, not a
        // top-level destination, so it lives under Settings (superadmin+, ACL-gated in the menu).
        Tiger_Admin_Settings::register([
            'key'      => 'system_acl',
            'label'    => 'system.nav.acl',
            'icon'     => 'fa-scale-balanced',
            'href'     => '/system/acl',
            'resource' => 'System_AclController',
            'order'    => 30,
        ]);
        // Sites — the multi-site host -> org map (one install serving many public sites). Under Settings
        // (ACL-gated in the menu); on a single-site install it's an empty list, harmless.
        Tiger_Admin_Settings::register([
            'key'      => 'system_sites',
            'label'    => 'system.nav.sites',
            'icon'     => 'fa-globe',
            'href'     => '/system/sites',
            'resource' => 'System_SitesController',
            'order'    => 40,
        ]);
    }

    /** Top-level "Logs" item (ACL-gated in the menu; Updates lives under the Modules toggle). */
    protected function _initAdminNav()
    {
        Tiger_Admin_Nav::register([
            'key'      => 'system_logs',
            'label'    => 'system.nav.logs',
            'icon'     => 'fa-rectangle-list',
            'href'     => '/system/logs',
            'resource' => 'System_LogsController',
            'order'    => 17,
        ]);
    }

    /**
     * Keep the Updates badge honest (TIGER-112).
     *
     * The badge on Modules > Updates reads a summary the last full check wrote; it never checks for
     * itself, because it renders on every admin page. Without this job the summary is only refreshed
     * when someone opens the Updates screen, and a badge that lights up only after you have already
     * looked is not a badge. Daily is enough — release cadence does not justify hitting GitHub from
     * every install more often — and the summary keeps for two days, so a missed run does not blank it.
     */
    protected function _initUpdateCheckJob()
    {
        if (!class_exists('Tiger_Schedule') || !class_exists('Tiger_Update_Checker')) { return; }

        Tiger_Schedule::register([
            'key'     => 'system.update_check',
            'label'   => 'Check for Tiger and module updates',
            'every'   => Tiger_Schedule::DAILY,
            'at'      => '03:30',
            'run'     => static function () { Tiger_Update_Checker::all(true); },
            'managed' => false,
        ]);
    }
}
