<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Git_Transport_Interface — HOW a git operation is carried out (TIGER-334).
 *
 * The load-bearing abstraction of the module: two implementations, one contract, and callers that
 * never know which is active.
 *   - Tiger_Git_Transport_Shell drives the `git` binary (proc_open) when the host permits it.
 *   - Tiger_Git_Transport_Api creates commits through the GitHub REST/GraphQL API, with NO `.git`
 *     directory — the path that makes a shared-hosting account with `exec` disabled work.
 *
 * Every method operates on ONE module working tree (a Tiger_Git_WorkingTree: the module directory +
 * its connection). Results MUST be equivalent across transports for: status, commit, branch
 * list/create/switch, push (fast-forward only), pull (fast-forward only), tag, list releases, fetch a
 * release archive. A transport NEVER force-pushes, rewrites history, deletes a tag, runs an arbitrary
 * git command, or reaches a host outside Tiger_Git::ALLOWED_HOSTS. On any failure it throws a
 * Tiger_Git_Exception carrying a structured, secret-free error code — never a stack trace or a token.
 *
 * @api
 * @since 1.38.0
 */
interface Tiger_Git_Transport_Interface
{
    /** The transport name — Tiger_Git::TRANSPORT_SHELL or TRANSPORT_API. */
    public function name(): string;

    /**
     * Current working-tree status.
     *
     * @return array{branch:string, ahead:int, behind:int, changed:array<int,array{path:string,state:string}>, clean:bool}
     */
    public function status(Tiger_Git_WorkingTree $tree): array;

    /**
     * A size-capped diff summary for the working tree (never full file bodies beyond the cap).
     *
     * @param  array $opts {paths?:string[], max_bytes?:int}
     * @return array{files:array<int,array{path:string,added:int,removed:int,binary:bool}>, truncated:bool}
     */
    public function diff(Tiger_Git_WorkingTree $tree, array $opts = []): array;

    /**
     * Commit the given paths (relative to the module dir; empty = all tracked changes) on the current
     * branch and return the new commit SHA.
     *
     * @param  string   $message the commit message
     * @param  string[] $paths   module-relative paths, or [] for all changes
     * @return string            the new commit SHA
     */
    public function commit(Tiger_Git_WorkingTree $tree, string $message, array $paths = []): string;

    /** Local branch names. @return string[] */
    public function branches(Tiger_Git_WorkingTree $tree): array;

    /** Create a branch off the current HEAD (never a protected/default branch name). */
    public function createBranch(Tiger_Git_WorkingTree $tree, string $name): void;

    /** Switch the working tree to an existing branch (refuses on a dirty tree). */
    public function switchBranch(Tiger_Git_WorkingTree $tree, string $name): void;

    /**
     * Push the current branch to the remote. Fast-forward only — a non-fast-forward is reported, never
     * forced.
     *
     * @return array{ref:string, pushed:bool}
     */
    public function push(Tiger_Git_WorkingTree $tree, string $branch): array;

    /**
     * Fast-forward pull of $branch. A non-fast-forward (divergence) or a merge conflict aborts cleanly,
     * leaves the working tree unchanged, and is reported.
     *
     * @return array{ref:string, updated:bool, conflicts:string[]}
     */
    public function pull(Tiger_Git_WorkingTree $tree, string $branch): array;

    /**
     * Create an annotated tag at $ref (default: current HEAD of the default branch). Semver is enforced
     * by the caller; a submitted tag is immutable (never moved or deleted).
     *
     * @return array{tag:string, sha:string}
     */
    public function tag(Tiger_Git_WorkingTree $tree, string $tag, ?string $ref = null): array;

    /**
     * Releases on the remote, newest first.
     *
     * @return array<int,array{tag:string, name:string, sha:string, created_at:string, prerelease:bool}>
     */
    public function listReleases(Tiger_Git_WorkingTree $tree): array;

    /**
     * Download a release archive (the tag's tree) to $destFile and return its path. The ONLY artifact
     * an update ever installs — never an arbitrary branch.
     *
     * @return string the written file path
     */
    public function fetchArchive(Tiger_Git_WorkingTree $tree, string $tag, string $destFile): string;
}
