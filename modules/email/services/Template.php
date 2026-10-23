<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Email_Service_Template — /api service for the transactional-email authoring screen (TIGER-267).
 *
 * The list reads the registered email events (Tiger_Mail::registeredTemplates) and annotates each with
 * whether an override is authored; the editor reads/writes an authored subject + HTML body to the
 * `email_template` DB-override tier (Tiger_Model_EmailTemplate) — the live-override pattern, no deploy.
 * Preview renders exactly what a send would produce (Tiger_Mail::renderTemplate), including an unsaved
 * draft. ACL: admin+ (modules/email/configs/acl.ini); every method re-guards with _isAllowed (TIGER-335).
 *
 * @api
 */
class Email_Service_Template extends Tiger_Service_Service
{
    /** v1 authors the global/platform override; per-org scoping reuses the same store (Tiger_Model_EmailTemplate). */
    const SCOPE = '';

    /**
     * The list of registered email events (DataTables) — each annotated with its override state.
     *
     * @param  array $params the DataTables request
     * @return void
     */
    public function datatable(array $params): void
    {
        if (!$this->_isAllowed('datatable')) { $this->_error('core.api.error.not_allowed'); return; }

        $dt    = $this->_dtParams($params);
        $t     = Zend_Registry::isRegistered('Zend_Translate') ? Zend_Registry::get('Zend_Translate') : null;
        $model = new Tiger_Model_EmailTemplate();

        $canEdit = $this->_isAllowed('save');
        $rows    = [];
        foreach (Tiger_Mail::registeredTemplates() as $def) {
            $custom = (bool) $model->byKey($def['key'], self::SCOPE);
            $rows[] = [
                'key'        => $def['key'],
                'label'      => $t ? (string) $t->translate($def['label']) : $def['label'],
                'group'      => $def['group'],
                'customized' => $custom,
                'can_edit'   => $canEdit,
                'can_reset'  => $canEdit && $custom,
            ];
        }

        $total = count($rows);

        // Search spans the visible text (label/key/group); filter before sort+paginate.
        $search = trim((string) ($dt['search'] ?? ''));
        if ($search !== '') {
            $needle = mb_strtolower($search);
            $rows = array_values(array_filter($rows, static function ($r) use ($needle) {
                return mb_strpos(mb_strtolower($r['label'] . ' ' . $r['key'] . ' ' . $r['group']), $needle) !== false;
            }));
        }
        $filtered = count($rows);

        // Sort by the requested column (0=label,1=key,2=group,3=status), default label asc.
        $cols = ['label', 'key', 'group', 'customized'];
        $ci   = isset($dt['order'][0]['column']) ? (int) $dt['order'][0]['column'] : 0;
        $dir  = (isset($dt['order'][0]['dir']) && $dt['order'][0]['dir'] === 'desc') ? -1 : 1;
        $col  = $cols[$ci] ?? 'label';
        usort($rows, static function ($a, $b) use ($col, $dir) {
            return $dir * strcmp((string) $a[$col], (string) $b[$col]);
        });

        $rows = array_slice($rows, (int) $dt['start'], (int) $dt['length'] ?: $filtered);

        $this->_dtResponse($dt['draw'], $total, $filtered, $rows);
    }

    /**
     * The registry def (label/description/vars) + the stored override (or empty) for a key.
     *
     * @param  array $params {key}
     * @return void
     */
    public function get(array $params): void
    {
        if (!$this->_isAllowed('get')) { $this->_error('core.api.error.not_allowed'); return; }

        $key  = (string) ($params['key'] ?? '');
        $defs = Tiger_Mail::registeredTemplates();
        if ($key === '' || !isset($defs[$key])) { $this->_error('email.error.unknown_template'); return; }

        $row = (new Tiger_Model_EmailTemplate())->byKey($key, self::SCOPE);
        $this->_success([
            'key'        => $key,
            'def'        => $defs[$key],
            'subject'    => $row ? (string) $row->subject : '',
            'body'       => $row ? (string) $row->body : '',
            'customized' => (bool) $row,
        ]);
    }

    /**
     * Save (upsert) an authored override.
     *
     * @param  array $params {template_key, subject, body, scope?}
     * @return void
     */
    public function save(array $params): void
    {
        if (!$this->_isAllowed('save')) { $this->_error('core.api.error.not_allowed'); return; }

        $form = new Email_Form_Template();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        $v = $form->getValues();

        $key = (string) $v['template_key'];
        if (!isset(Tiger_Mail::registeredTemplates()[$key])) { $this->_error('email.error.unknown_template'); return; }

        try {
            $this->_transaction(function () use ($key, $v) {
                (new Tiger_Model_EmailTemplate())->store(
                    $key, self::SCOPE, (string) $v['subject'], (string) $v['body']
                );
            });
            $this->_success(['key' => $key, 'customized' => true], 'email.saved');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Remove an override so the code-shipped file default renders again.
     *
     * @param  array $params {key}
     * @return void
     */
    public function reset(array $params): void
    {
        if (!$this->_isAllowed('reset')) { $this->_error('core.api.error.not_allowed'); return; }

        $key = (string) ($params['key'] ?? '');
        if (!isset(Tiger_Mail::registeredTemplates()[$key])) { $this->_error('email.error.unknown_template'); return; }

        try {
            $this->_transaction(function () use ($key) {
                (new Tiger_Model_EmailTemplate())->reset($key, self::SCOPE);
            });
            $this->_success(['key' => $key, 'customized' => false], 'email.reset');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Render a preview of the current draft (subject + body) with sample variable values.
     *
     * @param  array $params {key, subject, body}
     * @return void
     */
    public function preview(array $params): void
    {
        if (!$this->_isAllowed('preview')) { $this->_error('core.api.error.not_allowed'); return; }

        $key  = (string) ($params['key'] ?? '');
        $defs = Tiger_Mail::registeredTemplates();
        if ($key === '' || !isset($defs[$key])) { $this->_error('email.error.unknown_template'); return; }

        $subject = (string) ($params['subject'] ?? '');
        $body    = (string) ($params['body'] ?? '');
        $vars    = $this->_sampleVars($defs[$key]);

        try {
            $out = Tiger_Mail::renderTemplate($key, $vars, self::SCOPE, $subject, $body);
            $this->_success(['subject' => $out['subject'], 'html' => $out['html']]);
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Illustrative sample values for a template's declared placeholders, for the preview.
     *
     * @param  array $def the registry def (its `vars` names the placeholders)
     * @return array<string,string> placeholder name => sample value
     */
    protected function _sampleVars(array $def): array
    {
        $out = [];
        foreach (array_keys($def['vars'] ?? []) as $name) {
            $lower = strtolower((string) $name);
            if ($lower === 'code') {
                $out[$name] = '123456';
            } elseif (strpos($lower, 'url') !== false) {
                $out[$name] = 'https://example.com/' . $lower . '/abc123';
            } elseif ($lower === 'domain') {
                $out[$name] = 'example.com';
            } elseif ($lower === 'username') {
                $out[$name] = 'sampleuser';
            } else {
                $out[$name] = '[' . $name . ']';
            }
        }
        return $out;
    }
}
