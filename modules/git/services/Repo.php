<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Git_Service_Repo — the `/api` surface for a module's GitHub connection (TIGER-334 / TIGER-367).
 *
 * The entry point to the whole module: connect an installed module to its private repo, see its
 * working-tree status, re-check the host's git capabilities, list the org's connections, read the
 * audit trail, and disconnect. Admin-only (deny-by-default in configs/acl.ini); every method also
 * re-checks the privilege in-method (defense-in-depth, the only gate for an in-process caller).
 *
 * A connection carries NO secret — only a credential_ref. `connect` stores the token through the
 * write-only Tiger_Git_Credential, then VERIFIES it against the real repo (a cheap authenticated GET)
 * BEFORE the connection row is committed; a bad token or wrong repo is reported and the just-stored
 * credential is revoked, so a failed connect leaves nothing behind. A reconnect reuses the active-only
 * unique (a disconnect frees `(org, module)`), revoking the superseded credential.
 *
 * @api
 * @since 1.38.0
 */
class Git_Service_Repo extends Git_Service_Base
{
    /**
     * Connect an installed module to its GitHub repo. Stores the token (encrypted), detects the
     * transport, verifies read access against the real repo, then records the connection.
     *
     * @param  array $params module_key, repo_owner, repo_name, token, default_branch?, write_enabled?
     * @return void
     */
    public function connect(array $params): void
    {
        if (!$this->_signedIn() || !$this->_isAllowed('connect')) { $this->_error('core.api.error.not_allowed'); return; }

        $form = new Git_Form_Connect();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        $v = $form->getValues();

        $moduleKey = $this->_slug((string) $v['module_key']);
        if ($moduleKey === null)                                           { $this->_error('git.error.invalid_module'); return; }
        if (Tiger_Git::repoSlug($v['repo_owner'], $v['repo_name']) === null) { $this->_error('git.error.invalid_repo'); return; }
        $dir = $this->_moduleDir($moduleKey);
        if ($dir === null)                                                 { $this->_error('git.error.module_missing'); return; }

        $branch       = trim((string) ($v['default_branch'] ?? '')) !== '' ? trim((string) $v['default_branch']) : 'main';
        $writeEnabled = !empty($v['write_enabled']);
        $token        = (string) $v['token'];

        // 1. store the credential so a transient tree can resolve it for verification.
        try {
            $cred   = new Tiger_Git_Credential();
            $stored = $cred->store($this->_org_id, $moduleKey, Tiger_Model_GitCredential::TYPE_TOKEN, $token, ['scopes' => 'contents:write']);
        } catch (Tiger_Git_Exception $e) {
            $this->_error($this->_gitErrorKey($e)); return;
        }
        $ref = (string) $stored['ref'];

        // 2. detect the transport + verify the token/repo with a cheap authenticated GET.
        $env       = Tiger_Git_Environment::detect();
        $transport = $env['transport'] !== '' ? $env['transport'] : Tiger_Git::TRANSPORT_API;
        $tree      = new Tiger_Git_WorkingTree($dir, (string) $v['repo_owner'], (string) $v['repo_name'], $branch, $branch, $transport, $ref, $writeEnabled);
        try {
            $this->_adapter()->listReleases($tree);   // ALWAYS a remote authenticated GET (the adapter routes releases to the API) — proves the token + repo
        } catch (Tiger_Git_Exception $e) {
            $cred->revoke($ref);
            $this->_log($moduleKey, 'connect', 'error', ['error_code' => $e->code()]);
            $this->_error($this->_gitErrorKey($e)); return;
        }

        // 3. record the connection (reconnect reuses the active-only unique; revoke the superseded secret).
        try {
            $this->_transaction(function () use ($moduleKey, $v, $branch, $transport, $writeEnabled, $ref, $cred) {
                $model    = new Tiger_Model_GitRepoConnection();
                $existing = $model->forModule($this->_org_id, $moduleKey);
                $fields = [
                    'org_id'           => (string) $this->_org_id,
                    'module_key'       => $moduleKey,
                    'provider'         => Tiger_Git_Provider_GitHub::PROVIDER,
                    'repo_owner'       => (string) $v['repo_owner'],
                    'repo_name'        => (string) $v['repo_name'],
                    'default_branch'   => $branch,
                    'transport'        => $transport,
                    'write_enabled'    => $writeEnabled ? 1 : 0,
                    'credential_ref'   => $ref,
                    'status'           => Tiger_Git::STATUS_CONNECTED,
                    'last_verified_at' => gmdate('Y-m-d H:i:s'),
                ];
                if ($existing) {
                    $oldRef = (string) $existing->credential_ref;
                    foreach ($fields as $k => $val) { $existing->$k = $val; }
                    $existing->save();
                    if ($oldRef !== '' && $oldRef !== $ref) { $cred->revoke($oldRef); }
                } else {
                    $model->insert($fields);
                }
            });
        } catch (\Throwable $e) {
            $cred->revoke($ref);
            Tiger_Log::error('git.repo.connect.failed', ['error' => $e->getMessage()]);
            $this->_error('git.error.general'); return;
        }

        $conn = $this->_connection($moduleKey);
        $this->_log($moduleKey, 'connect', 'ok');
        $this->_success(['connection' => $this->_connView($conn), 'environment' => $this->_envView($env)], 'git.repo.connected');
    }

    /**
     * A module's working-tree status. Returns the connection + environment always; the live `working`
     * status (branch/ahead/behind/changes) is present on a transport that supports it (shell), and
     * `status_available=false` on one that does not yet (the API — the reconciler is later work).
     *
     * @param  array $params module_key
     */
    public function status(array $params): void
    {
        if (!$this->_signedIn() || !$this->_isAllowed('status')) { $this->_error('core.api.error.not_allowed'); return; }
        $moduleKey = $this->_slug((string) ($params['module_key'] ?? ''));
        if ($moduleKey === null) { $this->_error('git.error.invalid_module'); return; }
        $conn = $this->_connection($moduleKey);
        if (!$conn) { $this->_error('git.error.not_connected'); return; }

        $data = ['connection' => $this->_connView($conn), 'environment' => $this->_envView(Tiger_Git_Environment::detect())];
        try {
            $data['working']          = $this->_adapter()->status($this->_tree($conn));
            $data['status_available'] = true;
        } catch (Tiger_Git_Exception $e) {
            if ($e->code() === Tiger_Git_Exception::NOT_IMPLEMENTED) {
                $data['working']          = null;
                $data['status_available'] = false;   // expected on the API transport — not an error
            } else {
                $this->_error($this->_gitErrorKey($e)); return;
            }
        }
        $this->_success($data);
    }

    /**
     * Re-detect the host's git capabilities and re-verify the connection, updating the stored transport
     * + status. The "Re-check environment" action behind the connection screen.
     *
     * @param  array $params module_key
     */
    public function recheck(array $params): void
    {
        if (!$this->_signedIn() || !$this->_isAllowed('recheck')) { $this->_error('core.api.error.not_allowed'); return; }
        $moduleKey = $this->_slug((string) ($params['module_key'] ?? ''));
        if ($moduleKey === null) { $this->_error('git.error.invalid_module'); return; }
        $conn = $this->_connection($moduleKey);
        if (!$conn) { $this->_error('git.error.not_connected'); return; }

        $env       = Tiger_Git_Environment::detect();
        $transport = $env['transport'] !== '' ? $env['transport'] : (string) $conn->transport;
        $conn->transport = $transport;   // verify against the freshly detected transport

        $status = Tiger_Git::STATUS_CONNECTED;
        try {
            $this->_adapter()->listReleases($this->_tree($conn));   // remote auth check (always the API)
        } catch (Tiger_Git_Exception $e) {
            $status = Tiger_Git::STATUS_DEGRADED;
        }

        $this->_transaction(function () use ($conn, $status) {
            $conn->status           = $status;
            $conn->last_verified_at = gmdate('Y-m-d H:i:s');
            $conn->save();   // transport already set above
        });
        $this->_log($moduleKey, 'recheck', $status === Tiger_Git::STATUS_CONNECTED ? 'ok' : 'error');

        $fresh = $this->_connection($moduleKey);
        $this->_success(['connection' => $this->_connView($fresh), 'environment' => $this->_envView($env)]);
    }

    /**
     * Every connection in the caller's org (the connections list).
     *
     * @param  array $params (none)
     */
    public function list(array $params): void
    {
        if (!$this->_signedIn() || !$this->_isAllowed('list')) { $this->_error('core.api.error.not_allowed'); return; }
        $rows = (new Tiger_Model_GitRepoConnection())->forOrg($this->_org_id);
        $this->_success(['connections' => array_map([$this, '_connView'], iterator_to_array($rows))]);
    }

    /**
     * The recent audit trail for a module (who did what to the repo).
     *
     * @param  array $params module_key, limit?
     */
    public function log(array $params): void
    {
        if (!$this->_signedIn() || !$this->_isAllowed('log')) { $this->_error('core.api.error.not_allowed'); return; }
        $moduleKey = $this->_slug((string) ($params['module_key'] ?? ''));
        if ($moduleKey === null) { $this->_error('git.error.invalid_module'); return; }
        $limit = min(200, max(1, (int) ($params['limit'] ?? 50)));
        $rows  = (new Tiger_Model_GitOperationLog())->recentForModule($this->_org_id, $moduleKey, $limit);

        $entries = [];
        foreach ($rows as $r) {
            $entries[] = [
                'operation'  => (string) $r->operation,
                'actor_type' => (string) $r->actor_type,
                'result'     => (string) $r->result,
                'branch'     => $r->branch !== null ? (string) $r->branch : null,
                'ref_after'  => $r->ref_after !== null ? (string) $r->ref_after : null,
                'error_code' => $r->error_code !== null ? (string) $r->error_code : null,
                'created_at' => (string) $r->created_at,
            ];
        }
        $this->_success(['module_key' => $moduleKey, 'entries' => $entries]);
    }

    /**
     * Disconnect a module: soft-delete the connection (freeing the active-only unique for a clean
     * reconnect) and revoke its credential.
     *
     * @param  array $params module_key
     */
    public function disconnect(array $params): void
    {
        if (!$this->_signedIn() || !$this->_isAllowed('disconnect')) { $this->_error('core.api.error.not_allowed'); return; }
        $moduleKey = $this->_slug((string) ($params['module_key'] ?? ''));
        if ($moduleKey === null) { $this->_error('git.error.invalid_module'); return; }
        $conn = $this->_connection($moduleKey);
        if (!$conn) { $this->_error('git.error.not_connected'); return; }

        $ref = (string) $conn->credential_ref;
        $this->_transaction(function () use ($conn, $ref) {
            $model = new Tiger_Model_GitRepoConnection();
            $model->softDelete($model->getAdapter()->quoteInto('git_repo_connection_id = ?', (string) $conn->git_repo_connection_id));
            if ($ref !== '') { (new Tiger_Git_Credential())->revoke($ref); }
        });
        $this->_log($moduleKey, 'disconnect', 'ok');
        $this->_success(['module_key' => $moduleKey], 'git.repo.disconnected');
    }
}
