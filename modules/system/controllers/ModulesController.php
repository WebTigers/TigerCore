<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * System_ModulesController — the Modules admin screen (the WordPress "Plugins" analogue).
 *
 * Lists every module ON DISK (via Tiger_Module_Discovery — the runtime map hides deactivated
 * ones, so we scan directories) with its manifest details + activation state, and lets a
 * superadmin activate/deactivate the non-protected ones. Thin: mutations go through
 * System_Service_Modules over /api. ACL-gated to superadmin+ (configs/acl.ini).
 */
class System_ModulesController extends Tiger_Controller_Admin_Action
{
    /**
     * Admin shell (layout) comes from the base; keep the explicit init cascade.
     *
     * @return void
     */
    public function init()
    {
        parent::init();
    }

    /**
     * List every module on disk with its manifest, source, and activation state.
     *
     * @return void
     */
    public function indexAction()
    {
        // The rows are NOT server-rendered — the view ships an empty table and fetches them from
        // System_Service_Modules::datatable over /api (client/server paradigm, WEBSERVICES §5). Here we
        // only build the TYPE-filter pills: their labels + full-catalog counts, and which one starts
        // active (from the remembered cookie), so the first ajax load is already filtered — no flash.
        $catalog = System_Service_Modules::catalog();

        $counts = [];
        foreach ($catalog as $m) { $t = (string) ($m['type'] ?? 'module'); $counts[$t] = ($counts[$t] ?? 0) + 1; }

        // Type labels from the SAME data-driven registry taxonomy the Add Module screen uses. Best-effort
        // + cached; a derived humanize (in the view) is the fallback, so the screen never depends on the
        // registry being reachable.
        $typeLabels = [];
        try {
            $tax = Tiger_Module_Registry::taxonomy();
            foreach (($tax['types'] ?? []) as $t) {
                if (!empty($t['id'])) { $typeLabels[(string) $t['id']] = (string) ($t['label'] ?? $t['id']); }
            }
        } catch (Throwable $e) {
        }

        // The remembered type filter (a cookie the pill click sets) decides which pill renders active,
        // so the grid loads already filtered on that type instead of flashing All → the chosen tab. A
        // stale cookie (a type no longer present) falls back to All.
        $activeType = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($_COOKIE['tiger_mods_type'] ?? ''));
        if ($activeType !== '' && !isset($counts[$activeType])) { $activeType = ''; }

        $this->view->title         = 'Modules — Tiger Admin';
        $this->view->counts        = $counts;
        $this->view->total         = count($catalog);
        $this->view->typeLabels    = $typeLabels;
        $this->view->activeType    = $activeType;
        $this->view->useDataTables = true;
    }

    /**
     * Add New — registry search + install-from-URL (with a preview step). The screen shell;
     * search/inspect/install are /api calls to System_Service_Modules.
     *
     * @return void
     */
    public function addAction()
    {
        $this->view->title           = 'Add Module — Tiger Admin';
        $this->view->registryUrl     = Tiger_Module_Registry::indexUrl();
        $this->view->registryHasData = Tiger_Module_Registry::available();

        // Where "Subscribe" sends the buyer for TigerPASS checkout (config-overridable; the install never
        // takes a card — Stripe lives on webtigers.com). Empty here just falls back to the default in-view.
        $cfg = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        $pass = ($cfg && $cfg->get('tiger')) ? $cfg->get('tiger')->get('pass') : null;
        $this->view->passCheckoutUrl = $pass ? (string) $pass->get('checkout_url') : '';
    }
}
