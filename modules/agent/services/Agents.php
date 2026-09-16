<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Agent_Service_Agents — CRUD for the agent registry (TIGER-151), the /api service behind the
 * multi-agent settings screen. An org registers many named agents, each with its own persona,
 * provider, model and BYO key; exactly one is the default the platform resolves to (Tiger_Agent).
 *
 * The API key is write-only, exactly as the single-agent form was: it is encrypted with
 * Tiger_Crypto and NEVER round-trips to the browser — list() reports a "connected" flag, not the
 * key, and a blank key field on save keeps the stored secret. BYO is deliberate (TIGERAGENT.md §9).
 *
 * The install-wide auto-mode ceiling (mode_max) stays on Agent_Service_Settings — it is governance,
 * not a per-agent property.
 *
 * @api
 */
class Agent_Service_Agents extends Tiger_Service_Service
{
    /** The org scope for the current caller ('' = platform/global). */
    private function _scope(): string
    {
        return (string) ($this->_org_id ?? '');
    }

    /**
     * List the org's agents for the card view — default first. Never returns keys; each row carries
     * a `connected` flag (a key is stored) instead.
     *
     * @param  array $params
     * @return void
     */
    public function list(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $agents = [];
        foreach ((new Tiger_Model_Agent())->allForOrg($this->_scope()) as $row) {
            $agents[] = $this->_public($row);
        }
        $this->_success(['agents' => $agents, 'crypto' => Tiger_Crypto::isConfigured()], 'core.api.success');
    }

    /**
     * Create or update one agent. `agent_id` blank = create. A blank `api_key` preserves the stored
     * secret; a non-blank one is encrypted and replaces it. An org always keeps exactly one default:
     * the first agent created becomes it automatically, and setting `is_default` moves it.
     *
     * @param  array $params agent_id, name, persona, provider, model, api_key, enabled, is_default
     * @return void
     */
    public function save(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $form = new Agent_Form_Agent();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }

        $scope = $this->_scope();
        $model = new Tiger_Model_Agent();

        $agentId = (string) $form->getValue('agent_id');
        $existing = $agentId !== '' ? $model->findForOrg($scope, $agentId) : null;
        if ($agentId !== '' && $existing === null) { $this->_error('agent.agents.error.not_found'); return; }

        $provider = (string) $form->getValue('provider');
        if (!array_key_exists($provider, Tiger_Agent_Provider_Factory::options())) { $provider = 'anthropic'; }

        $name    = (string) $form->getValue('name');
        $persona = (string) $form->getValue('persona');
        $mdl     = (string) $form->getValue('model');
        $key     = (string) $form->getValue('api_key');
        $enabled = !empty($params['enabled']) ? 1 : 0;
        $wantDefault = !empty($params['is_default']);

        if ($key !== '' && !Tiger_Crypto::isConfigured()) {
            $this->_error('agent.agents.error.crypto'); return;
        }

        try {
            $savedId = $this->_transaction(function () use ($model, $scope, $existing, $agentId, $name, $persona, $provider, $mdl, $key, $enabled, $wantDefault) {
                // An org with no agent yet MUST end with a default, so the facade resolves a row.
                $isFirst   = $model->allForOrg($scope)->count() === 0;
                $makeDefault = $wantDefault || $isFirst;

                $data = [
                    'name'     => $name,
                    'persona'  => $persona !== '' ? $persona : null,
                    'provider' => $provider,
                    'model'    => $mdl,
                    'enabled'  => $enabled,
                ];
                if ($key !== '') { $data['api_key_enc'] = Tiger_Crypto::encrypt($key); }

                if ($existing !== null) {
                    $model->update($data, ['agent_id = ?' => $agentId, 'org_id = ?' => $scope]);
                    $id = $agentId;
                } else {
                    $data['org_id']     = $scope;
                    $data['is_default'] = $makeDefault ? 1 : 0;
                    $id = $model->insert($data);
                }

                if ($makeDefault) { $model->setDefault($scope, $id); }
                return $id;
            });

            Tiger_Agent::reset();
            $saved = $model->findForOrg($scope, $savedId);
            $this->_success(['agent' => $this->_public($saved)], 'agent.agents.saved');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Soft-delete an agent. If the deleted one was the default and others remain, the oldest
     * surviving agent is promoted so the org never loses its default. Deleting the last agent is
     * allowed — the registry falls back to the legacy config until one is created again.
     *
     * @param  array $params agent_id
     * @return void
     */
    public function delete(array $params): void
    {
        if (!$this->_isAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $scope   = $this->_scope();
        $model   = new Tiger_Model_Agent();
        $agentId = (string) ($params['agent_id'] ?? '');
        $row     = $agentId !== '' ? $model->findForOrg($scope, $agentId) : null;
        if ($row === null) { $this->_error('agent.agents.error.not_found'); return; }

        $wasDefault = (int) $row->is_default === 1;

        try {
            $this->_transaction(function () use ($model, $scope, $agentId, $wasDefault) {
                $model->softDelete(['agent_id = ?' => $agentId, 'org_id = ?' => $scope]);
                if ($wasDefault) {
                    $next = $model->allForOrg($scope)->current();   // oldest survivor (default-first, then created_at)
                    if ($next) { $model->setDefault($scope, $next->agent_id); }
                }
            });
            Tiger_Agent::reset();
            $this->_success(null, 'agent.agents.deleted');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * The browser-safe view of an agent row: identity + config + a connected flag, never the key.
     *
     * @param  Zend_Db_Table_Row_Abstract $row
     * @return array
     */
    private function _public($row): array
    {
        return [
            'agent_id'   => (string) $row->agent_id,
            'name'       => (string) $row->name,
            'persona'    => (string) ($row->persona ?? ''),
            'provider'   => (string) $row->provider,
            'model'      => (string) $row->model,
            'enabled'    => (int) $row->enabled === 1,
            'is_default' => (int) $row->is_default === 1,
            'connected'  => (string) ($row->api_key_enc ?? '') !== '',
        ];
    }
}
