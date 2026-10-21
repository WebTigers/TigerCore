<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Git_Provider_Interface — WHICH host a connection talks to (TIGER-334).
 *
 * A provider is the layer above the transports. Where Tiger_Git_Transport_Interface answers HOW an
 * operation runs (the `git` binary, or the GitHub API with no `.git`), a provider answers WHICH host —
 * and composes the transports so a caller never has to: it picks the working transport for a connection
 * and routes the host's provider-API operations (releases, archives) to the API regardless of which
 * transport is doing the git work. A provider therefore IS a transport (the composite one services use),
 * plus a stable `provider()` key.
 *
 * v1 ships exactly one — Tiger_Git_Provider_GitHub. The split (provider = host, transport = mechanism)
 * is the cheap convention that lets a GitLab/Bitbucket adapter land later as another implementation of
 * this interface without touching the transports or the callers.
 *
 * @api
 * @since 1.38.0
 */
interface Tiger_Git_Provider_Interface extends Tiger_Git_Transport_Interface
{
    /** The provider/host key, e.g. 'github'. Stable — used for storage, routing, and display. */
    public function provider(): string;
}
