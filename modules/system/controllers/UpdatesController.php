<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * System_UpdatesController — the WordPress-simple "Updates" screen (Login → Admin → Updates).
 *
 * ONE screen lists everything with a pending update — TigerCore + every installer-managed module —
 * with checkboxes and an Update / Update All button. Thin: it renders the detected list; the actual
 * work (and the step log) is the `System_Service_Updates` `/api` service. See ADMIN.md.
 */
class System_UpdatesController extends Tiger_Controller_Admin_Action
{
    public function init()
    {
        parent::init();
    }

    /**
     * Render the Updates screen with the current detection result.
     *
     * @return void
     */
    public function indexAction()
    {
        $updates = Tiger_Update_Checker::all();

        $pending = array_values(array_filter($updates, static function ($u) {
            return !empty($u['update']);
        }));
        // Attach each offered version's changelog section ("what's in this update"). Fetched from the
        // repo at the new ref, file-cached; fail-soft to null (the card falls back to version numbers).
        foreach ($pending as &$u) {
            try { $u['notes'] = Tiger_Update_Checker::notes($u); } catch (Throwable $e) { $u['notes'] = null; }
        }
        unset($u);

        $this->view->title          = 'Updates — Tiger Admin';
        $this->view->updates        = $updates;
        $this->view->pending        = $pending;
        $this->view->currentVersion = Tiger_Version::VERSION;   // the installed TigerCore version, always shown

        // Durable history (empty until the migration runs — never let it break the screen).
        try {
            $this->view->history = (new Tiger_Model_UpdateHistory())->recent(15);
        } catch (Throwable $e) {
            $this->view->history = [];
        }
    }
}
