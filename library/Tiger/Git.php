<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Git — the facade + shared constants for the platform's one git/GitHub layer (TIGER-334).
 *
 * This is the single trusted entry point for every git interaction in the platform: versioning a
 * custom module in a private repo, cutting a release through QA, and receiving approved updates — with
 * no local machine, no terminal, and (on a shared-hosting account with `exec` disabled) no git binary.
 * No other module talks to GitHub directly.
 *
 * The design is two interchangeable TRANSPORTS behind one interface
 * (Tiger_Git_Transport_Interface): a ShellTransport that drives the `git` binary where the host allows
 * it, and an ApiTransport that creates commits through the GitHub API with no working `.git` directory
 * at all. Callers never know which is active; capability detection (Tiger_Git_Environment) chooses.
 *
 * This class holds only cross-cutting constants + small pure helpers. The work lives in the services
 * (Tiger_Git_Service_*), the transports, and the models. Authorization is expressed as ACL privileges
 * — never a role-string compare (TIGER-335): the sharp owner-only operations (tag, release submit) are
 * a CAPABILITY resource, RESOURCE_RELEASE, checked with `_isAllowed('tag', Tiger_Git::RESOURCE_RELEASE)`.
 *
 * @api
 * @since 1.38.0
 */
class Tiger_Git
{
    /** Transport names (Tiger_Git_Environment picks one per instance; stored on the connection). */
    const TRANSPORT_SHELL = 'shell';
    const TRANSPORT_API   = 'api';

    /**
     * The release CAPABILITY resource (ACL). The owner-only operations — creating a semver tag and
     * submitting a release to QA — must be STRICTER than the service's own grant (editors/admins reach
     * the service, but only an owner may tag/submit). Expressing that as a privilege on a capability
     * resource — `_isAllowed('tag', Tiger_Git::RESOURCE_RELEASE)` — is the proven Tiger_Message_Compose
     * / Tiger_Agent_Forge shape; a resource named after a role tier would just be a role compare with
     * one level of indirection. Granted to the module-owner role in modules/git/configs/acl.ini.
     */
    const RESOURCE_RELEASE = 'Tiger_Git_Release';

    /** Release-capability privileges. */
    const PRIV_TAG    = 'tag';
    const PRIV_SUBMIT = 'submit';

    /** A connection's lifecycle status. */
    const STATUS_CONNECTED    = 'connected';   // verified, read (and write when enabled) work
    const STATUS_DEGRADED     = 'degraded';    // credential revoked/expired at GitHub; local reads still OK
    const STATUS_REVOKED      = 'revoked';     // disconnected by the owner

    /** Update policy per installed module. */
    const POLICY_AUTO   = 'auto';
    const POLICY_NOTIFY = 'notify';   // default
    const POLICY_PINNED = 'pinned';

    /** The only hosts Tiger_Git ever reaches (outbound allowlist). */
    const ALLOWED_HOSTS = ['github.com', 'api.github.com', 'raw.githubusercontent.com', 'codeload.github.com'];

    /**
     * Is a string a valid semantic version (optionally `v`-prefixed, optional pre-release/build)?
     * Tag creation and the manifest version are both validated against this.
     *
     * @param  string $version e.g. "1.0.0", "v2.3.1-beta.1"
     * @return bool
     */
    public static function isSemver($version)
    {
        return (bool) preg_match(
            '/^v?(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/',
            (string) $version
        );
    }

    /**
     * Normalize a version for comparison: strip a leading `v`. (Not a comparator — just canonicalizes
     * what a tag and a manifest might each write differently.)
     *
     * @param  string $version
     * @return string
     */
    public static function normalizeVersion($version)
    {
        return ltrim((string) $version, 'vV');
    }

    /**
     * Is $host one of the hosts Tiger_Git is permitted to reach? Enforced before any outbound request;
     * a transport must refuse anything else rather than follow a redirect off-allowlist.
     *
     * @param  string $host
     * @return bool
     */
    public static function isAllowedHost($host)
    {
        return in_array(strtolower((string) $host), self::ALLOWED_HOSTS, true);
    }

    /**
     * Validate an `owner/repo` pair to GitHub's own character rules (so it can never be bent into a
     * path-traversal or a second path segment). Returns [owner, repo] or null.
     *
     * @param  string $owner
     * @param  string $repo
     * @return array{0:string,1:string}|null
     */
    public static function repoSlug($owner, $repo)
    {
        $owner = (string) $owner; $repo = (string) $repo;
        $ok = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,99}$/';
        if (!preg_match($ok, $owner) || !preg_match($ok, $repo)) { return null; }
        return [$owner, $repo];
    }
}
