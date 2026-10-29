<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Sms_AdminController — the admin SMS settings screen (Admin → Settings → SMS).
 *
 * Renders the sender identity (the brand), the provider picker, and each provider's own fields (from the
 * adapter's fields() declaration) with the current values, so adding an adapter needs no controller
 * change. Secrets are never sent to the browser — a stored secret shows only as "set". The save + test
 * happen over /api (Sms_Service_Settings).
 */
class Sms_AdminController extends Tiger_Controller_Admin_Action
{
    public function init()
    {
        parent::init();
    }

    public function settingsAction()
    {
        $cfg = new Tiger_Model_Config();
        $g   = Tiger_Model_Config::SCOPE_GLOBAL;
        $get = static function ($key) use ($cfg, $g) { return (string) $cfg->get($g, '', $key); };

        $providers = [];
        foreach (Tiger_Sms::providers() as $name) {
            $class = 'Tiger_Sms_Adapter_' . ucfirst($name);
            if (!class_exists($class)) { continue; }
            $adapter = new $class();
            $fields  = [];
            foreach ($adapter->fields() as $f) {
                $base = 'tiger.sms.adapters.' . $name . '.' . $f['key'];
                $f['value'] = empty($f['secret']) ? $get($base) : '';
                $f['set']   = !empty($f['secret']) && $get($base . '_enc') !== '';
                $fields[]   = $f;
            }
            $providers[] = ['name' => $name, 'label' => $adapter->label(), 'fields' => $fields];
        }

        // The sender identity, and whether it is actually SET — an unset brand silently borrows the
        // site name, which is the one failure the operator cannot see from the outside.
        $this->view->brand       = $get('tiger.sms.brand');
        $this->view->brandInUse  = Tiger_Sms::brand();
        $this->view->brandIsSet  = Tiger_Sms::brandIsSet();

        $this->view->title       = 'SMS — Tiger Admin';
        $this->view->providers   = $providers;
        $this->view->provider    = Tiger_Sms::provider();
        $this->view->configured  = Tiger_Sms::isConfigured();
        $this->view->cryptoReady = class_exists('Tiger_Crypto') && Tiger_Crypto::isConfigured();
        $this->i18n([
            'sms.settings.saved', 'sms.settings.test_sent', 'sms.settings.error.test',
            'sms.settings.error.to', 'core.api.error.general',
        ]);
    }
}
