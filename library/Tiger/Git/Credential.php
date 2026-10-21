<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Git_Credential — the write-only credential manager (TIGER-334 / TIGER-365).
 *
 * The one place a git credential (a fine-grained token or a deploy key) is written, resolved for use,
 * rotated, and revoked. The security contract:
 *   - Secrets are encrypted at rest with Tiger_Crypto (libsodium secretbox; the key lives in local.ini,
 *     never the DB). The connection stores only a `credential_ref`, never the secret.
 *   - WRITE-ONLY on every surface. `store()`, `rotate()` and `revoke()` return a SAFE public view
 *     (id, type, fingerprint, public key, scopes) and NOTHING sensitive. There is no public getter for
 *     a secret; `resolve()` is the single read path, it returns the plaintext to a TRANSPORT at the
 *     moment of use, and nothing logs or echoes it. A controller never calls `resolve()`.
 *   - A reference may be a local `git_credential` id OR a `Tiger_Secret` ref (e.g. an SSM parameter) —
 *     the same provider-neutral indirection the fleet already uses — so a shared secret need not be
 *     copied into every install's DB.
 *   - Rotation verifies the NEW credential before the OLD one is revoked (R2), so a bad rotation can't
 *     lock the owner out.
 *
 * @api
 * @since 1.38.0
 */
class Tiger_Git_Credential
{
    /** A Tiger_Secret ref looks like `<scheme>://…`; anything else is a local git_credential id. */
    private static function isSecretRef(string $ref): bool
    {
        return (bool) preg_match('~^[a-z][a-z0-9+.-]*://~', $ref);
    }

    /**
     * Store a new credential, encrypted. Returns the SAFE public view (NO secret). The caller then
     * records the returned `ref` on the connection.
     *
     * @param  string $orgId
     * @param  string $moduleKey
     * @param  string $type       Tiger_Model_GitCredential::TYPE_TOKEN|TYPE_DEPLOY_KEY
     * @param  string $secret     the plaintext token or private key (consumed, never returned)
     * @param  array  $meta       public_material?, fingerprint?, scopes?
     * @return array{ref:string, public:array}
     */
    public function store(string $orgId, string $moduleKey, string $type, string $secret, array $meta = []): array
    {
        if ($secret === '') {
            throw new Tiger_Git_Exception(Tiger_Git_Exception::CREDENTIAL_MISSING, 'a credential value is required');
        }
        if (!Tiger_Crypto::isConfigured()) {
            throw new Tiger_Git_Exception(Tiger_Git_Exception::CREDENTIAL_INVALID, 'encryption is not configured on this install');
        }
        $model = new Tiger_Model_GitCredential();
        $id = (string) $model->insert([
            'org_id'          => $orgId,
            'module_key'      => $moduleKey,
            'type'            => $type,
            'ciphertext'      => Tiger_Crypto::encrypt($secret),
            'public_material' => $meta['public_material'] ?? null,
            'fingerprint'     => $meta['fingerprint'] ?? null,
            'scopes'          => $meta['scopes'] ?? null,
            'status'          => 'active',
        ]);
        sodium_memzero($secret);
        $row = $model->findById($id);
        return ['ref' => $id, 'public' => Tiger_Model_GitCredential::publicView($row)];
    }

    /**
     * Resolve a credential reference to its PLAINTEXT secret, for a transport about to make a request.
     * The ONLY read path. Returns null when the ref is unknown/revoked/unusable (the transport then
     * marks the connection degraded). NEVER call this from a controller or log its result.
     *
     * @param  string $ref a git_credential id or a Tiger_Secret ref
     * @return string|null the plaintext secret, or null
     * @internal for transports only
     */
    public function resolve(string $ref): ?string
    {
        if ($ref === '') { return null; }
        if (self::isSecretRef($ref)) {
            $v = Tiger_Secret::get($ref);
            return ($v === null || $v === '') ? null : $v;
        }
        $row = (new Tiger_Model_GitCredential())->findById($ref);
        if (!$row || $row->revoked_at !== null) { return null; }
        try {
            return Tiger_Crypto::decrypt((string) $row->ciphertext);
        } catch (\Throwable $e) {
            return null;   // unusable secret — treated as "not resolvable", never surfaced
        }
    }

    /**
     * Rotate: store the new credential, let the caller VERIFY it works, then revoke the old one. The
     * old secret is only revoked after the new one is recorded, so a rotation never leaves the owner
     * with no working credential. Returns the new ref + public view; the caller verifies, then calls
     * `revoke($oldRef)` once verification passes.
     *
     * @return array{ref:string, public:array}
     */
    public function rotate(string $orgId, string $moduleKey, string $type, string $newSecret, array $meta = []): array
    {
        return $this->store($orgId, $moduleKey, $type, $newSecret, $meta);
    }

    /**
     * Revoke a local credential (mark revoked + soft-delete so the ciphertext stops resolving). A
     * Tiger_Secret ref is not ours to revoke — the caller removes it at its authority.
     *
     * @return bool true if a local credential was revoked
     */
    public function revoke(string $ref): bool
    {
        if ($ref === '' || self::isSecretRef($ref)) { return false; }
        $model = new Tiger_Model_GitCredential();
        $row = $model->findById($ref);
        if (!$row) { return false; }
        $row->revoked_at = gmdate('Y-m-d H:i:s');
        $row->status     = 'revoked';
        $row->save();
        $model->softDelete($model->getAdapter()->quoteInto('git_credential_id = ?', $ref));
        return true;
    }

    /**
     * Generate an Ed25519 deploy keypair (for the ShellTransport path). Returns the OpenSSH PUBLIC key
     * line to show the owner (they paste it into the repo's deploy-key box) and its fingerprint; the
     * returned `secret` is the private material to hand straight to `store()` and then forget. The
     * private key is NEVER returned to any API surface.
     *
     * @param  string $comment a label embedded in the public key (e.g. "tiger:my_module")
     * @return array{public_openssh:string, fingerprint:string, secret:string}
     */
    public function generateDeployKey(string $comment = 'tiger-git'): array
    {
        $pair   = sodium_crypto_sign_keypair();
        $pub    = sodium_crypto_sign_publickey($pair);     // 32 bytes ed25519 public
        $secret = sodium_crypto_sign_secretkey($pair);     // 64 bytes (seed+public)

        // OpenSSH public-key wire format: string "ssh-ed25519" + string <32-byte key>, base64'd.
        $blob = self::sshString('ssh-ed25519') . self::sshString($pub);
        $publicLine = 'ssh-ed25519 ' . base64_encode($blob) . ' ' . $comment;
        // Fingerprint is SHA256 of the blob, base64 (no padding) — the `SHA256:…` GitHub shows.
        $fingerprint = 'SHA256:' . rtrim(base64_encode(hash('sha256', $blob, true)), '=');

        return [
            'public_openssh' => $publicLine,
            'fingerprint'    => $fingerprint,
            'secret'         => base64_encode($secret),   // stored encrypted; the shell path unwraps it
        ];
    }

    /** SSH wire "string": 4-byte big-endian length prefix + bytes. */
    private static function sshString(string $s): string
    {
        return pack('N', strlen($s)) . $s;
    }
}
