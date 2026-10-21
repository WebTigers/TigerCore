<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Git_Service_Update — receive approved module updates (TIGER-334 / TIGER-367).
 *
 * The consumer side of the module: see which connected modules have a newer APPROVED release, apply one
 * (download the tag's archive and update the module in place), set the per-module update policy, and
 * roll back to the previous version if a release goes bad. Admin-only (configs/acl.ini).
 *
 * The load-bearing safety rule: an update ONLY ever installs an APPROVED release (status `approved` in
 * module_release) at its exact tag — never an arbitrary branch, never an unapproved version. `apply`
 * fetches that tag's archive through the GitHub adapter and hands it to the one shared installer tail
 * (Tiger_Module_Installer::installFromTarball, which backs up → extracts → places → migrates →
 * publishes → records), update-in-place. The pin records `previous_version` so a bad release is one
 * `rollback` away, and `pinned_version` keeps auto-update from reapplying the version a rollback fled.
 *
 * @api
 * @since 1.38.0
 */
class Git_Service_Update extends Git_Service_Base
{
    /**
     * Which connected modules have a newer approved release. One module (module_key) or the whole org.
     *
     * @param  array $params module_key?
     */
    public function check(array $params): void
    {
        if (!$this->_signedIn() || !$this->_isAllowed('check')) { $this->_error('core.api.error.not_allowed'); return; }

        $only = isset($params['module_key']) ? $this->_slug((string) $params['module_key']) : null;
        if (isset($params['module_key']) && $only === null) { $this->_error('git.error.invalid_module'); return; }

        $connections = (new Tiger_Model_GitRepoConnection())->forOrg($this->_org_id);
        $pinModel    = new Tiger_Model_ModuleInstallPin();
        $relModel    = new Tiger_Model_ModuleRelease();

        $updates = [];
        foreach ($connections as $conn) {
            $moduleKey = (string) $conn->module_key;
            if ($only !== null && $moduleKey !== $only) { continue; }

            $pin       = $pinModel->forModule($this->_org_id, $moduleKey);
            $installed = $pin && $pin->installed_version !== null ? (string) $pin->installed_version : null;
            $channel   = $pin ? (string) $pin->channel : Tiger_Git::POLICY_NOTIFY;

            $approved = $relModel->approvedForModule($this->_org_id, $moduleKey);
            $latest   = count($approved) ? (string) $approved->current()->version : null;

            $pending = $latest !== null
                && $channel !== Tiger_Git::POLICY_PINNED
                && ($installed === null || version_compare(Tiger_Git::normalizeVersion($latest), Tiger_Git::normalizeVersion($installed), '>'));

            $updates[] = [
                'module_key'             => $moduleKey,
                'installed_version'      => $installed,
                'latest_approved_version'=> $latest,
                'channel'                => $channel,
                'pending'                => $pending,
            ];
        }
        $this->_success(['updates' => $updates]);
    }

    /**
     * Apply an approved update to a module — download the tag's archive and update in place. Defaults to
     * the latest approved release; a specific `version` must also be approved. Records the rollback anchor.
     *
     * @param  array $params module_key, version?
     */
    public function apply(array $params): void
    {
        if (!$this->_signedIn() || !$this->_isAllowed('apply')) { $this->_error('core.api.error.not_allowed'); return; }

        $moduleKey = $this->_slug((string) ($params['module_key'] ?? ''));
        if ($moduleKey === null) { $this->_error('git.error.invalid_module'); return; }
        $conn = $this->_connection($moduleKey);
        if (!$conn) { $this->_error('git.error.not_connected'); return; }

        // Resolve the target to an APPROVED release (never an arbitrary version).
        $relModel = new Tiger_Model_ModuleRelease();
        $wanted   = trim((string) ($params['version'] ?? ''));
        $release  = null;
        if ($wanted !== '') {
            $row = $relModel->byTag($this->_org_id, $moduleKey, $wanted);
            if ($row && (string) $row->status === Tiger_Model_ModuleRelease::STATUS_APPROVED) { $release = $row; }
        } else {
            $approved = $relModel->approvedForModule($this->_org_id, $moduleKey);
            if (count($approved)) { $release = $approved->current(); }
        }
        if (!$release) { $this->_error($wanted !== '' ? 'git.error.not_approved' : 'git.error.no_update'); return; }

        $target = (string) $release->version;

        // A rollback pins the bad version; refuse to reapply exactly that one.
        $pinModel = new Tiger_Model_ModuleInstallPin();
        $pin      = $pinModel->forModule($this->_org_id, $moduleKey);
        if ($pin && (string) $pin->channel === Tiger_Git::POLICY_PINNED
            && $pin->pinned_version !== null && (string) $pin->pinned_version === $target) {
            $this->_error('git.error.version_pinned'); return;
        }
        $installed = $pin && $pin->installed_version !== null ? (string) $pin->installed_version : null;
        if ($installed !== null && version_compare(Tiger_Git::normalizeVersion($target), Tiger_Git::normalizeVersion($installed), '==')) {
            $this->_error('git.error.no_update'); return;   // already on it
        }

        $applied = $this->_download($conn, $moduleKey, (string) $release->git_tag, (string) $release->commit_sha, 'update.apply');
        if ($applied === false) { return; }   // _download set the error

        $this->_transaction(function () use ($pinModel, $pin, $moduleKey, $target, $installed) {
            $this->_savePin($pinModel, $pin, $moduleKey, [
                'installed_version' => $target,
                'previous_version'  => $installed,
            ]);
        });
        $this->_success(['module_key' => $moduleKey, 'version' => $target, 'previous_version' => $installed], 'git.update.applied');
    }

    /**
     * Set a module's update policy: auto | notify | pinned (+ an optional pinned_version).
     *
     * @param  array $params module_key, channel, pinned_version?
     */
    public function setPolicy(array $params): void
    {
        if (!$this->_signedIn() || !$this->_isAllowed('setPolicy')) { $this->_error('core.api.error.not_allowed'); return; }

        $moduleKey = $this->_slug((string) ($params['module_key'] ?? ''));
        if ($moduleKey === null) { $this->_error('git.error.invalid_module'); return; }
        $channel = (string) ($params['channel'] ?? '');
        if (!in_array($channel, [Tiger_Git::POLICY_AUTO, Tiger_Git::POLICY_NOTIFY, Tiger_Git::POLICY_PINNED], true)) {
            $this->_error('git.error.invalid_channel'); return;
        }
        $pinnedVersion = trim((string) ($params['pinned_version'] ?? ''));

        $pinModel = new Tiger_Model_ModuleInstallPin();
        $pin      = $pinModel->forModule($this->_org_id, $moduleKey);
        $fields   = ['channel' => $channel];
        if ($channel === Tiger_Git::POLICY_PINNED && $pinnedVersion !== '') { $fields['pinned_version'] = $pinnedVersion; }

        $this->_transaction(function () use ($pinModel, $pin, $moduleKey, $fields) {
            $this->_savePin($pinModel, $pin, $moduleKey, $fields);
        });
        $this->_log($moduleKey, 'update.policy', 'ok');
        $this->_success(['pin' => $this->_pinView($pinModel->forModule($this->_org_id, $moduleKey))], 'git.update.policy_set');
    }

    /**
     * Roll a module back to its previous version (which must still be an approved release). Pins the
     * version it fled so auto-update does not immediately reapply it.
     *
     * @param  array $params module_key
     */
    public function rollback(array $params): void
    {
        if (!$this->_signedIn() || !$this->_isAllowed('rollback')) { $this->_error('core.api.error.not_allowed'); return; }

        $moduleKey = $this->_slug((string) ($params['module_key'] ?? ''));
        if ($moduleKey === null) { $this->_error('git.error.invalid_module'); return; }
        $conn = $this->_connection($moduleKey);
        if (!$conn) { $this->_error('git.error.not_connected'); return; }

        $pinModel = new Tiger_Model_ModuleInstallPin();
        $pin      = $pinModel->forModule($this->_org_id, $moduleKey);
        $previous = $pin && $pin->previous_version !== null ? (string) $pin->previous_version : null;
        $current  = $pin && $pin->installed_version !== null ? (string) $pin->installed_version : null;
        if ($previous === null) { $this->_error('git.error.no_previous'); return; }

        $release = (new Tiger_Model_ModuleRelease())->byTag($this->_org_id, $moduleKey, $previous);
        if (!$release || (string) $release->status !== Tiger_Model_ModuleRelease::STATUS_APPROVED) {
            $this->_error('git.error.not_approved'); return;
        }

        $applied = $this->_download($conn, $moduleKey, (string) $release->git_tag, (string) $release->commit_sha, 'update.rollback');
        if ($applied === false) { return; }

        $this->_transaction(function () use ($pinModel, $pin, $moduleKey, $previous, $current) {
            $this->_savePin($pinModel, $pin, $moduleKey, [
                'installed_version' => $previous,
                'previous_version'  => null,
                'channel'           => Tiger_Git::POLICY_PINNED,   // don't auto-reapply the bad one
                'pinned_version'    => $current,                   // the version we fled
            ]);
        });
        $this->_success(['module_key' => $moduleKey, 'version' => $previous, 'pinned_version' => $current], 'git.update.rolled_back');
    }

    // --- internals -------------------------------------------------------------------------------

    /**
     * Download an approved tag's archive and update the module in place. Returns true, or false after
     * setting a mapped error (the caller returns on false). Shared by apply + rollback.
     */
    private function _download($conn, string $moduleKey, string $tag, string $sha, string $operation): bool
    {
        $tmp = tempnam(sys_get_temp_dir(), 'tigergit') . '.tar.gz';
        try {
            $this->_adapter()->fetchArchive($this->_tree($conn), $tag, $tmp);
            $this->_installTarball($tmp, [
                'repository' => $conn->repo_owner . '/' . $conn->repo_name,
                'ref'        => $tag,
                'source'     => Tiger_Model_Module::SOURCE_URL,
            ], ['force' => true]);
        } catch (Tiger_Git_Exception $e) {
            $this->_log($moduleKey, $operation, 'error', ['error_code' => $e->code()]);
            $this->_error($this->_gitErrorKey($e));
            return false;
        } catch (\Throwable $e) {
            Tiger_Log::error('git.update.install_failed', ['module' => $moduleKey, 'error' => $e->getMessage()]);
            $this->_log($moduleKey, $operation, 'error', ['error_code' => 'install']);
            $this->_error('git.error.install_failed');
            return false;
        } finally {
            if (is_file($tmp)) { @unlink($tmp); }
        }
        $this->_log($moduleKey, $operation, 'ok', ['ref_after' => $sha]);
        return true;
    }

    /** Insert or update the module_install_pin row with the given fields. */
    private function _savePin(Tiger_Model_ModuleInstallPin $model, $pin, string $moduleKey, array $fields): void
    {
        if ($pin) {
            foreach ($fields as $k => $v) { $pin->$k = $v; }
            $pin->save();
        } else {
            $model->insert(array_merge([
                'org_id'     => (string) $this->_org_id,
                'module_key' => $moduleKey,
                'channel'    => Tiger_Git::POLICY_NOTIFY,
                'status'     => 'active',
            ], $fields));
        }
    }
}
