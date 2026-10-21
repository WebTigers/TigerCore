<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Model_GitOperationLog — the append-only git audit trail (TIGER-334, migration 0054).
 *
 * One row per operation: who (actor type + id), what (operation), on which branch, the ref before and
 * after, the result, and an error code on failure. NO secret, NO file contents — it answers "what did
 * this actor do to this module's repo?" and nothing more. Append-only (no soft-delete, no update).
 *
 * @api
 * @since 1.38.0
 */
class Tiger_Model_GitOperationLog extends Tiger_Model_Table
{
    protected $_name    = 'git_operation_log';
    protected $_primary = 'git_operation_log_id';

    const ACTOR_USER  = 'user';
    const ACTOR_AGENT = 'agent';
    const RESULT_OK    = 'ok';
    const RESULT_ERROR = 'error';

    /** The recent activity for a module, newest first (the Activity panel). */
    public function recentForModule($orgId, $moduleKey, $limit = 50)
    {
        return $this->fetchAll(
            $this->select()
                ->where('org_id = ?', (string) $orgId)
                ->where('module_key = ?', (string) $moduleKey)
                ->order('created_at DESC')
                ->limit(max(1, (int) $limit))
        );
    }

    /**
     * Record one operation. Returns the new log id. Fail-soft is the CALLER's concern — the audit
     * write must not be swallowed silently, but it must also never carry a secret (enforced by the
     * column set, not by trust).
     *
     * @param  array $fields org_id, module_key, actor_type, actor_id, operation, branch?, ref_before?, ref_after?, result, error_code?
     * @return string
     */
    public function record(array $fields): string
    {
        return (string) $this->insert([
            'org_id'      => (string) ($fields['org_id'] ?? ''),
            'module_key'  => (string) ($fields['module_key'] ?? ''),
            'actor_type'  => (string) ($fields['actor_type'] ?? self::ACTOR_USER),
            'actor_id'    => $fields['actor_id'] ?? null,
            'operation'   => (string) ($fields['operation'] ?? ''),
            'branch'      => $fields['branch'] ?? null,
            'ref_before'  => $fields['ref_before'] ?? null,
            'ref_after'   => $fields['ref_after'] ?? null,
            'result'      => (string) ($fields['result'] ?? self::RESULT_OK),
            'error_code'  => $fields['error_code'] ?? null,
        ]);
    }
}
