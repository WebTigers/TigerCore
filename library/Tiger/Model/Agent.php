<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Model_Agent — the agent registry table gateway (migration 0050, TIGER-151).
 *
 * One row per registered agent, scoped by `org_id` ('' = the platform/global scope). Exactly one
 * agent per scope carries `is_default = 1`; `Tiger_Agent::default()` resolves to it. Domain finders
 * build on activeSelect() so soft-deleted agents stay hidden.
 *
 * The API key lives in `api_key_enc` (Tiger_Crypto ciphertext); this gateway never encrypts or
 * decrypts — that is the service's job. save()/setDefault() keep the "one default per scope"
 * invariant in a transaction.
 *
 * @api
 * @since 1.9.0
 */
class Tiger_Model_Agent extends Tiger_Model_Table
{
    protected $_name    = 'agent';
    protected $_primary = 'agent_id';

    /**
     * The default agent for a scope: the org's own default if it has one, else the global ('')
     * default, else null. Falling back to the global scope means a tenant that never registered
     * its own agent still resolves to the platform default (which is what the legacy singleton was).
     *
     * @param  string $orgId the acting org, '' for the platform scope
     * @return Zend_Db_Table_Row_Abstract|null
     */
    public function defaultForOrg($orgId)
    {
        $orgId = (string) $orgId;
        $row = $this->fetchRow(
            $this->activeSelect()
                ->where('org_id = ?', $orgId)
                ->where('is_default = ?', 1)
                ->order('created_at ASC')
        );
        if ($row === null && $orgId !== '') {
            $row = $this->fetchRow(
                $this->activeSelect()
                    ->where("org_id = ''")
                    ->where('is_default = ?', 1)
                    ->order('created_at ASC')
            );
        }
        return $row;
    }

    /**
     * Every non-deleted agent in a scope, default first then newest.
     *
     * @param  string $orgId
     * @return Zend_Db_Table_Rowset_Abstract
     */
    public function allForOrg($orgId)
    {
        return $this->fetchAll(
            $this->activeSelect()
                ->where('org_id = ?', (string) $orgId)
                ->order(['is_default DESC', 'created_at ASC'])
        );
    }

    /**
     * One agent by id within a scope (so an org can't address another org's agent), or null.
     *
     * @param  string $orgId
     * @param  string $agentId
     * @return Zend_Db_Table_Row_Abstract|null
     */
    public function findForOrg($orgId, $agentId)
    {
        if ((string) $agentId === '') { return null; }
        return $this->fetchRow(
            $this->activeSelect()
                ->where('org_id = ?', (string) $orgId)
                ->where('agent_id = ?', (string) $agentId)
        );
    }

    /**
     * Make one agent the sole default in its scope, clearing the flag on every sibling. Runs in a
     * transaction so a scope never has two defaults or, briefly, none.
     *
     * @param  string $orgId
     * @param  string $agentId
     * @return void
     */
    public function setDefault($orgId, $agentId)
    {
        $db = $this->getAdapter();
        $db->beginTransaction();
        try {
            $this->update(['is_default' => 0], [
                'org_id = ?'      => (string) $orgId,
                'agent_id <> ?'   => (string) $agentId,
            ]);
            $this->update(['is_default' => 1], [
                'org_id = ?'   => (string) $orgId,
                'agent_id = ?' => (string) $agentId,
            ]);
            $db->commit();
        } catch (Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
