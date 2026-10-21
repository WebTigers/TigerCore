<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Git_Transport_Shell — git operations driven by the `git` binary (TIGER-364).
 *
 * The transport for a host that HAS git and can run a subprocess (a dev box, the owner's own server) —
 * the counterpart to Tiger_Git_Transport_Api, behind the same interface so callers never know which is
 * active. It operates on a real `.git` working tree at `$tree->dir()`.
 *
 * Safety is structural: git is invoked through `proc_open` with an ARGUMENT ARRAY (never a shell
 * string), so nothing a caller passes can be interpreted as a shell metacharacter; only a fixed set of
 * subcommands is ever run; every file path is resolved under the module root first (refusing an escape);
 * pushes are fast-forward only (no `--force`, ever); and a credential is injected through an inline
 * `credential.helper` that reads the token from the process ENVIRONMENT (`GIT_TOKEN`), so the secret is
 * never in argv (visible to `ps`) nor written to `.git/config`.
 *
 * GitHub *releases* and release *archives* are a provider-API concept, not a git one — the GitHub
 * adapter routes those to Tiger_Git_Transport_Api regardless of the working transport — so
 * `listReleases()`/`fetchArchive()` here report NOT_IMPLEMENTED by design.
 *
 * @api
 * @since 1.38.0
 */
class Tiger_Git_Transport_Shell implements Tiger_Git_Transport_Interface
{
    /** @var Tiger_Git_Credential resolves a connection's credential ref to a token (never returned out) */
    private Tiger_Git_Credential $credentials;

    /** @var null|callable(array,string,array):array the command-runner seam; null = real proc_open */
    private $runner;

    public function __construct(Tiger_Git_Credential $credentials, ?callable $runner = null)
    {
        $this->credentials = $credentials;
        $this->runner      = $runner;
    }

    /** Swap the command runner (tests stub git output). Null restores real proc_open. */
    public function setRunner(?callable $runner): void
    {
        $this->runner = $runner;
    }

    public function name(): string
    {
        return Tiger_Git::TRANSPORT_SHELL;
    }

    // --- status / diff ---------------------------------------------------------------------------

    public function status(Tiger_Git_WorkingTree $tree): array
    {
        $branch = trim($this->ok($tree, ['rev-parse', '--abbrev-ref', 'HEAD'])['out']);

        $porcelain = $this->ok($tree, ['status', '--porcelain'])['out'];
        $changed = [];
        foreach (preg_split('/\R/', $porcelain) as $line) {
            if ($line === '') { continue; }
            $state = trim(substr($line, 0, 2));
            $path  = substr($line, 3);
            if (str_contains($path, ' -> ')) { $path = substr($path, strpos($path, ' -> ') + 4); } // rename
            $changed[] = ['path' => $path, 'state' => $state];
        }

        [$ahead, $behind] = $this->aheadBehind($tree);

        return [
            'branch'  => $branch,
            'ahead'   => $ahead,
            'behind'  => $behind,
            'changed' => $changed,
            'clean'   => $changed === [],
        ];
    }

    public function diff(Tiger_Git_WorkingTree $tree, array $opts = []): array
    {
        $args = ['diff', '--numstat'];
        if (!empty($opts['paths']) && is_array($opts['paths'])) {
            $args[] = '--';
            foreach ($opts['paths'] as $p) { $tree->resolve((string) $p); $args[] = (string) $p; }
        }
        $out  = $this->ok($tree, $args)['out'];
        $cap  = (int) ($opts['max_bytes'] ?? 0);
        $files = [];
        $truncated = false;
        foreach (preg_split('/\R/', $out) as $line) {
            if ($line === '') { continue; }
            $parts = preg_split('/\t/', $line);
            if (count($parts) < 3) { continue; }
            [$add, $rem, $path] = $parts;
            $binary = ($add === '-' && $rem === '-');
            $files[] = ['path' => $path, 'added' => $binary ? 0 : (int) $add, 'removed' => $binary ? 0 : (int) $rem, 'binary' => $binary];
            if ($cap > 0 && count($files) >= $cap) { $truncated = true; break; }
        }
        return ['files' => $files, 'truncated' => $truncated];
    }

    // --- commit ----------------------------------------------------------------------------------

    public function commit(Tiger_Git_WorkingTree $tree, string $message, array $paths = []): string
    {
        if (trim($message) === '') {
            throw new Tiger_Git_Exception(Tiger_Git_Exception::INVALID_REF, 'a commit message is required');
        }

        if ($paths === []) {
            $this->ok($tree, ['add', '-A']);
        } else {
            $add = ['add', '--'];
            foreach ($paths as $p) { $tree->resolve((string) $p); $add[] = (string) $p; }
            $this->ok($tree, $add);
        }

        [$name, $email] = $this->authorIdentity();
        $res = $this->run($tree, ['-c', 'user.name=' . $name, '-c', 'user.email=' . $email, 'commit', '-m', $message]);
        if ($res['code'] !== 0) {
            if (stripos($res['out'] . $res['err'], 'nothing to commit') !== false) {
                throw new Tiger_Git_Exception(Tiger_Git_Exception::NOTHING_TO_COMMIT, 'there is nothing to commit');
            }
            throw $this->shellError($res, 'commit failed');
        }
        return trim($this->ok($tree, ['rev-parse', 'HEAD'])['out']);
    }

    // --- branches --------------------------------------------------------------------------------

    public function branches(Tiger_Git_WorkingTree $tree): array
    {
        $out = $this->ok($tree, ['branch', '--format=%(refname:short)'])['out'];
        return array_values(array_filter(array_map('trim', preg_split('/\R/', $out)), static fn ($b) => $b !== ''));
    }

    public function createBranch(Tiger_Git_WorkingTree $tree, string $name): void
    {
        $this->assertRef($name);
        if ($name === $tree->defaultBranch()) {
            throw new Tiger_Git_Exception(Tiger_Git_Exception::PROTECTED_BRANCH, 'refusing to recreate the default branch');
        }
        $this->ok($tree, ['branch', $name]);   // off the current HEAD, no checkout
    }

    public function switchBranch(Tiger_Git_WorkingTree $tree, string $name): void
    {
        $this->assertRef($name);
        if (!$this->status($tree)['clean']) {
            throw new Tiger_Git_Exception(Tiger_Git_Exception::DIRTY_TREE, 'refusing to switch with uncommitted changes');
        }
        $res = $this->run($tree, ['switch', $name]);
        if ($res['code'] !== 0) { throw $this->shellError($res, 'branch switch failed'); }
    }

    // --- push / pull (network) -------------------------------------------------------------------

    public function push(Tiger_Git_WorkingTree $tree, string $branch): array
    {
        $this->assertRef($branch);
        $res = $this->run($tree, ['push', 'origin', $branch], $this->authEnv($tree));   // NO --force, ever
        if ($res['code'] !== 0) { throw $this->pushPullError($res); }
        return ['ref' => trim($this->ok($tree, ['rev-parse', $branch])['out']), 'pushed' => true];
    }

    public function pull(Tiger_Git_WorkingTree $tree, string $branch): array
    {
        $this->assertRef($branch);
        $res = $this->run($tree, ['pull', '--ff-only', 'origin', $branch], $this->authEnv($tree));
        if ($res['code'] !== 0) { throw $this->pushPullError($res); }
        return ['ref' => trim($this->ok($tree, ['rev-parse', 'HEAD'])['out']), 'updated' => true, 'conflicts' => []];
    }

    // --- tag -------------------------------------------------------------------------------------

    public function tag(Tiger_Git_WorkingTree $tree, string $tag, ?string $ref = null): array
    {
        $this->assertRef($tag);
        $args = ['tag', '-a', $tag, '-m', $tag];
        if ($ref !== null && $ref !== '') { $this->assertRef($ref); $args[] = $ref; }
        $this->ok($tree, $args);

        // A release tag is only useful on the remote — push it (auth, never forced).
        $push = $this->run($tree, ['push', 'origin', 'refs/tags/' . $tag], $this->authEnv($tree));
        if ($push['code'] !== 0) { throw $this->pushPullError($push); }

        return ['tag' => $tag, 'sha' => trim($this->ok($tree, ['rev-parse', $tag . '^{commit}'])['out'])];
    }

    // --- provider-API concerns (not git) ---------------------------------------------------------

    public function listReleases(Tiger_Git_WorkingTree $tree): array
    {
        throw new Tiger_Git_Exception(Tiger_Git_Exception::NOT_IMPLEMENTED, 'GitHub releases are a provider-API concern — use the GitHub adapter');
    }

    public function fetchArchive(Tiger_Git_WorkingTree $tree, string $tag, string $destFile): string
    {
        throw new Tiger_Git_Exception(Tiger_Git_Exception::NOT_IMPLEMENTED, 'a release archive is a provider-API concern — use the GitHub adapter');
    }

    // --- internals -------------------------------------------------------------------------------

    /** The ahead/behind counts vs the upstream, or [0,0] when there is no upstream. */
    private function aheadBehind(Tiger_Git_WorkingTree $tree): array
    {
        $up = $this->run($tree, ['rev-parse', '--abbrev-ref', '--symbolic-full-name', '@{upstream}']);
        if ($up['code'] !== 0) { return [0, 0]; }
        $counts = $this->run($tree, ['rev-list', '--left-right', '--count', '@{upstream}...HEAD']);
        if ($counts['code'] !== 0) { return [0, 0]; }
        $parts = preg_split('/\s+/', trim($counts['out']));
        return [(int) ($parts[1] ?? 0), (int) ($parts[0] ?? 0)];   // [ahead (right), behind (left)]
    }

    /** The commit author identity (config-overridable), with safe defaults so commit never fails unconfigured. */
    private function authorIdentity(): array
    {
        $name = 'Tiger';
        $email = 'tiger@localhost';
        try {
            if (Zend_Registry::isRegistered('Zend_Config')) {
                $cfg = Zend_Registry::get('Zend_Config');
                $g = $cfg->tiger->git->author ?? null;
                if ($g) {
                    $name  = (string) ($g->name ?: $name);
                    $email = (string) ($g->email ?: $email);
                }
            }
        } catch (\Throwable $e) { /* defaults */ }
        return [$name, $email];
    }

    /**
     * The environment for a network op: the resolved token in `GIT_TOKEN` + an inline credential helper
     * that reads it (so the secret is never in argv or `.git/config`). Empty when there's no token (a
     * public / file / ssh-agent remote), which is fine — git just proceeds unauthenticated.
     *
     * @return array<string,string>
     */
    private function authEnv(Tiger_Git_WorkingTree $tree): array
    {
        $token = $this->credentials->resolve($tree->credentialRef());
        if ($token === null || $token === '') { return []; }
        return ['GIT_TOKEN' => $token, 'GIT_TERMINAL_PROMPT' => '0'];
    }

    /** Extra `-c` args that wire the inline credential helper (used only when a token is present). */
    private function authArgs(Tiger_Git_WorkingTree $tree): array
    {
        $token = $this->credentials->resolve($tree->credentialRef());
        if ($token === null || $token === '') { return []; }
        // The helper references $GIT_TOKEN from the env — no secret in argv.
        return ['-c', 'credential.helper=', '-c', 'credential.helper=!f() { echo username=x-access-token; echo "password=$GIT_TOKEN"; }; f'];
    }

    /** A ref/branch/tag name git will accept and that carries no shell/option trickery. */
    private function assertRef(string $name): void
    {
        if ($name === '' || $name[0] === '-' || !preg_match('~^[A-Za-z0-9._/-]+$~', $name) || str_contains($name, '..')) {
            throw new Tiger_Git_Exception(Tiger_Git_Exception::INVALID_REF, 'invalid ref name');
        }
    }

    /** Run git and throw SHELL_ERROR on any non-zero exit (for commands expected to succeed). */
    private function ok(Tiger_Git_WorkingTree $tree, array $args, array $env = []): array
    {
        $res = $this->run($tree, $args, $env);
        if ($res['code'] !== 0) { throw $this->shellError($res, 'git ' . ($args[0] ?? '') . ' failed'); }
        return $res;
    }

    private function shellError(array $res, string $what): Tiger_Git_Exception
    {
        return new Tiger_Git_Exception(Tiger_Git_Exception::SHELL_ERROR, $what, ['code' => $res['code']]);
    }

    /** Map a push/pull failure to NON_FAST_FORWARD / CREDENTIAL_INVALID / SHELL_ERROR. */
    private function pushPullError(array $res): Tiger_Git_Exception
    {
        $blob = strtolower($res['out'] . ' ' . $res['err']);
        if (str_contains($blob, 'non-fast-forward') || str_contains($blob, 'fetch first') || str_contains($blob, 'not possible to fast-forward')) {
            return new Tiger_Git_Exception(Tiger_Git_Exception::NON_FAST_FORWARD, 'the branch moved — update is not a fast-forward');
        }
        if (str_contains($blob, 'authentication failed') || str_contains($blob, 'could not read username') || str_contains($blob, 'terminal prompts disabled') || str_contains($blob, '403')) {
            return new Tiger_Git_Exception(Tiger_Git_Exception::CREDENTIAL_INVALID, 'the credential was rejected by the remote');
        }
        return new Tiger_Git_Exception(Tiger_Git_Exception::SHELL_ERROR, 'the git remote operation failed', ['code' => $res['code']]);
    }

    /**
     * Run `git <args>` in the module dir. A network op's $env carries the token + its inline helper args
     * are prepended. Returns `{code:int, out:string, err:string}`. The command is an ARG ARRAY — no shell.
     *
     * @param  array<int,string>    $args
     * @param  array<string,string> $env
     * @return array{code:int,out:string,err:string}
     */
    private function run(Tiger_Git_WorkingTree $tree, array $args, array $env = []): array
    {
        // A token in the env means this is an authenticated op — wire the helper args in front.
        if (isset($env['GIT_TOKEN'])) { $args = array_merge($this->authArgs($tree), $args); }

        if ($this->runner !== null) {
            $res = ($this->runner)($args, $tree->dir(), $env);
            return ['code' => (int) ($res['code'] ?? 0), 'out' => (string) ($res['out'] ?? ''), 'err' => (string) ($res['err'] ?? '')];
        }
        if (!Tiger_Git_Environment::execUsable()) {
            throw new Tiger_Git_Exception(Tiger_Git_Exception::TRANSPORT_UNAVAILABLE, 'proc_open is unavailable for the shell transport');
        }
        $desc = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $procEnv = $env === [] ? null : array_merge(getenv() ?: [], $env);   // null = inherit; else merge over it
        $proc = proc_open(array_merge(['git'], $args), $desc, $pipes, $tree->dir(), $procEnv);
        if (!is_resource($proc)) {
            throw new Tiger_Git_Exception(Tiger_Git_Exception::SHELL_ERROR, 'could not start git');
        }
        $out = stream_get_contents($pipes[1]) ?: '';
        $err = stream_get_contents($pipes[2]) ?: '';
        foreach ($pipes as $p) { if (is_resource($p)) { fclose($p); } }
        $code = proc_close($proc);
        return ['code' => (int) $code, 'out' => (string) $out, 'err' => (string) $err];
    }
}
