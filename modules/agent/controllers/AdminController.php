<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Agent_AdminController — the TigerAgent settings screen (ADMIN.md template).
 *
 * Thin: it renders the shell and prefills the form from live config; the save is an /api call
 * to Agent_Service_Settings. The API key is never sent back to the browser — the field shows a
 * "connected" state instead (see the view).
 */
class Agent_AdminController extends Tiger_Controller_Admin_Action
{
    /** Base sets layout('admin'); keep the explicit cascade hook. */
    public function init()
    {
        parent::init();
    }

    /** Settings: the agent registry (a card per agent) + the install-wide auto-mode ceiling. */
    public function indexAction()
    {
        $form = new Agent_Form_Settings();   // carries the CSRF token the /api calls reuse

        $this->view->title        = Zend_Registry::get('Zend_Translate')->translate('agent.settings.title') . ' — Tiger Admin';
        $this->view->form         = $form;
        // The registry rows, or — while it is empty — one seed card prefilled from the legacy default,
        // so the admin's first Save writes the real Default row (the singleton -> registry hand-off).
        $agents = Tiger_Agent::all();
        if (!$agents) {
            $agents = [[
                'agent_id'   => '',
                'name'       => Zend_Registry::get('Zend_Translate')->translate('agent.agents.default_name'),
                'persona'    => '',
                'provider'   => Tiger_Agent::provider(),
                'model'      => Tiger_Agent::model(),
                'enabled'    => Tiger_Agent::isEnabled(),
                'is_default' => true,
                'connected'  => Tiger_Agent::isConnected(),
            ]];
        }
        $this->view->agents       = $agents;
        $this->view->providers    = Tiger_Agent_Provider_Factory::options();
        $this->view->defaultProvider = Tiger_Agent::provider();
        $this->view->modeMax      = Tiger_Agent::modeMax();
        $this->view->cryptoReady  = Tiger_Crypto::isConfigured();
    }
}
