<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Git_WorkingTree — one module's git working tree (TIGER-334).
 *
 * An immutable value object handed to a transport: the ABSOLUTE module directory (the git root — the
 * tree never extends beyond it) plus the connection facts a transport needs (owner/repo, default and
 * current branch, which transport, the credential REF). It carries no secret — only a `credentialRef`
 * (a Tiger_Secret ref or a git_credential id) that CredentialService resolves at the moment of use and
 * never hands back. `dir()` is validated on construction to be an existing directory; every path a
 * transport touches is resolved under it and refused if it escapes (symlink/`..`), so "operations are
 * restricted to the module directory" is enforced structurally, not by discipline.
 *
 * @api
 * @since 1.38.0
 */
final class Tiger_Git_WorkingTree
{
    private string $dir;
    private string $owner;
    private string $repo;
    private string $defaultBranch;
    private string $currentBranch;
    private string $transport;
    private string $credentialRef;
    private bool   $writeEnabled;

    /**
     * @param string $dir           absolute module directory (the git root)
     * @param string $owner         repo owner (validated)
     * @param string $repo          repo name (validated)
     * @param string $defaultBranch e.g. "main"
     * @param string $currentBranch the checked-out/working branch
     * @param string $transport     Tiger_Git::TRANSPORT_SHELL|TRANSPORT_API
     * @param string $credentialRef opaque credential reference (never a secret)
     * @param bool   $writeEnabled  whether the connection permits pushes
     */
    public function __construct(
        string $dir,
        string $owner,
        string $repo,
        string $defaultBranch,
        string $currentBranch,
        string $transport,
        string $credentialRef = '',
        bool $writeEnabled = false
    ) {
        $real = $dir !== '' ? realpath($dir) : false;
        if ($real === false || !is_dir($real)) {
            throw new Tiger_Git_Exception(Tiger_Git_Exception::INVALID_REF, 'module directory does not exist');
        }
        if (Tiger_Git::repoSlug($owner, $repo) === null) {
            throw new Tiger_Git_Exception(Tiger_Git_Exception::INVALID_REF, 'invalid repo owner/name');
        }
        $this->dir           = $real;
        $this->owner         = $owner;
        $this->repo          = $repo;
        $this->defaultBranch = $defaultBranch;
        $this->currentBranch = $currentBranch;
        $this->transport     = $transport;
        $this->credentialRef = $credentialRef;
        $this->writeEnabled  = $writeEnabled;
    }

    public function dir(): string           { return $this->dir; }
    public function owner(): string         { return $this->owner; }
    public function repo(): string          { return $this->repo; }
    public function slug(): string          { return $this->owner . '/' . $this->repo; }
    public function defaultBranch(): string { return $this->defaultBranch; }
    public function currentBranch(): string { return $this->currentBranch; }
    public function transport(): string     { return $this->transport; }
    public function credentialRef(): string { return $this->credentialRef; }
    public function writeEnabled(): bool    { return $this->writeEnabled; }

    /**
     * Resolve a module-relative path to an absolute path UNDER the tree root, or throw PATH_ESCAPE.
     * The guard for every file a transport reads/writes — `..`, an absolute path, or a symlink that
     * points outside the module dir are all refused.
     *
     * @param  string $relative a module-relative path
     * @return string           the safe absolute path (may not yet exist, but its parent is in-tree)
     */
    public function resolve(string $relative): string
    {
        $relative = str_replace('\\', '/', $relative);
        if ($relative === '' || $relative[0] === '/' || preg_match('~(^|/)\.\.(/|$)~', $relative)) {
            throw new Tiger_Git_Exception(Tiger_Git_Exception::PATH_ESCAPE, 'path escapes the module directory');
        }
        $target = $this->dir . '/' . $relative;
        // For an existing path, the real path must sit under the root (catches symlink escapes).
        $real = realpath($target);
        if ($real !== false) {
            if ($real !== $this->dir && strpos($real, $this->dir . '/') !== 0) {
                throw new Tiger_Git_Exception(Tiger_Git_Exception::PATH_ESCAPE, 'path escapes the module directory');
            }
            return $real;
        }
        // For a not-yet-existing path, its nearest existing ancestor must be in-tree.
        $parent = realpath(dirname($target));
        if ($parent === false || ($parent !== $this->dir && strpos($parent, $this->dir . '/') !== 0)) {
            throw new Tiger_Git_Exception(Tiger_Git_Exception::PATH_ESCAPE, 'path escapes the module directory');
        }
        return $target;
    }

    /** A copy on a different current branch (after a switch). */
    public function withBranch(string $branch): self
    {
        return new self($this->dir, $this->owner, $this->repo, $this->defaultBranch, $branch, $this->transport, $this->credentialRef, $this->writeEnabled);
    }
}
