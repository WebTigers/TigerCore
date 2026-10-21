<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Model_ModuleRelease — a module release moving through QA (TIGER-334, migration 0054).
 *
 * A row is created on `git.release.submit` (status `submitted`) and moves to `approved` or `rejected`
 * by the QA pipeline. A submitted tag is immutable — a rejection is fixed by a NEW tag, never by moving
 * this one. Named finders only.
 *
 * @api
 * @since 1.38.0
 */
class Tiger_Model_ModuleRelease extends Tiger_Model_Table
{
    protected $_name    = 'module_release';
    protected $_primary = 'module_release_id';

    const STATUS_SUBMITTED = 'submitted';
    const STATUS_IN_QA     = 'in_qa';
    const STATUS_APPROVED  = 'approved';
    const STATUS_REJECTED  = 'rejected';
    const STATUS_WITHDRAWN = 'withdrawn';

    /** Approved releases for a module, newest first — what a consumer may install. */
    public function approvedForModule($orgId, $moduleKey)
    {
        return $this->fetchAll(
            $this->activeSelect()
                ->where('org_id = ?', (string) $orgId)
                ->where('module_key = ?', (string) $moduleKey)
                ->where('status = ?', self::STATUS_APPROVED)
                ->order('created_at DESC')
        );
    }

    /** Every active release for a module, newest first (the release list — any status). */
    public function forModule($orgId, $moduleKey)
    {
        return $this->fetchAll(
            $this->activeSelect()
                ->where('org_id = ?', (string) $orgId)
                ->where('module_key = ?', (string) $moduleKey)
                ->order('created_at DESC')
        );
    }

    /** A specific release by tag (to check status / prevent tag reuse), or null. */
    public function byTag($orgId, $moduleKey, $tag)
    {
        return $this->fetchRow(
            $this->activeSelect()
                ->where('org_id = ?', (string) $orgId)
                ->where('module_key = ?', (string) $moduleKey)
                ->where('git_tag = ?', (string) $tag)
        );
    }
}
