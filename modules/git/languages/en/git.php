<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/** TigerGit module — English strings (TIGER-334 / TIGER-367). Sibling locales follow with the UI. */
return [
    // success
    'git.repo.connected'        => 'Repository connected.',
    'git.repo.disconnected'     => 'Repository disconnected.',
    'git.release.tagged'        => 'Release tag created.',
    'git.release.submitted'     => 'Release submitted for QA.',
    'git.release.decided'       => 'Release decision recorded.',
    'git.update.applied'        => 'Update applied.',
    'git.update.policy_set'     => 'Update policy saved.',
    'git.update.rolled_back'    => 'Rolled back to the previous version.',

    // input / state errors
    'git.error.invalid_module'  => 'That module key is not valid.',
    'git.error.invalid_repo'    => 'That repository owner/name is not valid.',
    'git.error.module_missing'  => 'That module is not installed on this server.',
    'git.error.not_connected'   => 'This module is not connected to a repository.',
    'git.error.not_allowed'     => 'You do not have permission to do that.',
    'git.error.invalid_version' => 'The version must be a semantic version, e.g. 1.0.0.',
    'git.error.tag_exists'      => 'That version already has a release — tags are immutable; use a new version.',
    'git.error.release_exists'  => 'A release for that version is already in progress.',
    'git.error.invalid_decision'=> 'The decision must be approve or reject.',
    'git.error.release_not_found'=> 'That release could not be found.',
    'git.error.release_decided' => 'That release has already been decided.',
    'git.error.no_update'       => 'There is no newer approved release to apply.',
    'git.error.not_approved'    => 'That version is not an approved release.',
    'git.error.version_pinned'  => 'That version is pinned after a rollback — choose a different one.',
    'git.error.no_previous'     => 'There is no previous version to roll back to.',
    'git.error.invalid_channel' => 'The update policy must be auto, notify, or pinned.',
    'git.error.install_failed'  => 'The update downloaded but could not be installed.',

    // transport / provider errors (mapped from Tiger_Git_Exception codes)
    'git.error.credential_invalid'    => 'GitHub rejected the credential. Check the token and its access.',
    'git.error.credential_missing'    => 'No usable credential is configured for this connection.',
    'git.error.non_fast_forward'      => 'The branch has moved — this is not a fast-forward. Pull first.',
    'git.error.rate_limited'          => 'GitHub’s rate limit was reached. Try again shortly.',
    'git.error.host_not_allowed'      => 'That request would leave the allowed GitHub hosts.',
    'git.error.invalid_ref'           => 'That repository, branch, or ref could not be found.',
    'git.error.protected_branch'      => 'That branch is protected and cannot be changed this way.',
    'git.error.dirty_tree'            => 'There are uncommitted changes — commit or discard them first.',
    'git.error.nothing_to_commit'     => 'There is nothing to commit.',
    'git.error.transport_unavailable' => 'No usable git transport is available on this server.',
    'git.error.not_implemented'       => 'That operation is not available on this connection’s transport yet.',
    'git.error.general'               => 'The git operation could not be completed.',
];
