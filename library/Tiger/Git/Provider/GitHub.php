<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Git_Provider_GitHub — the GitHub adapter: WHICH host, composing the two transports (TIGER-334).
 *
 * The transports answer HOW a git operation runs (the `git` binary, or the GitHub API with no `.git`);
 * this provider answers WHICH host and composes them into one object a service can use without caring:
 *
 *   - It picks the WORKING transport for a connection — the git binary where the host allows it, the API
 *     otherwise — and routes the git operations (status, diff, commit, branch, switch, push, pull, tag)
 *     to it. A connection stores the transport it chose at connect time (Tiger_Git_Environment detects
 *     it, re-checkable from the UI); the provider honours that, falling back to live detection only when
 *     a tree names none, and degrading a stale "shell" choice to the API when the host can no longer run
 *     a subprocess.
 *   - It ALWAYS routes the provider-API operations — listing releases and fetching a release archive —
 *     to the API transport, because a GitHub *release* is not a git concept: there is no git command for
 *     it, so even a shell-transport connection reaches releases over the API. This is the one place both
 *     transports are used together, and the reason the provider exists.
 *
 * v1 speaks only GitHub. The split (provider = host, transport = mechanism) is what lets a GitLab or
 * Bitbucket adapter land later as another Tiger_Git_Provider_Interface without touching the transports
 * or the callers.
 *
 * @api
 * @since 1.38.0
 */
class Tiger_Git_Provider_GitHub implements Tiger_Git_Provider_Interface
{
    const PROVIDER = 'github';

    /** @var Tiger_Git_Transport_Api the API transport — always available (release ops + API working) */
    private Tiger_Git_Transport_Api $api;

    /** @var Tiger_Git_Transport_Shell the git-binary transport — used when a connection chose it */
    private Tiger_Git_Transport_Shell $shell;

    /** @var ?string memoized host transport recommendation (for name()) */
    private ?string $hostTransport = null;

    /**
     * @param Tiger_Git_Credential $credentials the write-only credential manager both transports resolve through
     * @param callable|null        $http        HTTP seam for the API transport (tests); null = real cURL
     * @param callable|null        $runner      command-runner seam for the shell transport (tests); null = real proc_open
     */
    public function __construct(Tiger_Git_Credential $credentials, ?callable $http = null, ?callable $runner = null)
    {
        $this->api   = new Tiger_Git_Transport_Api($credentials, $http);
        $this->shell = new Tiger_Git_Transport_Shell($credentials, $runner);
    }

    /** The provider/host key. */
    public function provider(): string
    {
        return self::PROVIDER;
    }

    /**
     * The working transport recommended for THIS host (detected once, memoized) — not a specific
     * connection. A connection stores its own chosen transport (set at connect, re-checkable); this is
     * the host-capability answer, used for display and as the fallback when a tree names no transport.
     */
    public function name(): string
    {
        if ($this->hostTransport === null) {
            $env = Tiger_Git_Environment::detect();
            $this->hostTransport = $env['transport'] !== '' ? $env['transport'] : Tiger_Git::TRANSPORT_API;
        }
        return $this->hostTransport;
    }

    // --- git operations → the connection's working transport -------------------------------------

    public function status(Tiger_Git_WorkingTree $tree): array
    {
        return $this->working($tree)->status($tree);
    }

    public function diff(Tiger_Git_WorkingTree $tree, array $opts = []): array
    {
        return $this->working($tree)->diff($tree, $opts);
    }

    public function commit(Tiger_Git_WorkingTree $tree, string $message, array $paths = []): string
    {
        return $this->working($tree)->commit($tree, $message, $paths);
    }

    public function branches(Tiger_Git_WorkingTree $tree): array
    {
        return $this->working($tree)->branches($tree);
    }

    public function createBranch(Tiger_Git_WorkingTree $tree, string $name): void
    {
        $this->working($tree)->createBranch($tree, $name);
    }

    public function switchBranch(Tiger_Git_WorkingTree $tree, string $name): void
    {
        $this->working($tree)->switchBranch($tree, $name);
    }

    public function push(Tiger_Git_WorkingTree $tree, string $branch): array
    {
        return $this->working($tree)->push($tree, $branch);
    }

    public function pull(Tiger_Git_WorkingTree $tree, string $branch): array
    {
        return $this->working($tree)->pull($tree, $branch);
    }

    public function tag(Tiger_Git_WorkingTree $tree, string $tag, ?string $ref = null): array
    {
        return $this->working($tree)->tag($tree, $tag, $ref);
    }

    // --- provider-API operations → ALWAYS the API transport --------------------------------------

    public function listReleases(Tiger_Git_WorkingTree $tree): array
    {
        return $this->api->listReleases($tree);
    }

    public function fetchArchive(Tiger_Git_WorkingTree $tree, string $tag, string $destFile): string
    {
        return $this->api->fetchArchive($tree, $tag, $destFile);
    }

    // --- internals -------------------------------------------------------------------------------

    /**
     * The working transport for a connection: the git binary when the connection chose shell AND the
     * host can still run a subprocess, the API otherwise. A tree that names no transport falls back to
     * host detection; a stale "shell" choice on a host that lost `exec` degrades to the API rather than
     * failing every operation.
     */
    private function working(Tiger_Git_WorkingTree $tree): Tiger_Git_Transport_Interface
    {
        $want = $tree->transport();
        if ($want === '') { $want = $this->name(); }
        if ($want === Tiger_Git::TRANSPORT_SHELL && Tiger_Git_Environment::execUsable()) {
            return $this->shell;
        }
        return $this->api;
    }
}
