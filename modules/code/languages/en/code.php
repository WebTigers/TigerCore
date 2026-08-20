<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger Code module — English strings (code.*). Loaded on top of core/app strings by the
 * translate cascade; API response messages resolve these in the caller's locale.
 */
return [
    // API responses
    'code.saved'       => 'Snippet saved.',
    'code.activated'   => 'Snippet activated — live now.',
    'code.deactivated' => 'Snippet deactivated.',
    'code.deleted'     => 'Snippet deleted.',
    'code.restored'    => 'Snippet restored to the selected version.',

    // API errors (prose prefixes concatenated with a technical detail, + standalone)
    'code.error.not_saved'                => 'Not saved —',
    'code.error.saved_not_activated'      => 'Saved, but not activated — it conflicts with the running set:',
    'code.error.cannot_activate'          => 'Cannot activate —',
    'code.error.cannot_activate_conflict' => 'Cannot activate — it conflicts with the running set:',
    'code.error.snippet_unavailable'      => 'That snippet is no longer available — the module may have been removed.',

    // admin list
    'code.list.title'  => 'Code',
    'code.list.new'    => 'New snippet',
    'code.list.subtitle_a'       => 'PHP snippets that run across the platform — compiled + cached, executed on every request. Local snippets are stored in the DB;',
    'code.list.subtitle_b'       => 'snippets come from installed code modules (read the source before you activate).',
    'code.list.badge_module'     => 'module',
    'code.list.badge_superadmin' => 'superadmin',
    'code.list.col_name'     => 'Name',
    'code.list.col_lang'     => 'Lang',
    'code.list.col_runs'     => 'Runs',
    'code.list.col_priority' => 'Priority',
    'code.list.col_state'    => 'State',
    'code.list.col_updated'  => 'Updated',
    'code.list.col_actions'  => 'Actions',

    // view-source modal
    'code.source.title'    => 'Snippet source',
    'code.source.close'    => 'Close',
    'code.source.warn'     => 'Activating runs this PHP in your app.',
    'code.source.activate' => 'Activate',

    // snippet editor
    'code.edit.edit_title' => 'Edit Snippet',
    'code.edit.new_title'  => 'New Snippet',
    'code.edit.back'       => 'Back to code',
    'code.edit.cancel'     => 'Cancel',
    'code.edit.save'       => 'Save',
    'code.edit.warn'       => "This PHP runs on <strong>every request</strong> once active. It's linted on save and auto-deactivates if it fatals on load.",
    'code.edit.name'       => 'Name',
    'code.edit.code'       => 'Code',
    'code.edit.type'       => 'Type',
    'code.edit.language'   => 'Language',
    'code.edit.inject_at'  => 'Inject at',
    'code.edit.inject_hint'      => 'Where injected CSS/JS/HTML/PHTML lands.',
    'code.edit.activation'       => 'Activation',
    'code.edit.active_label'     => 'Active — run this snippet',
    'code.edit.priority'         => 'Priority',
    'code.edit.priority_hint'    => 'Lower loads first. Runs globally (every request).',
    'code.edit.notes'            => 'Notes',
    'code.edit.description'      => 'Description',
    'code.edit.description_hint' => 'What this snippet does (for the list).',

    // snippet editor — version history
    'code.edit.versions'       => 'Version history',
    'code.edit.col_version'    => 'Version',
    'code.edit.col_name'       => 'Name',
    'code.edit.col_state'      => 'State',
    'code.edit.col_saved'      => 'Saved',
    'code.edit.state_active'   => 'Active',
    'code.edit.state_inactive' => 'Inactive',
    'code.edit.untitled'       => '(untitled)',
    'code.edit.restore'        => 'Restore',

    // form — language select
    'code.lang.php'   => 'PHP — runs on every request (functions/hooks)',
    'code.lang.phtml' => 'PHTML — rendered + injected',
    'code.lang.html'  => 'HTML — injected verbatim',
    'code.lang.css'   => 'CSS — injected as a stylesheet',
    'code.lang.js'    => 'JavaScript — injected as a script',

    // form — inject-at select
    'code.auto.head'   => 'Head',
    'code.auto.footer' => 'Footer',
];
