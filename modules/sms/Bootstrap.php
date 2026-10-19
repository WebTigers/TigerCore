<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * SMS module bootstrap — surfaces the SMS settings screen and ensures the first-party adapters are
 * registered with the Tiger_Sms facade. The engine (Tiger_Sms + the adapters) lives in the library and
 * works with no module; this module is the operator-facing configuration + test surface.
 */
class Sms_Bootstrap extends Zend_Application_Module_Bootstrap
{
    /** Register the built-in adapters (idempotent — the facade also self-seeds them). */
    protected function _initSmsAdapters()
    {
        if (!class_exists('Tiger_Sms')) { return; }
        Tiger_Sms::register('twilio', 'Tiger_Sms_Adapter_Twilio');
        Tiger_Sms::register('sns', 'Tiger_Sms_Adapter_Sns');
        Tiger_Sms::register('vonage', 'Tiger_Sms_Adapter_Vonage');
        Tiger_Sms::register('plivo', 'Tiger_Sms_Adapter_Plivo');
        Tiger_Sms::register('telnyx', 'Tiger_Sms_Adapter_Telnyx');
    }

    /** Add the SMS settings page to the shared admin Settings tree (ACL-filtered, no core edit). */
    protected function _initSmsSettings()
    {
        if (!class_exists('Tiger_Admin_Settings')) { return; }
        Tiger_Admin_Settings::register([
            'key'      => 'sms',
            'label'    => 'SMS',
            'icon'     => 'fa-comment-sms',
            'href'     => '/sms/admin/settings',
            'resource' => 'Sms_AdminController',
            'order'    => 65,
        ]);
    }
}
