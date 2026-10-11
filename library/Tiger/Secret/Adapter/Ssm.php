<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Secret_Adapter_Ssm — AWS Systems Manager Parameter Store (SecureString) backend.
 *
 * Reached via the `ssm:` scheme: `ssm:/tiger/prod/node/crypto_key` → the decrypted parameter value. The
 * value of this backend for the fleet: a box authenticates to SSM with its **EC2 instance IAM role**
 * (the default AWS credential chain), not a pre-shared secret — so nothing is baked into the golden
 * image and nothing rides in user-data. The role is the root of trust; KMS decrypts the SecureString.
 *
 * Capability-detected exactly like the EC2 provisioner: `available()` is true only when the AWS SDK's
 * `Aws\Ssm\SsmClient` is loadable (shipped by the tiger-sdk-aws provider module, never vendored into
 * core) or a client has been injected — so on a shared-hosting install with no SDK the adapter is dormant
 * and `ssm:` refs resolve to null instead of fataling. A caller gets a credential or a clean null; it
 * never throws into the request.
 *
 * Config (read by the module Bootstrap / the facade's lazy wiring from `tiger.secret.ssm.*`):
 *   region  — AWS region (default us-east-1)
 *   prefix  — prepended to a relative path (e.g. /tiger/prod/node); an absolute `ssm:/…` ignores it
 *   key/secret — optional static credentials; omit to use the instance role / default chain
 *
 * Implements the writer interface too (put/delete) for ops/provisioning tooling — never the request path.
 *
 * @api
 */
class Tiger_Secret_Adapter_Ssm implements Tiger_Secret_Adapter_Interface, Tiger_Secret_Writer_Interface
{
    /** @var array the adapter options (region, prefix, key, secret, client) */
    protected $_opts;

    /** @var object|null an injected SSM client (tests) or the lazily-built SDK client */
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
        return $this->_client !== null || class_exists('Aws\\Ssm\\SsmClient');
    }

    public function get(string $path): ?string
    {
        if (!$this->available()) {
            return null;
        }
        try {
            $res = $this->_client()->getParameter(['Name' => $this->_name($path), 'WithDecryption' => true]);
            $val = $res['Parameter']['Value'] ?? null;
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
        try {
            $this->_client()->putParameter([
                'Name'      => $this->_name($path),
                'Value'     => $value,
                'Type'      => 'SecureString',
                'Overwrite' => true,
            ]);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function delete(string $path): bool
    {
        if (!$this->available()) {
            return false;
        }
        try {
            $this->_client()->deleteParameter(['Name' => $this->_name($path)]);
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /** Build the parameter name: an absolute path wins; a relative one is joined under the prefix. */
    protected function _name(string $path): string
    {
        $prefix = rtrim((string) ($this->_opts['prefix'] ?? ''), '/');
        if ($path === '') {
            return $prefix;
        }
        if ($path[0] === '/') {
            return $path;
        }
        return $prefix !== '' ? $prefix . '/' . $path : $path;
    }

    /** The SSM client — the injected one (tests) or a lazily-built SDK client using the role/static creds. */
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
        $class = 'Aws\\Ssm\\SsmClient';
        $this->_client = new $class($args);
        return $this->_client;
    }
}
