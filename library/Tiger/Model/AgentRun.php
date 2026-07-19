<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Model_AgentRun — one turn's execution + control record (see migration 0036).
 *
 * The audit spine for the human-in-the-loop flow (TIGERAGENT.md §3): a turn that proposes
 * a write action is stored `blocked` with that action `proposed` in the `actions` ledger;
 * approving it later (Agent_Service_Agent::approve) finds the run by id, executes the
 * proposed action through the Forge, and rewrites its ledger entry to `done` — all without
 * re-calling the model. Read-class actions execute inline and land here already `done`.
 *
 * @api
 */
class Tiger_Model_AgentRun extends Tiger_Model_Table
{
    protected $_name    = 'agent_run';
    protected $_primary = 'run_id';

    const STATUS_OK      = 'ok';       // completed, nothing pending
    const STATUS_PARTIAL = 'partial';  // some actions done, some failed
    const STATUS_BLOCKED = 'blocked';  // at least one write action awaits approval
    const STATUS_ERROR   = 'error';    // the turn failed

    /**
     * Open a run for a turn and return its id.
     *
     * @param  string $conversationId the thread
     * @param  string $userId         the actor
     * @return string                 the new run_id
     */
    public function open($conversationId, $userId)
    {
        return $this->insert([
            'conversation_id' => (string) $conversationId,
            'user_id'         => (string) $userId,
            'status'          => self::STATUS_OK,
            'steps'           => 1,
        ]);
    }

    /**
     * Persist the outcome of a turn.
     *
     * @param  string   $runId   the run to finalize
     * @param  string   $status  a STATUS_* value
     * @param  array    $actions the action ledger (encoded to JSON)
     * @param  array    $usage   ['input'=>int,'output'=>int] token counts
     * @param  string   $error   an error message, if any
     * @return void
     */
    public function finish($runId, $status, array $actions, array $usage = [], $error = '')
    {
        $this->update([
            'status'        => (string) $status,
            'actions'       => json_encode($actions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'input_tokens'  => isset($usage['input'])  ? (int) $usage['input']  : null,
            'output_tokens' => isset($usage['output']) ? (int) $usage['output'] : null,
            'error'         => ($error !== '' ? (string) $error : null),
        ], $this->getAdapter()->quoteInto('run_id = ?', (string) $runId));
    }

    /**
     * Load a run owned by a user, or null.
     *
     * @param  string $runId  the run
     * @param  string $userId the requesting owner
     * @return Zend_Db_Table_Row_Abstract|null
     */
    public function ownedById($runId, $userId)
    {
        return $this->fetchRow(
            $this->activeSelect()
                ->where('run_id = ?', (string) $runId)
                ->where('user_id = ?', (string) $userId)
        );
    }
}
