<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Git_Transport_Api — git operations through the GitHub API, with NO `.git` directory (TIGER-364).
 *
 * The transport that makes shared hosting work: a host with `exec` disabled and no git binary can still
 * commit, branch, and (Phase 2 cont.) tag/release/update, because every operation is an authenticated
 * GitHub REST call rather than a subprocess. The module's files live on disk as an ordinary working
 * tree; this transport writes the CHANGED files straight to the remote branch as a new commit.
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

    // --- deferred to the rest of TIGER-364 (status/diff/switch/pull/tag/releases/archive) --------

    public function status(Tiger_Git_WorkingTree $tree): array
    {
        throw $this->notImplemented('status');
    }

    public function diff(Tiger_Git_WorkingTree $tree, array $opts = []): array
    {
        throw $this->notImplemented('diff');
    }

    public function switchBranch(Tiger_Git_WorkingTree $tree, string $name): void
    {
        throw $this->notImplemented('switchBranch');
    }

    public function pull(Tiger_Git_WorkingTree $tree, string $branch): array
    {
        throw $this->notImplemented('pull');
    }

    public function tag(Tiger_Git_WorkingTree $tree, string $tag, ?string $ref = null): array
    {
        throw $this->notImplemented('tag');
    }

    public function listReleases(Tiger_Git_WorkingTree $tree): array
    {
        throw $this->notImplemented('listReleases');
    }

    public function fetchArchive(Tiger_Git_WorkingTree $tree, string $tag, string $destFile): string
    {
        throw $this->notImplemented('fetchArchive');
    }

    // --- internals -------------------------------------------------------------------------------

    private function notImplemented(string $op): Tiger_Git_Exception
    {
        return new Tiger_Git_Exception(Tiger_Git_Exception::NOT_IMPLEMENTED, "ApiTransport::$op lands in the rest of TIGER-364");
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
        $headers = [
            'Authorization'        => 'Bearer ' . $this->token($tree),
            'Accept'               => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
            'User-Agent'           => 'Tiger-Git',
        ];
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
     * The raw HTTP call — the seam. Tests inject canned responses; production uses cURL. Returns
     * `{status:int, body:string}`.
     *
     * @param  array<string,string> $headers
     */
    private function send(string $method, string $url, array $headers, ?string $body): array
    {
        if ($this->http !== null) {
            $res = ($this->http)($method, $url, $headers, $body);
            return ['status' => (int) ($res['status'] ?? 0), 'body' => (string) ($res['body'] ?? '')];
        }
        if (!function_exists('curl_init')) {
            throw new Tiger_Git_Exception(Tiger_Git_Exception::TRANSPORT_UNAVAILABLE, 'cURL is not available for the API transport');
        }
        $ch = curl_init($url);
        $hdr = [];
        foreach ($headers as $k => $v) { $hdr[] = $k . ': ' . $v; }
        $opts = [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $hdr,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_FOLLOWLOCATION => false,   // never follow a redirect off the allowlisted host
        ];
        if ($body !== null) { $opts[CURLOPT_POSTFIELDS] = $body; }
        curl_setopt_array($ch, $opts);
        $out  = curl_exec($ch);
        $errno = curl_errno($ch);
        $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($errno !== 0) {
            throw new Tiger_Git_Exception(Tiger_Git_Exception::API_ERROR, 'the GitHub request failed', ['curl' => $errno]);
        }
        return ['status' => $code, 'body' => (string) $out];
    }
}
