<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Email module bootstrap — the owner/agent authoring surface for transactional emails (TIGER-267).
 *
 * The engine lives in the platform layer (Tiger_Mail's file templates + the shared branded layout, and
 * the Tiger_Model_EmailTemplate DB-override tier); this module is the admin UI on top of it: a list of
 * every registered email event and an editor that writes an authored subject + HTML body (with
 * `{{var}}` placeholders) that overrides the code-shipped default — the live-override pattern, no deploy.
 *
 * It ALSO registers the platform's own shipped transactional emails as authorable events (so the screen
 * isn't empty out of the box). A feature module that sends its own mail registers its events the same
 * way from its Bootstrap — that's how it opts its mail into being owner/agent-authorable.
 */
class Email_Bootstrap extends Zend_Application_Module_Bootstrap
{
    /** Add the Email Templates page to the shared admin Settings tree (ACL-filtered, no core edit). */
    protected function _initEmailSettings()
    {
        if (!class_exists('Tiger_Admin_Settings')) { return; }
        Tiger_Admin_Settings::register([
            'key'      => 'email',
            'label'    => 'email.nav.settings',
            'icon'     => 'fa-envelope',
            'href'     => '/email/admin',
            'resource' => 'Email_AdminController',
            'order'    => 68,
        ]);
    }

    /** Register the platform's shipped transactional emails as authorable events (idempotent). */
    protected function _initEmailTemplates()
    {
        if (!class_exists('Tiger_Mail')) { return; }

        Tiger_Mail::registerTemplate([
            'key'         => 'reset',
            'label'       => 'email.tpl.reset.label',
            'description' => 'email.tpl.reset.desc',
            'group'       => 'auth',
            'vars'        => ['url' => 'email.var.reset_url'],
        ]);
        Tiger_Mail::registerTemplate([
            'key'         => 'otp',
            'label'       => 'email.tpl.otp.label',
            'description' => 'email.tpl.otp.desc',
            'group'       => 'auth',
            'vars'        => ['code' => 'email.var.code'],
        ]);
        Tiger_Mail::registerTemplate([
            'key'         => 'verify',
            'label'       => 'email.tpl.verify.label',
            'description' => 'email.tpl.verify.desc',
            'group'       => 'auth',
            'vars'        => ['url' => 'email.var.verify_url'],
        ]);
        Tiger_Mail::registerTemplate([
            'key'         => 'register-verify',
            'label'       => 'email.tpl.register_verify.label',
            'description' => 'email.tpl.register_verify.desc',
            'group'       => 'auth',
            'vars'        => ['url' => 'email.var.verify_url', 'domain' => 'email.var.domain'],
        ]);
        Tiger_Mail::registerTemplate([
            'key'         => 'backup',
            'label'       => 'email.tpl.backup.label',
            'description' => 'email.tpl.backup.desc',
            'group'       => 'system',
            'vars'        => [
                'filename'   => 'email.var.filename',
                'size'       => 'email.var.size',
                'components' => 'email.var.components',
                'error'      => 'email.var.error',
            ],
        ]);
    }
}
