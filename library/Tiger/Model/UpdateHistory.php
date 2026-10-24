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
    /**
     * DataTables data for the Updates history grid: search, sort and paging over the
     * durable update log. Owns the query; the service handles presentation + ACL
     * (AGENTS.md — a service never builds a predicate). Mirrors
     * `Tiger_Model_Page::datatable()`, which is the reference implementation.
     *
     * @param  array{search?:string,orderCol?:int,orderDir?:string,offset?:int,limit?:int} $opts
     * @return array{total:int,filtered:int,rows:array}
     */
    public function datatable(array $opts)
    {
        $db     = $this->getAdapter();
        $search = (string) ($opts['search'] ?? '');
        $limit  = max(1, (int) ($opts['limit'] ?? 25));
        $offset = max(0, (int) ($opts['offset'] ?? 0));

        // Sortable columns (grid index -> SQL). The log column is not sortable.
        $orderCols = [0 => 'item_name', 1 => 'item_type', 2 => 'created_at', 3 => 'to_version', 4 => 'outcome'];
        $col = (int) ($opts['orderCol'] ?? -1);
        $dir = (strtoupper((string) ($opts['orderDir'] ?? '')) === 'ASC') ? 'ASC' : 'DESC';
        $orderSql = isset($orderCols[$col]) ? ($orderCols[$col] . ' ' . $dir) : 'created_at DESC';

        $scope = function ($sel) { $sel->where('deleted = 0'); };
        $searchFn = function ($sel) use ($db, $search) {
            if ($search === '') { return; }
            $like  = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $search) . '%';
            $parts = [];
            foreach (['item_name', 'item_slug', 'item_type', 'from_version', 'to_version', 'outcome'] as $c) {
                $parts[] = $db->quoteInto("$c LIKE ?", $like);
            }
            $sel->where('(' . implode(' OR ', $parts) . ')');
        };

        $totalSel = $db->select()->from($this->_name, ['c' => new Zend_Db_Expr('COUNT(*)')]);
        $scope($totalSel);
        $total = (int) $db->fetchOne($totalSel);

        $filteredSel = $db->select()->from($this->_name, ['c' => new Zend_Db_Expr('COUNT(*)')]);
        $scope($filteredSel); $searchFn($filteredSel);
        $filtered = (int) $db->fetchOne($filteredSel);

        $pageSel = $db->select()
            ->from($this->_name, ['update_id', 'item_type', 'item_slug', 'item_name',
                                  'from_version', 'to_version', 'outcome', 'log', 'created_at'])
            ->order(new Zend_Db_Expr($orderSql))
            ->limit($limit, $offset);
        $scope($pageSel); $searchFn($pageSel);

        $rows = $db->fetchAll($pageSel);
        foreach ($rows as &$r) {
            $r['log'] = ($r['log'] !== null && $r['log'] !== '') ? (json_decode((string) $r['log'], true) ?: []) : [];
        }
        unset($r);

        return ['total' => $total, 'filtered' => $filtered, 'rows' => $rows];
    }

    public function recent($limit = 20)
    {
        $rows = $this->activeSelect()->order('created_at DESC')->limit(max(1, (int) $limit))->query()->fetchAll();
        foreach ($rows as &$r) {
            $r['log'] = isset($r['log']) && $r['log'] !== null ? (json_decode((string) $r['log'], true) ?: []) : [];
        }
        return $rows;
    }
}
