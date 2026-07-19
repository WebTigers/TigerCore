<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Model_AgentConversation — a TigerAgent chat thread (see migration 0034).
 *
 * Org-scoped and user-owned: a conversation belongs to the person who started it, and the
 * agent's capability inside it derives entirely from that user's role (TIGERAGENT.md §0).
 * The model in `library/` (not the module) because the agent Loop/Forge are platform
 * substrate that consume it — the same placement rule as `Tiger_Model_Code` (ARCHITECTURE
 * §3a).
 *
 * @api
 */
class Tiger_Model_AgentConversation extends Tiger_Model_Table
{
    protected $_name    = 'agent_conversation';
    protected $_primary = 'conversation_id';

    /**
     * The most recent conversations owned by a user in an org (newest first).
     *
     * @param  string $orgId  the tenant
     * @param  string $userId the owner
     * @param  int    $limit  max threads
     * @return array          plain rows (assoc), newest first
     */
    public function recentForUser($orgId, $userId, $limit = 20)
    {
        $select = $this->activeSelect()
            ->where('user_id = ?', (string) $userId)
            ->order('updated_at DESC')
            ->order('created_at DESC')
            ->limit(max(1, (int) $limit));
        if ($orgId !== null && $orgId !== '') {
            $select->where('org_id = ?', (string) $orgId);
        }
        return $this->fetchAll($select)->toArray();
    }

    /**
     * Load one conversation the user owns, or null (owner-scoped so a thread can't be
     * read across accounts).
     *
     * @param  string $id     the conversation_id
     * @param  string $userId the requesting owner
     * @return Zend_Db_Table_Row_Abstract|null
     */
    public function ownedById($id, $userId)
    {
        return $this->fetchRow(
            $this->activeSelect()
                ->where('conversation_id = ?', (string) $id)
                ->where('user_id = ?', (string) $userId)
        );
    }

    /**
     * Start a conversation for a user and return its id.
     *
     * @param  string $orgId    the tenant
     * @param  string $userId   the owner
     * @param  string $title    a short label (derived from the first message)
     * @param  string $provider the AI provider key
     * @param  string $model    the model id
     * @return string           the new conversation_id
     */
    public function start($orgId, $userId, $title, $provider, $model)
    {
        return $this->insert([
            'org_id'   => (string) $orgId,
            'user_id'  => (string) $userId,
            'title'    => mb_substr((string) $title, 0, 191),
            'provider' => (string) $provider,
            'model'    => (string) $model,
        ]);
    }
}
