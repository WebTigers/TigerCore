<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Account_Nav — the "My Account" sidebar's nav registry (the module hook for USER screens).
 *
 * The account-surface sibling of Tiger_Admin_Nav. Where that registry builds the /admin back-office
 * menu ("manage the platform" — Content, Users, Modules), THIS one builds the /account menu ("manage
 * MY stuff" — my subscription, my licenses, my profile). The menu follows the SURFACE, not the role:
 * an admin visiting /account sees the account menu (their personal account), not the admin menu. So a
 * module that gives a user their own screen — "My Membership", "My Licenses", "My Orders" — parks it
 * HERE, and it shows up in every user's account sidebar, ACL-filtered live.
 *
 * A module contributes one of **two equal ways** (identical to Tiger_Admin_Nav):
 *   - **Config** — drop a `configs/navigation-account.ini` (auto-discovered; see discover()). The
 *     zero-code default: declarative, no Bootstrap method. The `profile` module is the reference —
 *     it surfaces "Profile" + "My Organization" into the account menu this way.
 *   - **Code** — call `register()` from the module Bootstrap, for an item that needs logic (a computed
 *     label/href, a conditional item).
 * Either way the item is ACL-gated + activation-gated for free (a deactivated module's item is skipped,
 * and an item whose `resource` the live role can't reach hides). The PUMA `admin-menu` partial renders
 * whatever nav it's handed, so the account controllers just feed it `Tiger_Account_Nav::items()`.
 *
 *   ; modules/membership/configs/navigation-account.ini   (the zero-code path)
 *   nav.membership.label    = "My Membership"
 *   nav.membership.icon     = "fa-id-card"
 *   nav.membership.href     = "/member"
 *   nav.membership.match    = "/member"                    ; path prefix that marks it active
 *   nav.membership.resource = "Member_IndexController"     ; ACL-gated — hides for denied roles
 *   nav.membership.order    = 40
 *
 *   // …or the code path, from the module Bootstrap:
 *   Tiger_Account_Nav::register([
 *       'key'      => 'membership',
 *       'label'    => 'My Membership',
 *       'icon'     => 'fa-id-card',
 *       'href'     => '/member',
 *       'match'    => '/member',
 *       'resource' => 'Member_IndexController',
 *       'order'    => 40,
 *   ]);
 *
 * To make a screen RENDER in the account surface (shell + this menu), its controller extends
 * Tiger_Controller_Account_Action — see that class for the other half of the example.
 *
 * @api
 * @see Tiger_Admin_Nav
 * @see Tiger_Controller_Account_Action
 */
class Tiger_Account_Nav
{
    /** @var array<string,array> key => item definition (module-contributed) */
    protected static $_items = [];

    /** @var bool whether module navigation-account.ini files have been discovered this request */
    protected static $_loaded = false;

    /**
     * Register (or replace, by key) an account-menu item. Requires key, label, href.
     *
     * @param  array $item item definition (key, label, href, and optional icon/match/resource/order)
     * @return void
     */
    public static function register(array $item)
    {
        if (empty($item['key']) || empty($item['label']) || empty($item['href'])) {
            return;
        }
        self::$_items[$item['key']] = $item + [
            'icon'     => 'fa-circle',
            'match'    => $item['href'],
            'resource' => null,
            'order'    => 100,
        ];
    }

    /**
     * The full account menu as sidebar nav-item arrays: the core seed ("Overview" → /account),
     * then the module-contributed items (registered + config-discovered, sorted by order then
     * label), then the "Admin" escape hatch last (→ /admin, ACL-hidden for non-admins so only a
     * user who can reach the back office sees it). ACL filtering of each item happens in the menu
     * partial, live per role.
     *
     * @return array<int,array>
     */
    public static function items()
    {
        self::discover();

        $modules = array_values(self::$_items);
        usort($modules, static function ($a, $b) {
            return [$a['order'], $a['label']] <=> [$b['order'], $b['label']];
        });
        $modules = array_map(static function ($p) {
            return [
                'key'      => $p['key'],
                'label'    => $p['label'],
                'href'     => $p['href'],
                'match'    => $p['match'],
                'icon'     => $p['icon'],
                'resource' => $p['resource'],
            ];
        }, $modules);

        // Core seed first (the account home), module items in the middle, the admin escape hatch last.
        // The escape hatch carries the AdminController resource, so the menu partial's deny-by-default
        // ACL filter shows it ONLY to a user who can reach /admin — no role compare here.
        $overview = ['key' => 'overview', 'label' => 'Overview', 'href' => '/account', 'match' => '/account', 'icon' => 'fa-gauge-high', 'resource' => null];
        $backAdmin = ['key' => 'admin', 'label' => 'Admin', 'href' => '/admin', 'match' => '/admin', 'icon' => 'fa-toolbox', 'resource' => 'AdminController'];

        return array_merge([$overview], $modules, [$backAdmin]);
    }

    /**
     * Reset the registry (tests).
     *
     * @return void
     */
    public static function clear()
    {
        self::$_items = [];
        self::$_loaded = false;
    }

    /**
     * Auto-discover account-menu items from each ACTIVE module's `configs/navigation-account.ini` —
     * the exact mirror of how Tiger_Admin_Nav discovers `navigation.ini`, so a module surfaces a user
     * screen purely in config, with NO Bootstrap code. Idempotent + memoized (safe to call repeatedly).
     *
     *   ; modules/<name>/configs/navigation-account.ini
     *   nav.<key>.label    = "My Thing"
     *   nav.<key>.icon     = "fa-star"
     *   nav.<key>.href     = "/thing/mine"
     *   nav.<key>.match    = "/thing/mine"
     *   nav.<key>.resource = "Thing_IndexController"
     *   nav.<key>.order    = 50
     *
     * @return void
     */
    public static function discover()
    {
        if (self::$_loaded) {
            return;
        }
        self::$_loaded = true;

        $dirs = [];
        if (defined('TIGER_CORE_PATH'))  { $dirs[] = TIGER_CORE_PATH . '/modules'; }   // first-party (vendor)
        if (defined('APPLICATION_PATH')) { $dirs[] = APPLICATION_PATH . '/modules'; }  // app (wins on collision)

        $inactive = [];
        try {
            if (class_exists('Tiger_Model_Module')) {
                $inactive = (new Tiger_Model_Module())->inactiveSlugs();
            }
        } catch (Throwable $e) {
            // DB not ready (install/CLI) — show everything rather than nothing.
        }

        foreach ($dirs as $modsDir) {
            foreach (glob($modsDir . '/*', GLOB_ONLYDIR) ?: [] as $moduleDir) {
                if (in_array(basename($moduleDir), $inactive, true)) {
                    continue;   // a deactivated module never shows a nav link
                }
                $ini = $moduleDir . '/configs/navigation-account.ini';
                if (!is_file($ini)) {
                    continue;
                }
                try {
                    $nav = (new Zend_Config_Ini($ini))->get('nav');
                    if (!($nav instanceof Zend_Config)) {
                        continue;
                    }
                    foreach ($nav as $key => $item) {
                        if (!($item instanceof Zend_Config)) {
                            continue;
                        }
                        $data = $item->toArray();
                        $data['key'] = isset($data['key']) ? $data['key'] : $key;
                        self::register($data);
                    }
                } catch (Throwable $e) {
                    // A malformed ini is skipped, never fatal.
                }
            }
        }
    }
}
