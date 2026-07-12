<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Model_UpdateHistory — the durable log of one-click update runs (table `update_history`).
 *
 * `record()` writes one row per applied item (the base stamps `update_id`, `created_by`, `created_at`);
 * `recent()` reads them back for the Updates screen's history panel, decoding the JSON step log. See
 * `System_Service_Updates`.
 *
 * @api
 */
class Tiger_Model_UpdateHistory extends Tiger_Model_Table
{
    protected $_name    = 'update_history';
    protected $_primary = 'update_id';

    /** Valid outcomes. */
    const OUTCOME_SUCCESS  = 'success';
    const OUTCOME_FAILED   = 'failed';
    const OUTCOME_ROLLBACK = 'rolled_back';
    const OUTCOME_ADVISORY = 'advisory';

    /**
     * Record one applied item. The base mints the UUID + actor + timestamp.
     *
     * @param  array $data {item_type, item_slug, item_name, from_version, to_version, outcome, log:array}
     * @return string the update_id
     */
    public function record(array $data)
    {
        return $this->insert([
            'item_type'    => (string) ($data['item_type'] ?? 'module'),
            'item_slug'    => (string) ($data['item_slug'] ?? ''),
            'item_name'    => $data['item_name']    ?? null,
            'from_version' => $data['from_version'] ?? null,
            'to_version'   => $data['to_version']   ?? null,
            'outcome'      => (string) ($data['outcome'] ?? self::OUTCOME_FAILED),
            'log'          => isset($data['log']) ? json_encode($data['log'], JSON_UNESCAPED_SLASHES) : null,
        ]);
    }

    /**
     * The most recent update runs (newest first), with the step log decoded.
     *
     * @param  int $limit
     * @return array<int,array>
     */
    public function recent($limit = 20)
    {
        $rows = $this->activeSelect()->order('created_at DESC')->limit(max(1, (int) $limit))->query()->fetchAll();
        foreach ($rows as &$r) {
            $r['log'] = isset($r['log']) && $r['log'] !== null ? (json_decode((string) $r['log'], true) ?: []) : [];
        }
        return $rows;
    }
}
