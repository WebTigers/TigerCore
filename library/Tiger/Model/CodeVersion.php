<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Model_CodeVersion — immutable snapshots of `code` rows (see Tiger_Model_Code).
 *
 * Every save snapshots here so an executable-code change is diffable, restorable, and
 * audited — same pattern as page_version. @api
 */
class Tiger_Model_CodeVersion extends Tiger_Model_Table
{
    protected $_name    = 'code_version';
    protected $_primary = 'code_version_id';

    /**
     * Compute the next version number for a code row (max existing + 1).
     *
     * @param  string $codeId the code_id
     * @return int    the next version number
     */
    public function nextVersion($codeId)
    {
        $db  = $this->getAdapter();
        $max = (int) $db->fetchOne(
            $db->select()->from($this->_name, ['m' => new Zend_Db_Expr('MAX(version)')])
                ->where('code_id = ?', (string) $codeId)
        );
        return $max + 1;
    }

    /**
     * Snapshot a code row as a new version.
     *
     * @param  string $codeId the code_id
     * @param  array  $f      the snapshotted fields (name, language, code, run_location,
     *                        auto_insert, priority, active, status)
     * @return int    the new version number
     */
    public function snapshot($codeId, array $f)
    {
        $version = $this->nextVersion($codeId);
        $this->insert([
            'code_id'      => (string) $codeId,
            'version'      => $version,
            'name'         => $f['name']         ?? null,
            'language'     => $f['language']     ?? null,
            'code'         => $f['code']         ?? null,
            'run_location' => $f['run_location'] ?? null,
            'auto_insert'  => $f['auto_insert']  ?? null,
            'priority'     => $f['priority']     ?? null,
            'active'       => $f['active']       ?? null,
            'status'       => $f['status']       ?? null,
        ]);
        return $version;
    }

    /**
     * Fetch a code row's versions, newest first.
     *
     * @param  string $codeId the code_id
     * @param  int    $limit  max versions to return
     * @return Zend_Db_Table_Rowset_Abstract the version rows
     */
    public function recentFor($codeId, $limit = 50)
    {
        return $this->fetchAll(
            $this->select()->where('code_id = ?', (string) $codeId)->order('version DESC')->limit((int) $limit)
        );
    }

    /**
     * Fetch one version of a code row, or null.
     *
     * @param  string $codeId  the code_id
     * @param  int    $version the version number
     * @return Zend_Db_Table_Row_Abstract|null the version row, or null
     */
    public function get($codeId, $version)
    {
        return $this->fetchRow(
            $this->select()->where('code_id = ?', (string) $codeId)->where('version = ?', (int) $version)
        );
    }
}
