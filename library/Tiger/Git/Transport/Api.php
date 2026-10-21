<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Git_Transport_Api — git operations through the GitHub API, with NO `.git` directory (TIGER-364).
 *
 * The transport that makes shared hosting work: a host with `exec` disabled and no git binary can still
 * commit, branch, list releases and fetch a release archive, because every operation is an authenticated
 * GitHub REST call rather than a subprocess. The module's files live on disk as an ordinary working
 * tree; this transport writes the CHANGED files straight to the remote branch as a new commit.
 *
 * The provider-API operations (listReleases / fetchArchive) also live here, because a GitHub *release*
 * is not a git concept — there is no git command for it. The GitHub adapter (Tiger_Git_Provider_GitHub)
 * routes those to this transport even for a shell-transport connection.
 *
 * status / diff / pull / switchBranch are the no-`.git` RECONCILER: with no local repo there is no index
 * or HEAD, so this transport keeps a tiny merge-base marker (`.tiger-git/head`, the last commit the
 * working tree was synced to — written by commit() and pull()) and compares the on-disk files to it by
 * hashing each as a git blob. That marker is what lets status tell the operator's uncommitted edits from
 * the remote branch simply advancing, and lets pull fast-forward while preserving non-conflicting local
 * work (a file changed on both sides is a MERGE_CONFLICT). The marker dir is never committed (commit()
 * takes explicit paths) and is skipped by the working-tree walk.
 *
 * The spike at the centre of the module is `commit()` — creating a commit with no local repo via the
 * four-step Git Data API dance:
 *   1. read the branch head (ref) and its base tree,
 *   2. POST a blob per changed file (base64), a null-sha entry per deleted file,
 *   3. POST a new tree (on top of the base tree) then a new commit (parent = the old head),
 *   4. fast-forward the branch ref to the new commit — `force:false`, so a branch that moved under us
 *      is reported as NON_FAST_FORWARD, never clobbered.
 * With the API transport there is no local index, so `commit()` writes to the remote in one flow and
 * `push()` is a no-op that reports success — committing IS pushing here.
 *
 * The HTTP layer is a seam (`setHttp`) so the whole flow is unit-tested against canned GitHub responses
 * with no network. The credential is resolved per request through Tiger_Git_Credential (never held), and
 * every call is pinned to api.github.com (the host allowlist) and fails with a structured, secret-free
 * Tiger_Git_Exception.
 *
 * @api
 * @since 1.38.0
 */
class Tiger_Git_Transport_Api implements Tiger_Git_Transport_Interface
{
    private const API = 'https://api.github.com';
    private const HOST = 'api.github.com';

    /** @var Tiger_Git_Credential resolves a connection's credential ref to a token (never returned out) */
    private Tiger_Git_Credential $credentials;

    /** @var null|callable(string,string,array,?string):array the HTTP seam; null = real cURL */
    private $http;

    /** @var array<string,?string> memoized token per credential ref (one resolve per request) */
    private array $tokenCache = [];

    public function __construct(Tiger_Git_Credential $credentials, ?callable $http = null)
    {
        $this->credentials = $credentials;
        $this->http        = $http;
    }

    /** Swap the HTTP transport (tests inject canned GitHub responses). Null restores real cURL. */
    public function setHttp(?callable $http): void
    {
        $this->http = $http;
    }

    public function name(): string
    {
        return Tiger_Git::TRANSPORT_API;
    }

    // --- the spike: create a commit with no local repo -------------------------------------------

    public function commit(Tiger_Git_WorkingTree $tree, string $message, array $paths = []): string
    {
        if (trim($message) === '') {
            throw new Tiger_Git_Exception(Tiger_Git_Exception::INVALID_REF, 'a commit message is required');
        }
        if ($paths === []) {
            // No local index to infer "all changes" from — the caller (the agent/Forge) names the files.
            throw new Tiger_Git_Exception(
                Tiger_Git_Exception::NOTHING_TO_COMMIT,
                'the API transport commits explicit paths (it has no local index to diff against)'
            );
        }
        $branch = $tree->currentBranch();
        $repo   = $this->repoPath($tree);

        // 1. the branch head + its tree.
        $ref     = $this->api($tree, 'GET', $repo . '/git/ref/heads/' . $branch);
        $headSha = (string) ($ref['object']['sha'] ?? '');
        if ($headSha === '') {
            throw new Tiger_Git_Exception(Tiger_Git_Exception::INVALID_REF, 'could not resolve the branch head');
        }
        $headCommit = $this->api($tree, 'GET', $repo . '/git/commits/' . $headSha);
        $baseTree   = (string) ($headCommit['tree']['sha'] ?? '');

        // 2. one tree entry per path — a blob for an add/modify, a null sha for a delete.
        $entries = [];
        foreach ($paths as $rel) {
            $rel = ltrim(str_replace('\\', '/', (string) $rel), '/');
            if ($rel === '') { continue; }
            $abs = $tree->resolve($rel);   // refuses a path that escapes the module dir
            if (is_file($abs)) {
                $blob = $this->api($tree, 'POST', $repo . '/git/blobs', [
                    'content'  => base64_encode((string) file_get_contents($abs)),
                    'encoding' => 'base64',
                ]);
                $entries[] = [
                    'path' => $rel,
                    'mode' => is_executable($abs) ? '100755' : '100644',
                    'type' => 'blob',
                    'sha'  => (string) ($blob['sha'] ?? ''),
                ];
            } else {
                // Not on disk → stage a deletion (GitHub removes it when base_tree is set).
                $entries[] = ['path' => $rel, 'mode' => '100644', 'type' => 'blob', 'sha' => null];
            }
        }
        if ($entries === []) {
            throw new Tiger_Git_Exception(Tiger_Git_Exception::NOTHING_TO_COMMIT, 'no files resolved to commit');
        }

        // 3. new tree → new commit (parent = the old head).
        $newTree = $this->api($tree, 'POST', $repo . '/git/trees', ['base_tree' => $baseTree, 'tree' => $entries]);
        $commit  = $this->api($tree, 'POST', $repo . '/git/commits', [
            'message' => $message,
            'tree'    => (string) ($newTree['sha'] ?? ''),
            'parents' => [$headSha],
        ]);
        $newSha = (string) ($commit['sha'] ?? '');
        if ($newSha === '') {
            throw new Tiger_Git_Exception(Tiger_Git_Exception::API_ERROR, 'commit creation returned no sha');
        }

        // 4. fast-forward the branch to the new commit (force:false → NON_FAST_FORWARD if it moved).
        $this->api($tree, 'PATCH', $repo . '/git/refs/heads/' . $branch, ['sha' => $newSha, 'force' => false]);

        // The working tree is now synced to this commit — record the merge base for status/diff/pull.
        $this->_setBaseSha($tree, $newSha);
        return $newSha;
    }

    /**
     * With the API transport, `commit()` already wrote to the remote branch — there is no separate local
     * repo to push from. So push is a verified no-op: it confirms the branch head and reports it.
     */
    public function push(Tiger_Git_WorkingTree $tree, string $branch): array
    {
        $ref = $this->api($tree, 'GET', $this->repoPath($tree) . '/git/ref/heads/' . $branch);
        return ['ref' => (string) ($ref['object']['sha'] ?? ''), 'pushed' => true];
    }

    public function branches(Tiger_Git_WorkingTree $tree): array
    {
        $list = $this->api($tree, 'GET', $this->repoPath($tree) . '/branches?per_page=100');
        return array_values(array_map(static fn ($b) => (string) ($b['name'] ?? ''), is_array($list) ? $list : []));
    }

    public function createBranch(Tiger_Git_WorkingTree $tree, string $name): void
    {
        if ($name === $tree->defaultBranch()) {
            throw new Tiger_Git_Exception(Tiger_Git_Exception::PROTECTED_BRANCH, 'refusing to recreate the default branch');
        }
        $repo = $this->repoPath($tree);
        $ref  = $this->api($tree, 'GET', $repo . '/git/ref/heads/' . $tree->currentBranch());
        $sha  = (string) ($ref['object']['sha'] ?? '');
        if ($sha === '') {
            throw new Tiger_Git_Exception(Tiger_Git_Exception::INVALID_REF, 'could not resolve the source branch head');
        }
        $this->api($tree, 'POST', $repo . '/git/refs', ['ref' => 'refs/heads/' . $name, 'sha' => $sha]);
    }

    // --- provider-API operations (releases + archives — the GitHub adapter routes these here) ----

    /**
     * The repository's releases, newest first (drafts excluded — a draft has no installable tag). The
     * `sha` is the release's `target_commitish` (a branch name or a commit sha — what the releases
     * endpoint gives without an extra call per release).
     */
    public function listReleases(Tiger_Git_WorkingTree $tree): array
    {
        $list = $this->api($tree, 'GET', $this->repoPath($tree) . '/releases?per_page=100');
        $out  = [];
        foreach (is_array($list) ? $list : [] as $r) {
            if (!is_array($r) || !empty($r['draft'])) { continue; }
            $tag  = (string) ($r['tag_name'] ?? '');
            $name = (string) ($r['name'] ?? '');
            $out[] = [
                'tag'        => $tag,
                'name'       => $name !== '' ? $name : $tag,
                'sha'        => (string) ($r['target_commitish'] ?? ''),
                'created_at' => (string) ($r['created_at'] ?? ''),
                'prerelease' => (bool) ($r['prerelease'] ?? false),
            ];
        }
        return $out;
    }

    /**
     * Download a tag's source archive (`.tar.gz`) to $destFile. GitHub answers the tarball endpoint with
     * a 302 to codeload.github.com; we capture the redirect, confirm it stays on the host allowlist, and
     * stream the bytes from the CDN WITHOUT re-sending our credential (the signed URL carries its own).
     * This is the one artifact an update ever installs — a tag's tree, never an arbitrary branch.
     */
    public function fetchArchive(Tiger_Git_WorkingTree $tree, string $tag, string $destFile): string
    {
        $this->assertTag($tag);
        if (!Tiger_Git::isAllowedHost(self::HOST)) {
            throw new Tiger_Git_Exception(Tiger_Git_Exception::HOST_NOT_ALLOWED, 'api.github.com is not allowed');
        }

        // Ask GitHub for the tarball; it answers a 302 to the download host (codeload, on the allowlist).
        $res    = $this->send('GET', self::API . $this->repoPath($tree) . '/tarball/' . $tag, $this->authHeaders($tree), null);
        $status = (int) ($res['status'] ?? 0);

        if (in_array($status, [301, 302, 303, 307, 308], true)) {
            $loc = $this->header($res, 'location');
            if ($loc === '') {
                throw new Tiger_Git_Exception(Tiger_Git_Exception::API_ERROR, 'the archive redirect carried no location');
            }
            $host = strtolower((string) parse_url($loc, PHP_URL_HOST));
            if (!Tiger_Git::isAllowedHost($host)) {
                throw new Tiger_Git_Exception(Tiger_Git_Exception::HOST_NOT_ALLOWED, 'the archive redirect left the allowed hosts');
            }
            // The signed CDN URL authenticates itself — never forward our token to it.
            $dl      = $this->send('GET', $loc, ['User-Agent' => 'Tiger-Git'], null);
            $dstatus = (int) ($dl['status'] ?? 0);
            if ($dstatus < 200 || $dstatus >= 300) {
                throw $this->mapError($dstatus, [], (string) ($dl['body'] ?? ''));
            }
            $bytes = (string) ($dl['body'] ?? '');
        } elseif ($status >= 200 && $status < 300) {
            $bytes = (string) ($res['body'] ?? '');   // some setups return the bytes directly
        } else {
            $decoded = json_decode((string) ($res['body'] ?? ''), true);
            throw $this->mapError($status, is_array($decoded) ? $decoded : [], (string) ($res['body'] ?? ''));
        }

        if (@file_put_contents($destFile, $bytes) === false) {
            throw new Tiger_Git_Exception(Tiger_Git_Exception::API_ERROR, 'could not write the release archive');
        }
        return $destFile;
    }

    /**
     * Create an annotated tag over the API and point a tag ref at it — no local repo. The target is the
     * commit resolved from $ref (a branch, tag or sha) or, by default, the current branch head. Mirrors
     * the shell transport's annotated-tag semantics; the returned `sha` is the tagged COMMIT (not the
     * tag object), so both transports agree.
     */
    public function tag(Tiger_Git_WorkingTree $tree, string $tag, ?string $ref = null): array
    {
        $this->assertTag($tag);
        $repo = $this->repoPath($tree);

        if ($ref !== null && $ref !== '') {
            $this->assertTag($ref);
            $commit = $this->api($tree, 'GET', $repo . '/commits/' . $ref);   // resolves a branch/tag/sha → a commit
            $sha    = (string) ($commit['sha'] ?? '');
        } else {
            $head = $this->api($tree, 'GET', $repo . '/git/ref/heads/' . $tree->currentBranch());
            $sha  = (string) ($head['object']['sha'] ?? '');
        }
        if ($sha === '') {
            throw new Tiger_Git_Exception(Tiger_Git_Exception::INVALID_REF, 'could not resolve the commit to tag');
        }

        // The annotated tag object, then the ref that points at it (lightweight if the object step is skipped).
        $obj    = $this->api($tree, 'POST', $repo . '/git/tags', ['tag' => $tag, 'message' => $tag, 'object' => $sha, 'type' => 'commit']);
        $tagSha = (string) ($obj['sha'] ?? '');
        $this->api($tree, 'POST', $repo . '/git/refs', ['ref' => 'refs/tags/' . $tag, 'sha' => $tagSha !== '' ? $tagSha : $sha]);

        return ['tag' => $tag, 'sha' => $sha];
    }

    // --- the reconciler: working-tree ↔ remote-tree, with no local .git -------------------------

    /**
     * Working-tree status without a `.git`: the on-disk files compared to the last-synced commit (the
     * `.tiger-git/head` MARKER — the merge base this transport keeps so it can tell MY uncommitted edits
     * from the remote simply moving on). `changed` is disk vs that base (A/M/D); `behind` is how many
     * commits the remote branch head is ahead of the base (GitHub's compare API); `ahead` is always 0 —
     * the API transport has no local-only commits (a commit goes straight to the remote).
     */
    public function status(Tiger_Git_WorkingTree $tree): array
    {
        $branch = $tree->currentBranch();
        $head   = $this->_headSha($tree, $branch);
        $base   = $this->_baseSha($tree);

        $baseTree = [];
        $behind   = 0;
        try {
            $baseTree = $this->_treeOf($tree, $base ?? $head);
            if ($base !== null && $base !== $head) {
                $cmp    = $this->_compare($tree, $base, $head);   // base...head → head ahead_by N
                $behind = (int) ($cmp['ahead_by'] ?? 0);
            }
        } catch (Tiger_Git_Exception $e) {
            // A stale/garbage-collected base must never break status — fall back to the head tree.
            $baseTree = $this->_treeOf($tree, $head);
        }

        $changed = $this->_workingChanges($tree, $baseTree);
        return ['branch' => $branch, 'ahead' => 0, 'behind' => $behind, 'changed' => $changed, 'clean' => $changed === []];
    }

    /**
     * A size-capped diff summary of the working tree against the base (the committed baseline). Added
     * lines / removed lines per changed file via a line LCS (bounded); binary files are flagged, never
     * counted. `opts.paths` restricts the set; `opts.max_bytes`, when > 0, caps the number of files
     * reported (mirroring the shell transport's cap) and sets `truncated`.
     */
    public function diff(Tiger_Git_WorkingTree $tree, array $opts = []): array
    {
        $base     = $this->_baseSha($tree) ?? $this->_headSha($tree, $tree->currentBranch());
        $baseTree = $this->_treeOf($tree, $base);
        $only     = (!empty($opts['paths']) && is_array($opts['paths'])) ? array_flip(array_map('strval', $opts['paths'])) : null;
        $cap      = (int) ($opts['max_bytes'] ?? 0) > 0 ? (int) $opts['max_bytes'] : self::MAX_FILES;

        $files = [];
        $truncated = false;
        foreach ($this->_workingChanges($tree, $baseTree) as $c) {
            if ($only !== null && !isset($only[$c['path']])) { continue; }
            if (count($files) >= $cap) { $truncated = true; break; }
            $files[] = $this->_fileDiff($tree, $baseTree, $c);
        }
        return ['files' => $files, 'truncated' => $truncated];
    }

    /**
     * Switch the working tree to an existing branch by MATERIALISING that branch's tree to disk — the
     * no-`.git` equivalent of a checkout. Refuses a dirty tree (uncommitted edits would be lost) and a
     * missing branch (INVALID_REF). The caller re-points the connection's current branch (the tree is
     * immutable); this does the on-disk checkout and resets the base marker to the new head.
     */
    public function switchBranch(Tiger_Git_WorkingTree $tree, string $name): void
    {
        $target = $this->_headSha($tree, $name);   // 404 → INVALID_REF
        $base   = $this->_baseSha($tree) ?? $this->_headSha($tree, $tree->currentBranch());
        $baseTree = $this->_treeOf($tree, $base);
        if ($this->_workingChanges($tree, $baseTree) !== []) {
            throw new Tiger_Git_Exception(Tiger_Git_Exception::DIRTY_TREE, 'refusing to switch with uncommitted changes');
        }
        $targetTree = $this->_treeOf($tree, $target);
        $apply = [];
        foreach ($targetTree as $path => $meta) { $apply[$path] = $meta['sha']; }
        foreach ($baseTree as $path => $meta) { if (!isset($targetTree[$path])) { $apply[$path] = null; } }
        $this->_materialize($tree, $apply);
        $this->_setBaseSha($tree, $target);
    }

    /**
     * Fast-forward the working tree to the remote branch head. No local commit graph, so "FF" means:
     * bring disk up to the files that changed between the base and the head, PRESERVING non-conflicting
     * local edits. A file edited BOTH locally and remotely is a MERGE_CONFLICT — the tree is left
     * untouched and the conflicting paths are reported (detail). Already-current is a clean no-op.
     */
    public function pull(Tiger_Git_WorkingTree $tree, string $branch): array
    {
        $head = $this->_headSha($tree, $branch);
        $base = $this->_baseSha($tree);
        if ($base === null || $base === $head) {
            if ($base === null) { $this->_setBaseSha($tree, $head); }   // first sync: adopt the head as the base
            return ['ref' => $head, 'updated' => false, 'conflicts' => []];
        }

        $cmp = $this->_compare($tree, $base, $head);
        $remote = [];
        foreach (($cmp['files'] ?? []) as $f) { $remote[(string) $f['filename']] = (string) ($f['status'] ?? 'modified'); }
        if ($remote === []) { $this->_setBaseSha($tree, $head); return ['ref' => $head, 'updated' => false, 'conflicts' => []]; }

        $local = [];
        foreach ($this->_workingChanges($tree, $this->_treeOf($tree, $base)) as $c) { $local[$c['path']] = true; }
        $conflicts = array_values(array_intersect(array_keys($remote), array_keys($local)));
        if ($conflicts !== []) {
            sort($conflicts);
            throw new Tiger_Git_Exception(Tiger_Git_Exception::MERGE_CONFLICT, 'local changes conflict with incoming changes', ['files' => $conflicts]);
        }

        $headTree = $this->_treeOf($tree, $head);
        $apply = [];
        foreach ($remote as $path => $st) {
            $apply[$path] = ($st === 'removed') ? null : ($headTree[$path]['sha'] ?? null);
        }
        $this->_materialize($tree, $apply);
        $this->_setBaseSha($tree, $head);
        return ['ref' => $head, 'updated' => true, 'conflicts' => []];
    }

    // --- internals: reconciler helpers -----------------------------------------------------------

    /** Directories never walked as part of the working tree (noise + our own marker). */
    private const SKIP = ['.git', '.tiger-git', 'node_modules', 'vendor', '.DS_Store'];
    /** Hard caps so a pathological tree can't hang the host. */
    private const MAX_FILES = 2000;
    private const DIFF_CELL_CAP = 4000000;

    /** Resolve a branch head to its commit sha (404 → INVALID_REF). */
    private function _headSha(Tiger_Git_WorkingTree $tree, string $branch): string
    {
        $this->assertTag($branch);
        $ref = $this->api($tree, 'GET', $this->repoPath($tree) . '/git/ref/heads/' . $branch);
        $sha = (string) ($ref['object']['sha'] ?? '');
        if ($sha === '') { throw new Tiger_Git_Exception(Tiger_Git_Exception::INVALID_REF, 'could not resolve the branch head'); }
        return $sha;
    }

    /** A commit's full (recursive) blob tree: path => {sha, mode}. */
    private function _treeOf(Tiger_Git_WorkingTree $tree, string $commitSha): array
    {
        $commit  = $this->api($tree, 'GET', $this->repoPath($tree) . '/git/commits/' . $commitSha);
        $treeSha = (string) ($commit['tree']['sha'] ?? '');
        if ($treeSha === '') { return []; }
        $data = $this->api($tree, 'GET', $this->repoPath($tree) . '/git/trees/' . $treeSha . '?recursive=1');
        $map  = [];
        foreach (($data['tree'] ?? []) as $e) {
            if (($e['type'] ?? '') === 'blob') {
                $map[(string) $e['path']] = ['sha' => (string) ($e['sha'] ?? ''), 'mode' => (string) ($e['mode'] ?? '100644')];
            }
        }
        return $map;
    }

    /** GitHub's two-dot-three compare: base...head → {ahead_by, behind_by, files:[{filename,status}]}. */
    private function _compare(Tiger_Git_WorkingTree $tree, string $base, string $head): array
    {
        return $this->api($tree, 'GET', $this->repoPath($tree) . '/compare/' . $base . '...' . $head);
    }

    /** One blob's decoded content. */
    private function _fetchBlob(Tiger_Git_WorkingTree $tree, string $sha): string
    {
        $b   = $this->api($tree, 'GET', $this->repoPath($tree) . '/git/blobs/' . $sha);
        $enc = (string) ($b['encoding'] ?? 'base64');
        $content = (string) ($b['content'] ?? '');
        return $enc === 'base64' ? (string) base64_decode($content) : $content;
    }

    /** Write (or, with a null sha, delete) each path — every target resolved under the tree root. */
    private function _materialize(Tiger_Git_WorkingTree $tree, array $paths): void
    {
        foreach ($paths as $rel => $sha) {
            $abs = $tree->resolve((string) $rel);   // PATH_ESCAPE guard
            if ($sha === null) { if (is_file($abs)) { @unlink($abs); } continue; }
            $dir = dirname($abs);
            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
            file_put_contents($abs, $this->_fetchBlob($tree, (string) $sha));
        }
    }

    /** On-disk files (relative => absolute), skipping the SKIP dirs, capped at MAX_FILES. */
    private function _walkDisk(Tiger_Git_WorkingTree $tree): array
    {
        $out = [];
        $this->_walk($tree->dir(), '', $out);
        return $out;
    }

    private function _walk(string $dir, string $prefix, array &$out): void
    {
        foreach ((scandir($dir) ?: []) as $e) {
            if ($e === '.' || $e === '..' || in_array($e, self::SKIP, true)) { continue; }
            $abs = $dir . '/' . $e;
            $rel = $prefix === '' ? $e : $prefix . '/' . $e;
            if (is_dir($abs)) {
                if (count($out) < self::MAX_FILES) { $this->_walk($abs, $rel, $out); }
            } elseif (is_file($abs) && count($out) < self::MAX_FILES) {
                $out[$rel] = $abs;
            }
        }
    }

    /** The git blob sha of some content (sha1 of "blob <len>\0<content>") — matches GitHub's object id. */
    private function _blobSha(string $content): string
    {
        return sha1('blob ' . strlen($content) . "\0" . $content);
    }

    /**
     * The working-tree changes vs a base tree: A (on disk, not tracked), M (tracked, content differs),
     * D (tracked, gone from disk). Sorted by path.
     *
     * @return array<int,array{path:string,state:string}>
     */
    private function _workingChanges(Tiger_Git_WorkingTree $tree, array $baseTree): array
    {
        $disk    = $this->_walkDisk($tree);
        $changed = [];
        foreach ($baseTree as $path => $meta) {
            if (!isset($disk[$path])) { $changed[] = ['path' => $path, 'state' => 'D']; continue; }
            if ($this->_blobSha((string) file_get_contents($disk[$path])) !== $meta['sha']) {
                $changed[] = ['path' => $path, 'state' => 'M'];
            }
            unset($disk[$path]);
        }
        foreach ($disk as $path => $abs) { $changed[] = ['path' => $path, 'state' => 'A']; }
        usort($changed, static fn ($a, $b) => strcmp($a['path'], $b['path']));
        return $changed;
    }

    /** Added/removed/binary for one changed file (fetches the base blob for a modify/delete). */
    private function _fileDiff(Tiger_Git_WorkingTree $tree, array $baseTree, array $c): array
    {
        $path = $c['path'];
        if ($c['state'] === 'D') {
            $old = isset($baseTree[$path]) ? $this->_fetchBlob($tree, $baseTree[$path]['sha']) : '';
            return $this->_isBinary($old)
                ? ['path' => $path, 'added' => 0, 'removed' => 0, 'binary' => true]
                : ['path' => $path, 'added' => 0, 'removed' => $this->_lineCount($old), 'binary' => false];
        }
        $new = (string) file_get_contents($tree->resolve($path));
        if ($c['state'] === 'A') {
            return $this->_isBinary($new)
                ? ['path' => $path, 'added' => 0, 'removed' => 0, 'binary' => true]
                : ['path' => $path, 'added' => $this->_lineCount($new), 'removed' => 0, 'binary' => false];
        }
        $old = isset($baseTree[$path]) ? $this->_fetchBlob($tree, $baseTree[$path]['sha']) : '';
        if ($this->_isBinary($old) || $this->_isBinary($new)) {
            return ['path' => $path, 'added' => 0, 'removed' => 0, 'binary' => true];
        }
        [$added, $removed] = $this->_lineDiff($old, $new);
        return ['path' => $path, 'added' => $added, 'removed' => $removed, 'binary' => false];
    }

    private function _isBinary(string $s): bool
    {
        return strpos($s, "\0") !== false;
    }

    /** Content → line array (a trailing newline does not count as an extra empty line). */
    private function _lines(string $s): array
    {
        if ($s === '') { return []; }
        $lines = explode("\n", $s);
        if (end($lines) === '') { array_pop($lines); }
        return $lines;
    }

    private function _lineCount(string $s): int
    {
        return count($this->_lines($s));
    }

    /**
     * Added/removed line counts via a bounded line-LCS (rolling two-row, O(n·m) time, O(m) memory).
     * Over DIFF_CELL_CAP cells it falls back to the net delta — a summary, never exact for huge files.
     *
     * @return array{0:int,1:int} [added, removed]
     */
    private function _lineDiff(string $old, string $new): array
    {
        $a = $this->_lines($old);
        $b = $this->_lines($new);
        $n = count($a);
        $m = count($b);
        if ($n === 0) { return [$m, 0]; }
        if ($m === 0) { return [0, $n]; }
        if ($n * $m > self::DIFF_CELL_CAP) { return [max(0, $m - $n), max(0, $n - $m)]; }

        $prev = array_fill(0, $m + 1, 0);
        for ($i = 1; $i <= $n; $i++) {
            $cur = array_fill(0, $m + 1, 0);
            $ai  = $a[$i - 1];
            for ($j = 1; $j <= $m; $j++) {
                $cur[$j] = ($ai === $b[$j - 1]) ? $prev[$j - 1] + 1 : max($prev[$j], $cur[$j - 1]);
            }
            $prev = $cur;
        }
        $lcs = $prev[$m];
        return [$m - $lcs, $n - $lcs];
    }

    /** The merge-base marker file (`.tiger-git/head`) — the last commit this working tree was synced to. */
    private function _baseSha(Tiger_Git_WorkingTree $tree): ?string
    {
        $f = $tree->dir() . '/.tiger-git/head';
        if (!is_file($f)) { return null; }
        $s = trim((string) file_get_contents($f));
        return preg_match('/^[0-9a-f]{40}$/', $s) ? $s : null;
    }

    /** Record the synced commit sha in the marker (so status/pull can tell local edits from a moved remote). */
    private function _setBaseSha(Tiger_Git_WorkingTree $tree, string $sha): void
    {
        if (!preg_match('/^[0-9a-f]{40}$/', $sha)) { return; }
        $dir = $tree->dir() . '/.tiger-git';
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) { return; }
        @file_put_contents($dir . '/head', $sha . "\n");
    }

    // --- internals: HTTP + helpers ---------------------------------------------------------------

    /** A tag name GitHub will accept in a URL path and that carries no traversal/option trickery. */
    private function assertTag(string $tag): void
    {
        if ($tag === '' || $tag[0] === '-' || !preg_match('~^[A-Za-z0-9._/-]+$~', $tag) || str_contains($tag, '..')) {
            throw new Tiger_Git_Exception(Tiger_Git_Exception::INVALID_REF, 'invalid tag name');
        }
    }

    /** Case-insensitive lookup of a response header from a send() result. */
    private function header(array $res, string $name): string
    {
        $name = strtolower($name);
        foreach (($res['headers'] ?? []) as $k => $v) {
            if (strtolower((string) $k) === $name) {
                return is_array($v) ? (string) ($v[0] ?? '') : (string) $v;
            }
        }
        return '';
    }

    /** The base authenticated GitHub headers (no Content-Type — api() adds it when there's a body). */
    private function authHeaders(Tiger_Git_WorkingTree $tree): array
    {
        return [
            'Authorization'        => 'Bearer ' . $this->token($tree),
            'Accept'               => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
            'User-Agent'           => 'Tiger-Git',
        ];
    }

    private function repoPath(Tiger_Git_WorkingTree $tree): string
    {
        return '/repos/' . $tree->owner() . '/' . $tree->repo();
    }

    /** The token for a tree's credential, resolved once per request. Throws when a write has none. */
    private function token(Tiger_Git_WorkingTree $tree): string
    {
        $refKey = $tree->credentialRef();
        if (!array_key_exists($refKey, $this->tokenCache)) {
            $this->tokenCache[$refKey] = $this->credentials->resolve($refKey);
        }
        $tok = $this->tokenCache[$refKey];
        if ($tok === null || $tok === '') {
            throw new Tiger_Git_Exception(Tiger_Git_Exception::CREDENTIAL_MISSING, 'no usable credential for this connection');
        }
        return $tok;
    }

    /**
     * One authenticated GitHub API call. Returns the decoded JSON body (array), or throws a structured
     * Tiger_Git_Exception mapping the status to a stable code. Pinned to api.github.com.
     *
     * @param  array|null $json a request body to JSON-encode, or null for none
     * @return array
     */
    private function api(Tiger_Git_WorkingTree $tree, string $method, string $path, ?array $json = null): array
    {
        if (!Tiger_Git::isAllowedHost(self::HOST)) {
            throw new Tiger_Git_Exception(Tiger_Git_Exception::HOST_NOT_ALLOWED, 'api.github.com is not allowed');
        }
        $headers = $this->authHeaders($tree);
        $body = $json !== null ? json_encode($json) : null;
        if ($body !== null) { $headers['Content-Type'] = 'application/json'; }

        $res    = $this->send($method, self::API . $path, $headers, $body);
        $status = (int) ($res['status'] ?? 0);
        $decoded = json_decode((string) ($res['body'] ?? ''), true);
        $decoded = is_array($decoded) ? $decoded : [];

        if ($status >= 200 && $status < 300) {
            return $decoded;
        }
        throw $this->mapError($status, $decoded, (string) ($res['body'] ?? ''));
    }

    /** Translate a non-2xx GitHub response to a stable, secret-free error code. */
    private function mapError(int $status, array $decoded, string $raw): Tiger_Git_Exception
    {
        $msg = (string) ($decoded['message'] ?? '');
        $detail = ['status' => $status];

        if ($status === 401) {
            return new Tiger_Git_Exception(Tiger_Git_Exception::CREDENTIAL_INVALID, 'the credential was rejected by GitHub', $detail);
        }
        if ($status === 403) {
            if (stripos($msg, 'rate limit') !== false || stripos($raw, 'rate limit') !== false) {
                return new Tiger_Git_Exception(Tiger_Git_Exception::RATE_LIMITED, 'GitHub rate limit reached', $detail);
            }
            return new Tiger_Git_Exception(Tiger_Git_Exception::CREDENTIAL_INVALID, 'the credential lacks the required scope', $detail);
        }
        if ($status === 404) {
            return new Tiger_Git_Exception(Tiger_Git_Exception::INVALID_REF, 'repository or ref not found', $detail);
        }
        if ($status === 409 || $status === 422) {
            if (stripos($msg, 'fast forward') !== false || stripos($msg, 'fast-forward') !== false) {
                return new Tiger_Git_Exception(Tiger_Git_Exception::NON_FAST_FORWARD, 'the branch moved — update is not a fast-forward', $detail);
            }
            return new Tiger_Git_Exception(Tiger_Git_Exception::API_ERROR, 'GitHub rejected the request', $detail);
        }
        return new Tiger_Git_Exception(Tiger_Git_Exception::API_ERROR, 'GitHub API error', $detail);
    }

    /**
     * The raw HTTP call — the seam. Tests inject canned responses; production uses cURL. Redirects are
     * never auto-followed (the caller inspects the Location so it can confirm the host allowlist before
     * fetching). Returns `{status:int, body:string, headers:array<string,string>}`.
     *
     * @param  array<string,string> $headers
     * @return array{status:int,body:string,headers:array<string,string>}
     */
    private function send(string $method, string $url, array $headers, ?string $body): array
    {
        if ($this->http !== null) {
            $res = ($this->http)($method, $url, $headers, $body);
            return [
                'status'  => (int) ($res['status'] ?? 0),
                'body'    => (string) ($res['body'] ?? ''),
                'headers' => is_array($res['headers'] ?? null) ? $res['headers'] : [],
            ];
        }
        if (!function_exists('curl_init')) {
            throw new Tiger_Git_Exception(Tiger_Git_Exception::TRANSPORT_UNAVAILABLE, 'cURL is not available for the API transport');
        }
        $ch = curl_init($url);
        $hdr = [];
        foreach ($headers as $k => $v) { $hdr[] = $k . ': ' . $v; }
        $respHeaders = [];
        $opts = [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $hdr,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => false,   // never follow a redirect off the allowlisted host
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$respHeaders): int {
                $p = strpos($line, ':');
                if ($p !== false) { $respHeaders[strtolower(trim(substr($line, 0, $p)))] = trim(substr($line, $p + 1)); }
                return strlen($line);
            },
        ];
        if ($body !== null) { $opts[CURLOPT_POSTFIELDS] = $body; }
        curl_setopt_array($ch, $opts);
        $out  = curl_exec($ch);
        $errno = curl_errno($ch);
        $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($errno !== 0) {
            throw new Tiger_Git_Exception(Tiger_Git_Exception::API_ERROR, 'the GitHub request failed', ['curl' => $errno]);
        }
        return ['status' => $code, 'body' => (string) $out, 'headers' => $respHeaders];
    }
}
