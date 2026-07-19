<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Agent_Loop — run an agent turn as a multi-step ReAct loop (TIGERAGENT.md §5).
 *
 * Tiger owns the loop, not the model. The user and the model never speak directly — they both
 * speak THROUGH Agent. Within a single turn the model may query the Scout 0+ times ("let me
 * see that file", "does this already exist?"); each read auto-runs and its result is fed back,
 * so the model gathers context, then acts, then reports — all server-side, invisibly, until it
 * says `done`.
 *
 * WRITES are gated by MODE (TIGERAGENT.md §3a) — the vibe-coder's dial:
 *   - ask  — every write pauses for approval (the loop stops, surfaces a chip).
 *   - auto — guarded, reversible /api writes run automatically; the sharp tiers still pause.
 *   - yolo — everything the role permits runs automatically; the loop reads→writes→loops to
 *            completion in one turn and reports at the end.
 * Mode only skips the PROMPT, never the ACL, the lint, the sandbox, or the audit — so it can
 * never do more than the user's role already allows; it just stops asking.
 *
 * @api
 */
class Tiger_Agent_Loop
{
    /** Hard ceiling on model calls per turn (each read round-trip is one call). */
    const MAX_STEPS = 8;

    /** Mode ordering: a write auto-runs when the mode's rank exceeds the action's autoRank. */
    const MODE_ORDER = ['ask' => 0, 'auto' => 1, 'yolo' => 2];

    /** @var string */ protected $_role;
    /** @var string */ protected $_userId;
    /** @var string */ protected $_orgId;

    /**
     * @param  string $role   the acting role (drives tools + Scout/Forge gating)
     * @param  string $userId the acting user
     * @param  string $orgId  the tenant
     * @return void
     */
    public function __construct($role, $userId, $orgId)
    {
        $this->_role   = (string) $role;
        $this->_userId = (string) $userId;
        $this->_orgId  = (string) $orgId;
    }

    /** Numeric rank for a mode string (unknown → ask). */
    public static function modeRank($mode)
    {
        return self::MODE_ORDER[$mode] ?? 0;
    }

    /**
     * Run a turn from a human message.
     *
     * @param  Zend_Db_Table_Row_Abstract $conversation the thread row
     * @param  string                     $userText     the human's message
     * @param  array                      $context      request context (path, …)
     * @param  string                     $mode         ask | auto | yolo
     * @return array{run_id:string,say:string,actions:array,navigate:?string,done:bool,status:string,mode:string}
     * @throws RuntimeException on a provider failure
     */
    public function run($conversation, $userText, array $context = [], $mode = 'ask')
    {
        $conversationId = (string) $conversation->conversation_id;
        $messages = new Tiger_Model_AgentMessage();

        $messages->append($conversationId, Tiger_Model_AgentMessage::ROLE_USER, $userText);
        $this->_touch($conversation, $userText);

        $working = $this->_transcriptToNeutral($messages->transcript($conversationId));
        $this->_enrichLastUserTurn($working, $userText, $context, $mode);

        return $this->_converse($conversation, $working, $context, $mode);
    }

    /**
     * Continue the conversation with tool/execution results (used after an approval so the
     * model can verify + report, or take the next step). Not shown as a user bubble.
     *
     * @param  Zend_Db_Table_Row_Abstract $conversation the thread
     * @param  string                     $feedbackText the execution results to feed the model
     * @param  array                      $context      request context
     * @param  string                     $mode         ask | auto | yolo
     * @return array
     * @throws RuntimeException on a provider failure
     */
    public function followUp($conversation, $feedbackText, array $context = [], $mode = 'ask')
    {
        $working = $this->_transcriptToNeutral((new Tiger_Model_AgentMessage())->transcript((string) $conversation->conversation_id));
        $working[] = ['role' => 'user', 'content' => $feedbackText];
        return $this->_converse($conversation, $working, $context, $mode);
    }

    // -------------------------------------------------------------------------

    /**
     * The step loop: call the model, run its reads (Scout) + writes (Forge, mode-gated), feed
     * read/exec results back, and repeat until the model is done or proposes a write that needs
     * approval — bounded by MAX_STEPS.
     *
     * @param  Zend_Db_Table_Row_Abstract $conversation
     * @param  array                      $working the provider-neutral message list to date
     * @param  array                      $context
     * @param  string                     $mode
     * @return array
     */
    protected function _converse($conversation, array $working, array $context, $mode)
    {
        $conversationId = (string) $conversation->conversation_id;
        $messages = new Tiger_Model_AgentMessage();
        $runs     = new Tiger_Model_AgentRun();
        $runId    = $runs->open($conversationId, $this->_userId);

        $capabilities = Tiger_Agent::capabilities();
        $context     += ['role' => $this->_role, 'capabilities' => $capabilities, 'mode' => $mode];
        $system       = Tiger_Agent_Tools::systemPrompt($this->_role, $capabilities, $context, $mode);

        $adapter  = Tiger_Agent_Provider_Factory::make((string) $conversation->provider);
        $model    = (string) $conversation->model;
        $key      = Tiger_Agent::apiKey();
        $scout    = new Tiger_Agent_Scout($this->_role);
        $forge    = new Tiger_Agent_Forge($this->_role);
        $modeRank = self::modeRank($mode);

        $ledger = [];
        $usage  = ['input' => 0, 'output' => 0];
        $say = ''; $navigate = null; $done = true;

        for ($step = 0; $step < self::MAX_STEPS; $step++) {
            try {
                $res = $adapter->complete($system, $working, $model, $key);
            } catch (Throwable $e) {
                $runs->finish($runId, Tiger_Model_AgentRun::STATUS_ERROR, $this->_strip($ledger), $usage, $e->getMessage());
                throw $e;
            }
            $usage['input']  += (int) $res['usage']['input'];
            $usage['output'] += (int) $res['usage']['output'];

            $c = Tiger_Agent_Contract::parse($res['text']);
            if ($c['say'] !== '')  { $say = $c['say']; }
            if ($c['navigate'])    { $navigate = $c['navigate']; }
            $done = $c['done'];

            $feedback = [];
            $proposed = false;
            foreach ($c['actions'] as $action) {
                if (Tiger_Agent_Contract::isRead($action['type'])) {
                    $entry = $scout->execute($action);
                    $ledger[] = $entry;
                    $feedback[] = $entry;
                    continue;
                }
                // A write: auto-approve it when the mode's rank clears the action's tier.
                $rank = Tiger_Agent_Forge::autoRank($action);
                if ($rank >= 0 && $modeRank > $rank) {
                    $action['approved'] = true;
                }
                $entry = $forge->execute($action);
                $ledger[] = $entry;
                if ($entry['status'] === 'proposed') { $proposed = true; }
                else { $feedback[] = $entry; }
            }

            // A write is waiting on the human — stop the loop and surface it.
            if ($proposed) { break; }
            // Still gathering/acting and something to report back → feed results + continue.
            if ($feedback && !$done && $step < self::MAX_STEPS - 1) {
                $working[] = ['role' => 'assistant', 'content' => $res['text']];
                $working[] = ['role' => 'user', 'content' => $this->_feedbackText($feedback)];
                continue;
            }
            break;
        }

        $blocked = false; $errored = false;
        foreach ($ledger as $e) {
            if (($e['status'] ?? '') === 'proposed') { $blocked = true; }
            if (($e['status'] ?? '') === 'error')    { $errored = true; }
        }
        $status = $blocked ? Tiger_Model_AgentRun::STATUS_BLOCKED
                : ($errored ? Tiger_Model_AgentRun::STATUS_PARTIAL : Tiger_Model_AgentRun::STATUS_OK);

        $clean = $this->_strip($ledger);
        $meta  = ['run_id' => $runId, 'actions' => $clean, 'navigate' => $navigate, 'done' => $done, 'status' => $status, 'mode' => $mode];
        $messages->append($conversationId, Tiger_Model_AgentMessage::ROLE_ASSISTANT, $say, $meta, $runId);
        $runs->finish($runId, $status, $clean, $usage);
        $this->_touch($conversation, '');

        return [
            'run_id'   => $runId,
            'say'      => $say,
            'actions'  => $clean,
            'navigate' => $navigate,
            'done'     => $done,
            'status'   => $status,
            'mode'     => $mode,
        ];
    }

    /** Map the stored transcript to provider-neutral messages, oldest first. */
    protected function _transcriptToNeutral(array $transcript)
    {
        $out = [];
        foreach ($transcript as $row) {
            $role = ($row['role'] === Tiger_Model_AgentMessage::ROLE_ASSISTANT) ? 'assistant' : 'user';
            $text = (string) $row['content'];
            if ($text !== '') {
                $out[] = ['role' => $role, 'content' => $text];
            }
        }
        return $out;
    }

    /** Replace the trailing user turn with the context-enriched envelope the model expects. */
    protected function _enrichLastUserTurn(array &$working, $userText, array $context, $mode)
    {
        $envelope = json_encode(
            ['message' => $userText, 'context' => $context + ['mode' => $mode]],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
        );
        if ($working && $working[count($working) - 1]['role'] === 'user') {
            $working[count($working) - 1]['content'] = $envelope;
        } else {
            $working[] = ['role' => 'user', 'content' => $envelope];
        }
    }

    /** Compose the model-facing text block from a step's read/exec results. */
    protected function _feedbackText(array $entries)
    {
        $parts = [];
        foreach ($entries as $e) {
            $head = strtoupper((string) ($e['type'] ?? '')) . ' [' . ($e['status'] ?? '') . '] ' . ($e['summary'] ?? '');
            $body = isset($e['feedback']) && $e['feedback'] !== '' ? "\n" . $e['feedback'] : '';
            $parts[] = $head . $body;
        }
        return "[tool results — act on these, then continue or finish]\n\n" . implode("\n\n", $parts);
    }

    /** Drop the heavy `feedback` payload before a ledger is persisted / returned to the UI. */
    protected function _strip(array $ledger)
    {
        foreach ($ledger as &$e) { unset($e['feedback']); }
        unset($e);
        return $ledger;
    }

    /** Title a new thread + bump its recency (update always refreshes updated_at). */
    protected function _touch($conversation, $userText)
    {
        $data = [];
        if ((string) $conversation->title === '' && $userText !== '') {
            $data['title'] = mb_substr(trim($userText), 0, 80);
        }
        try {
            $model = new Tiger_Model_AgentConversation();
            $model->update($data, $model->getAdapter()->quoteInto('conversation_id = ?', (string) $conversation->conversation_id));
        } catch (Throwable $e) { /* cosmetic */ }
    }
}
