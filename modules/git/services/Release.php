<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Git_Service_Release — cut a module release through QA (TIGER-334 / TIGER-367).
 *
 * The owner-side of the module: create a semver tag, submit it to QA, and (as the QA reviewer) approve
 * or reject it. The service is reachable by admins (configs/acl.ini), but the two SHARP operations —
 * creating a tag and submitting a release — are stricter: they require the `tag` / `submit` privilege
 * on the Tiger_Git::RESOURCE_RELEASE CAPABILITY (granted to superadmin, named for the capability not a
 * role tier — TIGER-335), so an ordinary admin who can read releases still cannot cut one.
 *
 * A submitted tag is immutable: a rejection is fixed by a NEW tag, never by moving this one. `tag`
 * refuses a version already taken by a live release.
 *
 * @api
 * @since 1.38.0
 */
class Git_Service_Release extends Git_Service_Base
{
    /**
     * Create an annotated semver tag on the connected repo (owner-only). Returns {tag, sha}.
     *
     * @param  array $params module_key, version
     */
    public function tag(array $params): void
    {
        if (!$this->_signedIn()) { return; }
        if (!$this->_isAllowed(Tiger_Git::PRIV_TAG, Tiger_Git::RESOURCE_RELEASE)) { $this->_error('git.error.not_allowed'); return; }

        $moduleKey = $this->_slug((string) ($params['module_key'] ?? ''));
        if ($moduleKey === null) { $this->_error('git.error.invalid_module'); return; }
        $version = trim((string) ($params['version'] ?? ''));
        if (!Tiger_Git::isSemver($version)) { $this->_error('git.error.invalid_version'); return; }

        $conn = $this->_connection($moduleKey);
        if (!$conn) { $this->_error('git.error.not_connected'); return; }

        $existing = (new Tiger_Model_ModuleRelease())->byTag($this->_org_id, $moduleKey, $version);
        if ($existing && (string) $existing->status !== Tiger_Model_ModuleRelease::STATUS_WITHDRAWN) {
            $this->_error('git.error.tag_exists'); return;   // a submitted tag is immutable
        }

        try {
            $out = $this->_adapter()->tag($this->_tree($conn), $version);
        } catch (Tiger_Git_Exception $e) {
            $this->_log($moduleKey, 'tag', 'error', ['error_code' => $e->code()]);
            $this->_error($this->_gitErrorKey($e)); return;
        }
        $this->_log($moduleKey, 'tag', 'ok', ['ref_after' => (string) ($out['sha'] ?? '')]);
        $this->_success(['tag' => (string) $out['tag'], 'sha' => (string) $out['sha']], 'git.release.tagged');
    }

    /**
     * Submit a tagged release to QA (owner-only). Records a `submitted` module_release row.
     *
     * @param  array $params module_key, version, commit_sha, qa_report_ref?
     */
    public function submit(array $params): void
    {
        if (!$this->_signedIn()) { return; }
        if (!$this->_isAllowed(Tiger_Git::PRIV_SUBMIT, Tiger_Git::RESOURCE_RELEASE)) { $this->_error('git.error.not_allowed'); return; }

        $form = new Git_Form_Submit();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        $v = $form->getValues();

        $moduleKey = $this->_slug((string) $v['module_key']);
        if ($moduleKey === null) { $this->_error('git.error.invalid_module'); return; }
        $version = trim((string) $v['version']);
        if (!Tiger_Git::isSemver($version)) { $this->_error('git.error.invalid_version'); return; }

        $conn = $this->_connection($moduleKey);
        if (!$conn) { $this->_error('git.error.not_connected'); return; }

        $existing = (new Tiger_Model_ModuleRelease())->byTag($this->_org_id, $moduleKey, $version);
        if ($existing && in_array((string) $existing->status, [
            Tiger_Model_ModuleRelease::STATUS_SUBMITTED,
            Tiger_Model_ModuleRelease::STATUS_IN_QA,
            Tiger_Model_ModuleRelease::STATUS_APPROVED,
        ], true)) {
            $this->_error('git.error.release_exists'); return;
        }

        $sha = strtolower(trim((string) $v['commit_sha']));
        $qa  = trim((string) ($v['qa_report_ref'] ?? ''));
        $id  = $this->_transaction(function () use ($moduleKey, $version, $sha, $qa) {
            return (new Tiger_Model_ModuleRelease())->insert([
                'org_id'        => (string) $this->_org_id,
                'module_key'    => $moduleKey,
                'version'       => $version,
                'git_tag'       => $version,
                'commit_sha'    => $sha,
                'qa_report_ref' => $qa !== '' ? $qa : null,
                'submitted_at'  => gmdate('Y-m-d H:i:s'),
                'status'        => Tiger_Model_ModuleRelease::STATUS_SUBMITTED,
            ]);
        });
        $this->_log($moduleKey, 'submit', 'ok', ['ref_after' => $sha]);
        $this->_success(['module_release_id' => (string) $id, 'version' => $version, 'status' => Tiger_Model_ModuleRelease::STATUS_SUBMITTED], 'git.release.submitted');
    }

    /**
     * Approve or reject a submitted release (the QA reviewer — admin). Moves it to approved|rejected.
     *
     * @param  array $params module_release_id, decision (approve|reject)
     */
    public function decide(array $params): void
    {
        if (!$this->_signedIn() || !$this->_isAllowed('decide')) { $this->_error('core.api.error.not_allowed'); return; }

        $id       = (string) ($params['module_release_id'] ?? '');
        $decision = (string) ($params['decision'] ?? '');
        if (!in_array($decision, ['approve', 'reject'], true)) { $this->_error('git.error.invalid_decision'); return; }

        $model = new Tiger_Model_ModuleRelease();
        $row   = $id !== '' ? $model->findById($id) : null;
        if (!$row || (string) $row->org_id !== (string) $this->_org_id) { $this->_error('git.error.release_not_found'); return; }
        if (!in_array((string) $row->status, [Tiger_Model_ModuleRelease::STATUS_SUBMITTED, Tiger_Model_ModuleRelease::STATUS_IN_QA], true)) {
            $this->_error('git.error.release_decided'); return;
        }

        $new = $decision === 'approve' ? Tiger_Model_ModuleRelease::STATUS_APPROVED : Tiger_Model_ModuleRelease::STATUS_REJECTED;
        $this->_transaction(function () use ($row, $new) {
            $row->status     = $new;
            $row->decided_at = gmdate('Y-m-d H:i:s');
            $row->save();
        });
        $this->_log((string) $row->module_key, 'release.' . $decision, 'ok', ['ref_after' => (string) $row->commit_sha]);
        $this->_success(['module_release_id' => $id, 'status' => $new], 'git.release.decided');
    }

    /**
     * Every release for a module, newest first (any status).
     *
     * @param  array $params module_key
     */
    public function list(array $params): void
    {
        if (!$this->_signedIn() || !$this->_isAllowed('list')) { $this->_error('core.api.error.not_allowed'); return; }
        $moduleKey = $this->_slug((string) ($params['module_key'] ?? ''));
        if ($moduleKey === null) { $this->_error('git.error.invalid_module'); return; }

        $out = [];
        foreach ((new Tiger_Model_ModuleRelease())->forModule($this->_org_id, $moduleKey) as $r) {
            $out[] = [
                'module_release_id' => (string) $r->module_release_id,
                'version'           => (string) $r->version,
                'git_tag'           => (string) $r->git_tag,
                'commit_sha'        => (string) $r->commit_sha,
                'status'            => (string) $r->status,
                'qa_report_ref'     => $r->qa_report_ref !== null ? (string) $r->qa_report_ref : null,
                'submitted_at'      => $r->submitted_at !== null ? (string) $r->submitted_at : null,
                'decided_at'        => $r->decided_at !== null ? (string) $r->decided_at : null,
                'created_at'        => (string) $r->created_at,
            ];
        }
        $this->_success(['module_key' => $moduleKey, 'releases' => $out]);
    }
}
