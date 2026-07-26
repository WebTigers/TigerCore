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
    'system.settings.saved'     => 'Settings saved.',
    'system.dashboard.saved'    => 'Dashboard layout saved.',
    'system.update.done'          => 'Updates applied.',
    'system.update.none_selected' => 'Select at least one update to apply.',
    'system.acl.unavailable'      => 'The ACL engine isn\'t available in this request.',
];
