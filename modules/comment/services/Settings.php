<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Comment_Service_Settings — the module's admin settings, written to the live config tier.
 *
 * Only the AI spam toggle for now, and it is REFUSED when no agent is connected: an install can't
 * switch on a check that has nothing to run it, because a stored `1` with no agent looks like a
 * working filter and silently isn't. The screen hides the control in the same case.
 */
class Comment_Service_Settings extends Tiger_Service_Service
{
    /**
     * Save the module settings.
     *
     * @param  array $params `spam_agent` (0|1)
     * @return void
     */
    public function save(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $wanted = !empty($params['spam_agent']);

        if ($wanted && !Tiger_Comment_Spam::agentAvailable()) {
            // Refuse rather than store an aspiration — see the class docblock.
            $this->_error('comment.admin.no_agent');
            return;
        }

        try {
            $this->_transaction(function () use ($wanted) {
                (new Tiger_Model_Config())->set('global', '', Tiger_Comment_Spam::CONFIG_AGENT, $wanted ? '1' : '0');
            });
            $this->_success(['spam_agent' => $wanted], 'comment.admin.saved');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }
}
