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

    /** Char cap on an /api action's returned payload fed back to the model (keeps a big read from
     *  blowing the context; counts/head survive since most services put totals before the row array). */
    const MAX_DATA_FEEDBACK = 6000;

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
     * @param  array                      $attachments  provider-neutral file descriptors the caller
     *                                                   resolved: each `{filename, mime, kind, size}`
     *                                                   plus `extract` (document text) or
     *                                                   `disk`/`storage_key` (an image, read lazily
     *                                                   for vision). Core stays app-agnostic — it
     *                                                   never touches the module's attachment model.
     * @return array{run_id:string,say:string,actions:array,navigate:?string,done:bool,status:string,mode:string}
     * @throws RuntimeException on a provider failure
     */
    public function run($conversation, $userText, array $context = [], $mode = 'ask', array $attachments = [])
    {
        $conversationId = (string) $conversation->conversation_id;
        $messages = new Tiger_Model_AgentMessage();

        // Persist a lean attachment sidecar on the user message so the chips re-render on reload
        // (filenames + kinds only — never the bytes or extracted text).
        $meta = $attachments ? ['attachments' => $this->_attachmentMeta($attachments)] : null;
        $userMessageId = $messages->append($conversationId, Tiger_Model_AgentMessage::ROLE_USER, $userText, $meta);
        $this->_touch($conversation, $userText);

        $working = $this->_transcriptToNeutral($messages->transcript($conversationId));

        // Fold attachments into the turn: a text manifest into the context envelope (so the agent
        // knows what was shared and can act on it with its tools), and — when the model is
        // multimodal — the image bytes onto the trailing user turn as native vision input.
        if ($attachments) { $context['attachments'] = $this->_attachmentManifest($attachments); }
        $this->_enrichLastUserTurn($working, $userText, $context, $mode);
        if ($attachments) { $this->_attachImages($working, $conversation, $attachments); }

        $result = $this->_converse($conversation, $working, $context, $mode);
        $result['user_message_id'] = $userMessageId;   // lets the caller bind attachment rows to it
        return $result;
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
            $clientPending = false;
            foreach ($c['actions'] as $action) {
                if (Tiger_Agent_Contract::isRead($action['type'])) {
                    $entry = $scout->execute($action);
                    $ledger[] = $entry;
                    $feedback[] = $entry;
                    continue;
                }
                if (Tiger_Agent_Contract::isClient($action['type'])) {
                    // The server can't touch the DOM — hand this to the browser, which reads/writes
                    // the target and posts results to `resume` to continue the loop (the client leg).
                    $ledger[] = $this->_clientEntry($action);
                    $clientPending = true;
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

            // A write awaits the human, or a DOM op awaits the browser — hand off either way.
            if ($proposed || $clientPending) { break; }
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

    /** The lean sidecar stored on the user message (for history re-render) — no bytes, no text. */
    protected function _attachmentMeta(array $attachments)
    {
        $out = [];
        foreach ($attachments as $a) {
            $out[] = [
                'attachment_id' => (string) ($a['attachment_id'] ?? ''),
                'filename'      => (string) ($a['filename'] ?? 'file'),
                'kind'          => (($a['kind'] ?? 'file') === 'image') ? 'image' : 'file',
                'size'          => (int) ($a['size'] ?? 0),
            ];
        }
        return $out;
    }

    /**
     * The model-facing manifest folded into the turn context: what the user attached, plus any
     * readable text for a document. An image carries no text here — it rides as native vision input
     * (below) when the model can see, and is noted otherwise so the model never guesses at bytes.
     */
    protected function _attachmentManifest(array $attachments)
    {
        $out = [];
        foreach ($attachments as $a) {
            $kind = (($a['kind'] ?? 'file') === 'image') ? 'image' : 'file';
            $item = [
                'filename' => (string) ($a['filename'] ?? 'file'),
                'mime'     => (string) ($a['mime'] ?? ''),
                'kind'     => $kind,
                'size'     => (int) ($a['size'] ?? 0),
            ];
            if ($kind === 'file') {
                $extract = (string) ($a['extract'] ?? '');
                $item['text'] = $extract !== ''
                    ? $extract
                    : '[no readable text could be extracted — treat as a stored file the user wants you to act on]';
            }
            $out[] = $item;
        }
        return $out;
    }

    /**
     * Attach image bytes to the trailing user turn as native vision input, but ONLY when the
     * conversation's provider+model is multimodal (Factory::supportsVision). A text-only model gets
     * the manifest note instead of an image it can't read — never a hard provider failure. Bytes are
     * read lazily from the shared media disk here, so a non-vision turn never pays to encode them.
     */
    protected function _attachImages(array &$working, $conversation, array $attachments)
    {
        if (!$working || $working[count($working) - 1]['role'] !== 'user') { return; }
        if (!Tiger_Agent_Provider_Factory::supportsVision((string) $conversation->provider, (string) $conversation->model)) { return; }

        $images = [];
        foreach ($attachments as $a) {
            if (($a['kind'] ?? '') !== 'image' || empty($a['storage_key'])) { continue; }
            try {
                $bytes = Tiger_Media_Storage::disk((string) ($a['disk'] ?? 'local'))->get((string) $a['storage_key'], 'private');
            } catch (Throwable $e) {
                continue;
            }
            if ($bytes === null || $bytes === '') { continue; }
            $images[] = ['mime' => (string) ($a['mime'] ?? 'image/png'), 'data' => base64_encode($bytes)];
        }
        if ($images) { $working[count($working) - 1]['images'] = $images; }
    }

    /**
     * A ledger entry for a DOM action the browser must execute. Carries `value` (what to write)
     * so tiger.agent.js can apply it; status `client` signals "browser, your turn".
     *
     * @param  array $action a normalized dom.read / dom.write action
     * @return array
     */
    protected function _clientEntry(array $action)
    {
        $type   = (string) $action['type'];
        $target = (string) ($action['target'] ?? '');
        $summary = ($type === Tiger_Agent_Contract::DOM_READ ? 'Read ' : 'Update ') . ($target !== '' ? $target : 'the page');
        return [
            'type'    => $type,
            'reason'  => $action['reason'] ?? '',
            'status'  => 'client',
            'summary' => $summary,
            'action'  => [
                'type'   => $type,
                'target' => $target,
                'value'  => (string) ($action['value'] ?? ''),   // the browser applies this
                'kind'   => (string) ($action['kind'] ?? ''),
            ],
        ];
    }

    /** Compose the model-facing text block from a step's read/exec results. */
    protected function _feedbackText(array $entries)
    {
        $parts = [];
        foreach ($entries as $e) {
            $head = strtoupper((string) ($e['type'] ?? '')) . ' [' . ($e['status'] ?? '') . '] ' . ($e['summary'] ?? '');
            $body = '';
            if (isset($e['feedback']) && $e['feedback'] !== '') {
                // Scout read tools (file/grep/tree/…) carry their content here.
                $body = "\n" . $e['feedback'];
            } elseif (isset($e['detail']['data']) && $e['detail']['data'] !== null && $e['detail']['data'] !== []) {
                // An /api action's returned payload — the model needs the DATA (rows, counts, the new
                // id), not just "the call succeeded". Forge stores it in detail.data (not feedback), so
                // bridge it here or a read hands the model nothing to reason about. Capped for context.
                $json = json_encode(
                    $e['detail']['data'],
                    JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR
                );
                if (is_string($json) && $json !== '' && $json !== 'null') {
                    $body = "\nData: " . (strlen($json) > self::MAX_DATA_FEEDBACK
                        ? substr($json, 0, self::MAX_DATA_FEEDBACK) . '… (truncated)'
                        : $json);
                }
            }
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
