<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * System module — English strings (system.*). Loaded on top of core/app strings by the
 * translate cascade; API response messages resolve these in the caller's locale.
 */
return [
    'system.module.activated'   => 'Module activated.',
    'system.module.deactivated' => 'Module deactivated.',
    'system.theme.activated'    => 'Theme activated.',
    'system.theme.deactivated'  => 'Theme deactivated — reverted to the default theme.',
    'system.module.installed'   => 'Module installed and activated.',
    'system.module.deleted'     => 'Module deleted — its files and data were removed.',
    'system.error.protected'    => 'That module is protected and can\'t be deactivated.',
    'system.error.unknown'      => 'No such module.',
    'system.error.not_deletable'  => 'That\'s a bundled platform module — it can\'t be deleted.',
    'system.error.delete_active'  => 'Deactivate this module before you delete it.',
    'system.error.delete_confirm' => 'The confirmation didn\'t match — nothing was deleted.',
    'system.error.delete_dependents' => 'Another active module depends on this one — remove it first.',
    'system.error.conflict'          => 'This module can\'t run alongside another that\'s active.',
    'system.module.conflict_switched' => 'Module activated — the conflicting module was deactivated.',
    'system.settings.saved'     => 'Settings saved.',
    'system.dashboard.saved'    => 'Dashboard layout saved.',
    'system.update.done'          => 'Updates applied.',
    'system.update.none_selected' => 'Select at least one update to apply.',
    'system.acl.unavailable'      => 'The ACL engine isn\'t available in this request.',
    'system.pass.activated'       => 'TigerPASS active — every premium module is unlocked.',
    'system.pass.removed'         => 'TigerPASS key removed from this install.',
    'system.pass.invalid_format'  => 'That doesn\'t look like a TigerPASS key (it\'s a code like 019f88b1-7ce7-7467-95b3-db7a7433342c).',
    'system.pass.not_configured'  => 'TigerPASS isn\'t configured on this install yet.',
    'system.pass.lapsed'          => 'That subscription has lapsed — renew it at webtigers.com, then try again.',
    'system.pass.unverified'      => 'We couldn\'t verify that key with WebTigers. Check that you pasted it correctly, or try again in a moment.',
    'system.pass.nag_snoozed'     => 'TigerPASS reminder hidden for 30 days.',
    'system.pass.nag_updated'     => 'Preference saved.',
    'system.source.connected'     => 'Marketplace connected.',
    'system.source.updated'       => 'Source updated.',
    'system.source.removed'       => 'Marketplace removed.',
    'system.source.err_label'     => 'Give the marketplace a name.',
    'system.source.err_url'       => 'Enter a valid http(s) index URL.',
    'system.source.err_unknown'   => 'No such source.',
    'system.source.err_not_removable' => 'Only connected marketplaces can be removed — disable it instead.',
    'system.vendor.required'      => 'Which vendor? (owner/repo)',
    'system.vendor.unreachable'   => 'Couldn\'t read that vendor\'s TigerVendor repo — it\'s missing or has no valid tigervendor.json.',
    'system.vendor.connected'     => 'Vendor read — review the key fingerprint before you trust it.',
    'system.vendor.pinned'        => 'Vendor trusted. You can now install its paid modules.',
    'system.vendor.not_connected' => 'Connect + trust this vendor first (review its key fingerprint).',
    'system.license.incomplete'   => 'A licensed install needs the vendor, the product, and your license key.',
];
