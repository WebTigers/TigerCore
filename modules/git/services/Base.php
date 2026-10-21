<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Git_Service_Base — shared plumbing for the Tiger_Git `/api` services (TIGER-334 / TIGER-367).
 *
 * The three services (Repo / Release / Update) all need the same few things: a signed-in-with-org
 * guard, the module's on-disk working directory, a Tiger_Git_WorkingTree built from a connection row,
 * the GitHub provider (adapter), a secret-free connection view, the audit-log writer, and one map from
 * a Tiger_Git_Exception code to an i18n key. They live here so each service stays about its own verbs.
 *
 * The provider is built behind a static factory seam (`setAdapterFactory`) so an integration test can
 * inject canned HTTP/git without a network; production uses the real Tiger_Git_Provider_GitHub.
 *
 * @api
 * @since 1.38.0
 */
abstract class Git_Service_Base extends Tiger_Service_Service
{
    /** @var null|callable():Tiger_Git_Provider_Interface test seam for the provider */
    protected static $adapterFactory = null;

    /** @var null|callable(string,array,array):array test seam for the tarball installer */
    protected static $installer = null;

    /** Swap the provider factory (tests inject a seam-wired adapter). Null restores the real one. */
    public static function setAdapterFactory(?callable $factory): void
    {
        self::$adapterFactory = $factory;
    }

    /**
     * Swap the tarball-install step (tests avoid touching the modules dir). The callable mirrors
     * Tiger_Module_Installer::installFromTarball($tar, $provenance, $opts). Null restores the real one.
     */
    public static function setInstaller(?callable $factory): void
    {
        self::$installer = $factory;
    }

    /** Install (update-in-place) a module from a local archive — the test seam if set, else the real installer. */
    protected function _installTarball(string $tarPath, array $provenance, array $opts): array
    {
        if (self::$installer !== null) {
            return (self::$installer)($tarPath, $provenance, $opts);
        }
        return Tiger_Module_Installer::installFromTarball($tarPath, $provenance, $opts);
    }

    /** Signed in with an org, or an error + false. (The ACL already gated the method at dispatch.) */
    protected function _signedIn(): bool
    {
        if ((string) $this->_user_id === '' || (string) $this->_org_id === '') {
            $this->_error('core.api.error.not_allowed');
            return false;
        }
        return true;
    }

    /** A module key sanitized to a safe slug (letters/digits/._-), or null. Never a path. */
    protected function _slug(string $moduleKey): ?string
    {
        $moduleKey = trim($moduleKey);
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,99}$/', $moduleKey) ? $moduleKey : null;
    }

    /**
     * The installed module's directory (app modules first, then first-party core), or null when the
     * module isn't present on disk — you can only version a module you actually have.
     */
    protected function _moduleDir(string $slug): ?string
    {
        $slug = $this->_slug($slug);
        if ($slug === null) { return null; }
        foreach ([
            defined('APPLICATION_PATH') ? APPLICATION_PATH . '/modules/' . $slug : null,
            defined('TIGER_CORE_PATH') ? TIGER_CORE_PATH . '/modules/' . $slug : null,
        ] as $dir) {
            if ($dir !== null && is_dir($dir)) { return $dir; }
        }
        return null;
    }

    /** The GitHub provider (adapter) — the test seam if set, else the real one. */
    protected function _adapter(): Tiger_Git_Provider_Interface
    {
        if (self::$adapterFactory !== null) {
            return (self::$adapterFactory)();
        }
        return new Tiger_Git_Provider_GitHub(new Tiger_Git_Credential());
    }

    /**
     * A Tiger_Git_WorkingTree for a connection row (default branch = current branch). Throws
     * NOT_CONNECTED when the module isn't on disk.
     */
    protected function _tree($conn): Tiger_Git_WorkingTree
    {
        $dir = $this->_moduleDir((string) $conn->module_key);
        if ($dir === null) {
            throw new Tiger_Git_Exception(Tiger_Git_Exception::NOT_CONNECTED, 'the module is not installed on this server');
        }
        return new Tiger_Git_WorkingTree(
            $dir,
            (string) $conn->repo_owner,
            (string) $conn->repo_name,
            (string) $conn->default_branch,
            (string) $conn->default_branch,
            (string) $conn->transport,
            (string) ($conn->credential_ref ?? ''),
            (bool) $conn->write_enabled
        );
    }

    /** The active connection for a module in the caller's org, or null. */
    protected function _connection(string $moduleKey)
    {
        return (new Tiger_Model_GitRepoConnection())->forModule($this->_org_id, $moduleKey);
    }

    /** The safe, secret-free view of a connection row for any surface (never the credential ref). */
    protected function _connView($conn): array
    {
        return [
            'module_key'       => (string) $conn->module_key,
            'provider'         => (string) $conn->provider,
            'repo_owner'       => (string) $conn->repo_owner,
            'repo_name'        => (string) $conn->repo_name,
            'slug'             => $conn->repo_owner . '/' . $conn->repo_name,
            'default_branch'   => (string) $conn->default_branch,
            'transport'        => (string) $conn->transport,
            'write_enabled'    => (bool) $conn->write_enabled,
            'status'           => (string) $conn->status,
            'last_verified_at' => $conn->last_verified_at !== null ? (string) $conn->last_verified_at : null,
        ];
    }

    /** The safe view of a Tiger_Git_Environment::detect() result (no secrets — pure capability facts). */
    protected function _envView(array $env): array
    {
        return [
            'transport'   => (string) ($env['transport'] ?? ''),
            'usable'      => (bool) ($env['usable'] ?? false),
            'git_binary'  => (bool) ($env['git_binary'] ?? false),
            'exec_usable' => (bool) ($env['exec_usable'] ?? false),
            'outbound'    => (bool) ($env['outbound'] ?? false),
            'git_version' => $env['git_version'] ?? null,
            'remediation' => $env['remediation'] ?? null,
        ];
    }

    /** The safe view of a module_install_pin row (installed/pinned versions + policy). */
    protected function _pinView($pin): array
    {
        return [
            'module_key'        => (string) $pin->module_key,
            'installed_version' => $pin->installed_version !== null ? (string) $pin->installed_version : null,
            'pinned_version'    => $pin->pinned_version !== null ? (string) $pin->pinned_version : null,
            'previous_version'  => $pin->previous_version !== null ? (string) $pin->previous_version : null,
            'channel'           => (string) $pin->channel,
            'status'            => (string) $pin->status,
        ];
    }

    /** Append one row to the audit trail (fail-soft — a log failure never fails the operation). */
    protected function _log(string $moduleKey, string $operation, string $result, array $extra = []): void
    {
        try {
            (new Tiger_Model_GitOperationLog())->record(array_merge([
                'org_id'     => (string) $this->_org_id,
                'module_key' => $moduleKey,
                'actor_type' => Tiger_Model_GitOperationLog::ACTOR_USER,
                'actor_id'   => $this->_user_id,
                'operation'  => $operation,
                'result'     => $result,
            ], $extra));
        } catch (\Throwable $e) {
            Tiger_Log::warn('git.audit.failed', ['operation' => $operation, 'error' => $e->getMessage()]);
        }
    }

    /** Map a Tiger_Git_Exception's stable code to a module i18n key (secret-free). */
    protected function _gitErrorKey(Tiger_Git_Exception $e): string
    {
        $map = [
            Tiger_Git_Exception::CREDENTIAL_INVALID    => 'git.error.credential_invalid',
            Tiger_Git_Exception::CREDENTIAL_MISSING    => 'git.error.credential_missing',
            Tiger_Git_Exception::NON_FAST_FORWARD      => 'git.error.non_fast_forward',
            Tiger_Git_Exception::RATE_LIMITED          => 'git.error.rate_limited',
            Tiger_Git_Exception::HOST_NOT_ALLOWED      => 'git.error.host_not_allowed',
            Tiger_Git_Exception::INVALID_REF           => 'git.error.invalid_ref',
            Tiger_Git_Exception::NOT_CONNECTED         => 'git.error.not_connected',
            Tiger_Git_Exception::PROTECTED_BRANCH      => 'git.error.protected_branch',
            Tiger_Git_Exception::DIRTY_TREE            => 'git.error.dirty_tree',
            Tiger_Git_Exception::NOTHING_TO_COMMIT     => 'git.error.nothing_to_commit',
            Tiger_Git_Exception::TRANSPORT_UNAVAILABLE => 'git.error.transport_unavailable',
            Tiger_Git_Exception::NOT_IMPLEMENTED       => 'git.error.not_implemented',
        ];
        return $map[$e->code()] ?? 'git.error.general';
    }
}
