<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Secret_Adapter_SecretsManager — AWS Secrets Manager backend (the rotation-managed sibling of SSM).
 *
 * Reached via the `secretsmanager:` scheme: `secretsmanager:tiger/prod/stripe` → the secret string.
 * Same trust model as the SSM adapter (the EC2 instance role authenticates; nothing baked), but backed
 * by Secrets Manager, which adds managed rotation and a per-secret cost — reach for it only where
 * rotation justifies the price; SSM Parameter Store SecureString is the cheaper default for most secrets.
 *
 * Capability-detected on `Aws\SecretsManager\SecretsManagerClient` (shipped by tiger-sdk-aws, never
 * vendored into core) or an injected client — dormant and null-resolving without the SDK. Implements the
 * writer interface (put upserts via create-or-update; delete schedules deletion) for provisioning tooling.
 *
 * Config (`tiger.secret.secretsmanager.*`): region, prefix, optional key/secret (else the instance role).
 *
 * @api
 */
class Tiger_Secret_Adapter_SecretsManager implements Tiger_Secret_Adapter_Interface, Tiger_Secret_Writer_Interface
{
    /** @var array the adapter options (region, prefix, key, secret, client) */
    protected $_opts;

    /** @var object|null an injected client (tests) or the lazily-built SDK client */
    protected $_client;

    /**
     * @param array $opts region, prefix, key, secret, and optionally a pre-built `client` (for tests)
     */
    public function __construct(array $opts = [])
    {
        $this->_opts   = $opts;
        $this->_client = $opts['client'] ?? null;
    }

    public function available(): bool
    {
        return $this->_client !== null || class_exists('Aws\\SecretsManager\\SecretsManagerClient');
    }

    public function get(string $path): ?string
    {
        if (!$this->available()) {
            return null;
        }
        try {
            $res = $this->_client()->getSecretValue(['SecretId' => $this->_name($path)]);
            $val = $res['SecretString'] ?? null;
            return ($val === null || $val === '') ? null : (string) $val;
        } catch (Throwable $e) {
            return null;
        }
    }

    public function put(string $path, string $value): bool
    {
        if (!$this->available()) {
            return false;
        }
        $name = $this->_name($path);
        try {
            $this->_client()->putSecretValue(['SecretId' => $name, 'SecretString' => $value]);
            return true;
        } catch (Throwable $e) {
            // Most likely the secret doesn't exist yet — create it.
            try {
                $this->_client()->createSecret(['Name' => $name, 'SecretString' => $value]);
                return true;
            } catch (Throwable $e2) {
                return false;
            }
        }
    }

    public function delete(string $path): bool
    {
        if (!$this->available()) {
            return false;
        }
        try {
            $this->_client()->deleteSecret([
                'SecretId'                   => $this->_name($path),
                'ForceDeleteWithoutRecovery' => true,
            ]);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /** Join a relative id under the configured prefix (Secrets Manager names are path-like). */
    protected function _name(string $path): string
    {
        $prefix = rtrim((string) ($this->_opts['prefix'] ?? ''), '/');
        if ($path === '') {
            return $prefix;
        }
        if ($path[0] === '/') {
            return ltrim($path, '/');
        }
        return $prefix !== '' ? $prefix . '/' . $path : $path;
    }

    /** The client — injected (tests) or a lazily-built SDK client using the role/static creds. */
    protected function _client()
    {
        if ($this->_client !== null) {
            return $this->_client;
        }
        $args = ['region' => (string) ($this->_opts['region'] ?? 'us-east-1'), 'version' => 'latest'];
        $key  = (string) ($this->_opts['key'] ?? '');
        $sec  = (string) ($this->_opts['secret'] ?? '');
        if ($key !== '' && $sec !== '') {
            $args['credentials'] = ['key' => $key, 'secret' => $sec];
        }
        $class = 'Aws\\SecretsManager\\SecretsManagerClient';
        $this->_client = new $class($args);
        return $this->_client;
    }
}
