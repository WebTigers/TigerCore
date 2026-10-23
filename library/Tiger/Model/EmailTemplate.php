<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * EmailTemplate — the DB-override tier for Tiger_Mail's transactional templates (see migration 0054).
 *
 * An active row OVERRIDES the code-shipped `emails/<template_key>.phtml` default: an owner- or
 * agent-authored `subject` + HTML `body` carrying `{{var}}` placeholders, which Tiger_Mail::template()
 * substitutes and wraps in the shared branded layout. This is the live-override pattern applied to mail
 * (file = base, this table = the override, last wins), the sibling of Tiger_Model_Config /
 * Tiger_Model_Translation. Scoped by `org_id` ('' = the global/platform default, else per-tenant), so
 * resolve() prefers an org row over the global one, exactly like the config cascade.
 *
 * @api
 */
class Tiger_Model_EmailTemplate extends Tiger_Model_Table
{
    protected $_name    = 'email_template';
    protected $_primary = 'email_template_id';

    /** The global/platform scope (every install's baseline override, no tenant). */
    const SCOPE_GLOBAL = '';

    /**
     * Resolve the active override for a key, org row winning over the global one (the config cascade).
     *
     * @param  string $key   the template key (matches the file template name, e.g. `reset`)
     * @param  string $orgId the requesting org ('' to consider only the global override)
     * @return Zend_Db_Table_Row_Abstract|null the winning override row, or null when none is authored
     */
    public function resolve($key, $orgId = '')
    {
        $orgId = (string) $orgId;
        if ($orgId !== '') {
            $row = $this->fetchRow(
                $this->activeSelect()
                    ->where('template_key = ?', (string) $key)
                    ->where('org_id = ?', $orgId)
            );
            if ($row) { return $row; }
        }
        return $this->fetchRow(
            $this->activeSelect()
                ->where('template_key = ?', (string) $key)
                ->where('org_id = ?', self::SCOPE_GLOBAL)
        );
    }

    /**
     * The active override for an exact (key, scope), without the global fallback — the editor's own row.
     *
     * @param  string $key   the template key
     * @param  string $orgId the scope ('' = global)
     * @return Zend_Db_Table_Row_Abstract|null the row, or null when that scope has no override
     */
    public function byKey($key, $orgId = '')
    {
        return $this->fetchRow(
            $this->activeSelect()
                ->where('template_key = ?', (string) $key)
                ->where('org_id = ?', (string) $orgId)
        );
    }

    /**
     * Upsert an override (revives a soft-deleted row for the same key+scope so the unique index holds).
     *
     * @param  string $key     the template key
     * @param  string $orgId   the scope ('' = global)
     * @param  string $subject the subject line (may carry `{{var}}` placeholders)
     * @param  string $body    the HTML body (may carry `{{var}}` placeholders)
     * @return string          the email_template_id
     */
    public function store($key, $orgId, $subject, $body)
    {
        // Match ANY row (incl. soft-deleted) for this key+scope so a prior reset doesn't collide on the
        // (org_id, template_key) unique index — revive + overwrite it instead of inserting a duplicate.
        $row = $this->fetchRow(
            $this->select()
                ->where('template_key = ?', (string) $key)
                ->where('org_id = ?', (string) $orgId)
                ->order('created_at DESC')
        );
        $data = [
            'template_key' => (string) $key,
            'org_id'       => (string) $orgId,
            'subject'      => (string) $subject,
            'body'         => (string) $body,
            'status'       => 'active',
            'deleted'      => 0,
        ];
        if ($row) {
            // Revive + overwrite the existing row (its key is reused, not freed — see migration 0054);
            // the model's update() re-stamps updated_at/updated_by. softDelete/update take a WHERE clause.
            $this->update($data, $this->getAdapter()->quoteInto('email_template_id = ?', $row->email_template_id));
            return (string) $row->email_template_id;
        }
        return (string) $this->insert($data);
    }

    /**
     * Remove the override for a key+scope (soft-delete) so the file default renders again.
     *
     * @param  string $key   the template key
     * @param  string $orgId the scope ('' = global)
     * @return bool          true when an override was present and removed
     */
    public function reset($key, $orgId = '')
    {
        $row = $this->byKey($key, $orgId);
        if (!$row) { return false; }
        // softDelete() takes a WHERE clause, not an id — a bare UUID would be nonsense SQL.
        $this->softDelete($this->getAdapter()->quoteInto('email_template_id = ?', $row->email_template_id));
        return true;
    }
}
