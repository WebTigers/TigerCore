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
        // Theme content (TIGER-455) — the remote theme-content mirror's status + "Update now". Shown only where a
        // remote content source is configured: on every other install there is nothing to fetch.
        if ((new Tiger_Theme_Remote())->isEnabled()) {
            Tiger_Admin_Settings::register([
                'key'      => 'system_theme_content',
                'label'    => 'system.nav.theme_content',
                'icon'     => 'fa-cloud-arrow-down',
                'href'     => '/system/themecontent',
                'resource' => 'System_ThemecontentController',
                'order'    => 45,
            ]);
        }
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
     * Per-domain TigerPASS seats (TIGER-505) — the notice + the daily reconcile.
     *
     * NOTICE: on a multi-domain install, an admin working on a tenant domain that has no valid seat sees a key
     * icon with a badge in the admin header (Tiger_Admin_Header, `visible` only on that host, admin+ via the
     * Sites resource) linking to System › Sites, where the coverage column says what to do. The public site
     * shows nothing and nothing is ever disabled — nag, never disable. The check is option reads only.
     *
     * JOB: once a day, claim a seat for every served domain that lacks one and refresh each seat's cached
     * verdict. Idempotent and fail-soft; a refused claim just leaves that domain uncovered.
     */
    protected function _initLicenseSeats()
    {
        if (!class_exists('Tiger_License_Seats')) { return; }

        Tiger_Admin_Header::register([
            'key'      => 'system_license_seat',
            'label'    => 'system.seats.notice',
            'icon'     => 'fa-key',
            'href'     => '/system/sites',
            'resource' => 'System_SitesController',
            'order'    => 5,
            'badge'    => 1,
            'visible'  => static fn() => Tiger_License_Seats::needsSeat(Tiger_License_Pass::installDomain()),
        ]);

        if (!class_exists('Tiger_Schedule')) { return; }
        Tiger_Schedule::register([
            'key'     => 'system.license_seats',
            'label'   => 'Claim and verify TigerPASS seats for every served domain',
            'every'   => Tiger_Schedule::DAILY,
            'at'      => '03:45',
            'run'     => static function () { Tiger_License_Seats::run(); },
            'managed' => false,
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
            'run'     => static function () {
                Tiger_Update_Checker::all(true);                 // refresh detection + the badge summary
                System_Service_Updates::runScheduledAutoUpdates(); // then apply opted-in modules (WP-style)
            },
            'managed' => false,
        ]);
    }
}
