<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Access module — English strings. Semantic, owner-prefixed keys (access.*).
 */
return [
    // --- Shared labels (form fields + table columns) ---
    'access.label.name'          => 'Name',
    'access.label.slug'          => 'Slug',
    'access.label.status'        => 'Status',
    'access.label.created'       => 'Created',
    'access.label.email'         => 'Email',
    'access.label.username'      => 'Username',
    'access.label.parent'        => 'Parent',
    'access.label.members'       => 'Members',
    'access.label.roles'         => 'Roles',
    'access.label.orgs'          => 'Orgs',
    'access.label.actions'       => 'Actions',

    // --- Common actions / UI bits ---
    'access.action.save'         => 'Save',
    'access.action.cancel'       => 'Cancel',
    'access.form.none'           => '—',

    // --- Filter toolbar ---
    'access.filter.all_statuses' => 'All statuses',
    'access.filter.clear'        => 'Clear',
    'access.filter.clear_title'  => 'Clear filters',

    // --- Status values ---
    'access.status.active'       => 'Active',
    'access.status.suspended'    => 'Suspended',

    // --- Users: list ---
    'access.user.list.title'     => 'Users',
    'access.user.list.subtitle'  => 'Identities — email, username, status, and membership.',
    'access.user.list.new'       => 'New User',

    // --- Users: editor ---
    'access.user.edit.title_new'  => 'New User',
    'access.user.edit.title_edit' => 'Edit User',
    'access.user.edit.back'       => 'Back to users',
    'access.user.field.email_help'          => 'The canonical login identifier. Must be unique.',
    'access.user.field.username_help'       => 'Optional. Unique if set.',
    'access.user.field.language'            => 'Language',
    'access.user.field.language_help'       => "The user's preferred language.",
    'access.user.field.timezone'            => 'Timezone',
    'access.user.field.timezone_placeholder'=> 'Search by city, abbreviation (EST), or offset (-05:00)…',
    'access.user.field.password'            => 'Set Password',
    'access.user.field.password_help'       => 'Leave blank to keep the current password. Setting it here resets it immediately.',

    // --- Users: /api service messages ---
    'access.user.saved'          => 'User saved.',
    'access.user.deleted'        => 'User deleted.',
    'access.user.email_taken'    => 'That email is already in use.',
    'access.user.username_taken' => 'That username is already in use.',
    'access.user.no_self_delete' => 'You cannot delete your own account.',

    // --- Organizations: list ---
    'access.org.list.title'      => 'Organizations',
    'access.org.list.subtitle'   => 'Tenants — name, slug, hierarchy, and membership.',
    'access.org.list.new'        => 'New Organization',

    // --- Organizations: editor ---
    'access.org.edit.title_new'  => 'New Organization',
    'access.org.edit.title_edit' => 'Edit Organization',
    'access.org.edit.back'       => 'Back to organizations',
    'access.org.field.slug_help'    => 'URL-safe identifier. Auto-derived from the name if left blank; must be unique.',
    'access.org.field.parent'       => 'Parent organization',
    'access.org.field.parent_help'  => 'For sub-tenants; leave as “none” for a root organization.',
    'access.org.parent.none'        => '— none (root organization) —',

    // --- Organizations: /api service messages ---
    'access.org.saved'           => 'Organization saved.',
    'access.org.deleted'         => 'Organization deleted.',
    'access.org.slug_taken'      => 'That slug is already in use.',
    'access.org.slug_required'   => 'A slug is required (or provide a name to derive one from).',
    'access.org.parent_self'     => 'An organization cannot be its own parent.',
    'access.org.no_self_delete'  => 'You cannot delete the organization you are currently acting in.',

    // --- JS-facing strings (registered via $this->i18n, resolved by Tiger.t) ---
    'access.js.search_orgs'         => 'Search name / slug…',
    'access.js.search_users'        => 'Search email / username…',
    'access.js.edit'                => 'Edit',
    'access.js.delete'              => 'Delete',
    'access.js.org_no_delete'       => "Your active organization can't be deleted",
    'access.js.delete_self'         => 'You cannot delete yourself',
    'access.js.not_permitted'       => 'Not permitted',
    'access.js.confirm_delete_org'  => 'Delete this organization? It is soft-deleted and can be recovered.',
    'access.js.confirm_delete_user' => 'Delete this user? They are soft-deleted and can be recovered.',
    'access.js.fix_fields'          => 'Please fix the highlighted fields and try again.',
    'access.js.network_error'       => 'Network error — please try again.',
    'access.js.parent_root'         => '— root —',
];
