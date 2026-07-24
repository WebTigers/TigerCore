<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * TigerAgent — English strings. Semantic, owner-prefixed keys (AGENTS.md i18n).
 */
return [
    // Settings screen
    'agent.settings.title'      => 'AI Agent',
    'agent.settings.subtitle'   => 'Connect your own AI account and let the agent work inside your site.',
    'agent.settings.saved'      => 'Agent settings saved.',
    'agent.settings.provider'   => 'Provider',
    'agent.settings.model'      => 'Model',
    'agent.settings.model.ph'   => 'e.g. claude-sonnet-5',
    'agent.settings.key'        => 'API key',
    'agent.settings.key.ph'     => 'Paste a key to connect (leave blank to keep the current one)',
    'agent.settings.enabled'    => 'Enable the AI agent',
    'agent.settings.connected'  => 'Connected — a key is stored (encrypted).',
    'agent.settings.disconnected' => 'Not connected — paste an API key to enable the agent.',
    'agent.settings.mode_max'   => 'Automation ceiling',
    'agent.settings.mode_max.help' => 'The highest automation level anyone here may use. Users can dial down, never past this.',
    'agent.settings.mode.ask'   => 'Ask — approve every change (safest)',
    'agent.settings.mode.auto'  => 'Auto — routine changes run automatically; code/files still ask',
    'agent.settings.mode.yolo'  => 'YOLO — everything the role allows runs automatically',

    // Aside modes
    'agent.mode.ask'            => 'Ask',
    'agent.mode.auto'           => 'Auto',
    'agent.mode.yolo'           => 'YOLO',
    'agent.mode.ask.hint'       => 'Approve every change',
    'agent.mode.auto.hint'      => 'Routine changes auto-run; code/files ask',
    'agent.mode.yolo.hint'      => 'Everything auto-runs — hold on tight',

    // Turn results
    'agent.turn.ok'             => 'Done.',
    'agent.approve.ok'          => 'Actions completed.',

    // Attachments (drag-drop / paperclip)
    'agent.file.attached'       => 'File attached.',
    'agent.file.type'           => 'That file type isn’t supported.',
    'agent.file.too_large'      => 'That file is too large.',
    'agent.file.failed'         => 'The file couldn’t be attached. Please try again.',

    // Errors
    'agent.error.empty'         => 'Type a message for the agent.',
    'agent.error.unconfigured'  => 'The AI agent isn’t connected yet. Add an API key under Settings → AI Agent.',
    'agent.error.provider'      => 'The AI provider couldn’t be reached. Check the key and try again.',
    'agent.error.run_missing'   => 'That conversation or turn is no longer available.',

    // Aside UI
    'agent.aside.title'         => 'Agent',
    'agent.aside.placeholder'   => 'Ask the agent to build, change, or explain something…',
    'agent.aside.new'           => 'New chat',
    'agent.aside.send'          => 'Send',
    'agent.aside.approve'       => 'Approve',
    'agent.aside.approve_all'   => 'Approve all',
    'agent.aside.thinking'      => 'Working…',
    'agent.aside.empty'         => 'Start a conversation — the agent acts with your permissions.',
];
