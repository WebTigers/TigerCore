<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Backup_Form_Settings — the scheduled-backup settings (Backup_Service_Backup::saveSettings, TIGER-528).
 *
 * `notify_email` is validated HERE, as a field: a comma-separated list where every non-blank entry
 * must be an email address — the same rule the service used to hand-check with `Zend_Validate::is`,
 * now a per-field error (`res.form.notify_email`, message `backup.bad_email`). Blank is allowed (no
 * notice recipients). The other fields keep the service's own normalization and refusals
 * (`backup.bad_disk`, `backup.secrets.not_allowed`).
 *
 * @api
 */
class Backup_Form_Settings extends Backup_Form_Write
{
    /**
     * The declared schema.
     *
     * @return array<int,array> [type, name, options] triples
     */
    protected function elements(): array
    {
        return [
            $this->_field('components'),
            $this->_field('disk'),
            $this->_field('include_secrets'),
            $this->_field('retention_max'),
            $this->_field('notify_enabled'),
            ['text', 'notify_email', [
                'required'   => false,
                // No StringTrim: a param can arrive as an array, and the validator trims each entry itself.
                'filters'    => [],
                'validators' => [
                    ['Callback', true, [
                        'callback' => [self::class, 'isEmailList'],
                        'messages' => [Zend_Validate_Callback::INVALID_VALUE => 'backup.bad_email'],
                    ]],
                ],
            ]],
        ];
    }

    /**
     * Is this a comma-separated list of email addresses? Blank entries are ignored; anything that is
     * not a string is refused.
     *
     * @param  mixed $value the submitted notify_email
     * @return bool
     */
    public static function isEmailList($value): bool
    {
        if (!is_scalar($value)) { return false; }
        foreach (array_map('trim', explode(',', trim((string) $value))) as $addr) {
            if ($addr !== '' && !Zend_Validate::is($addr, 'EmailAddress')) { return false; }
        }
        return true;
    }
}
