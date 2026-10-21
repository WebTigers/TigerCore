<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Model_GitCredential — a least-privilege, encrypted-at-rest credential (TIGER-334, migration 0054).
 *
 * `ciphertext` is a Tiger_Crypto blob (a deploy key or a fine-grained token); only the public key and
 * a fingerprint are ever readable. This model exposes NO finder that returns the secret — resolution
 * lives in Tiger_Git_Credential, which decrypts at the moment of use and never hands it back.
 * Named finders only.
 *
 * @api
 * @since 1.38.0
 */
class Tiger_Model_GitCredential extends Tiger_Model_Table
{
    protected $_name    = 'git_credential';
    protected $_primary = 'git_credential_id';

    const TYPE_DEPLOY_KEY = 'deploy_key';
    const TYPE_TOKEN      = 'token';

    /** The active, non-revoked credential for a module, or null. */
    public function forModule($orgId, $moduleKey)
    {
        return $this->fetchRow(
            $this->activeSelect()
                ->where('org_id = ?', (string) $orgId)
                ->where('module_key = ?', (string) $moduleKey)
                ->where('revoked_at IS NULL')
                ->order('created_at DESC')
        );
    }

    /**
     * The public, SAFE view of a credential row — never the ciphertext. What any surface may show.
     *
     * @param  Zend_Db_Table_Row_Abstract $row
     * @return array{id:string, type:string, fingerprint:?string, public_material:?string, scopes:?string, rotated_at:?string, revoked_at:?string}
     */
    public static function publicView($row): array
    {
        return [
            'id'              => (string) $row->git_credential_id,
            'type'            => (string) $row->type,
            'fingerprint'     => $row->fingerprint !== null ? (string) $row->fingerprint : null,
            'public_material' => $row->public_material !== null ? (string) $row->public_material : null,
            'scopes'          => $row->scopes !== null ? (string) $row->scopes : null,
            'rotated_at'      => $row->rotated_at !== null ? (string) $row->rotated_at : null,
            'revoked_at'      => $row->revoked_at !== null ? (string) $row->revoked_at : null,
        ];
    }
}
