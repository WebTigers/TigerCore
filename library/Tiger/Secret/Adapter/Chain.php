<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Secret_Adapter_Chain — try several backends in order; the first hit wins.
 *
 * The resilient default driver for a mixed fleet: `tiger.secret.driver = chain` +
 * `tiger.secret.chain = ssm,env,local` resolves a bare name against SSM first, then an env var, then the
 * inline-encrypted local value — so a box fetches from the store when it can and degrades to what it has
 * otherwise. Each child is looked up through the facade (`Tiger_Secret::adapter()`), so its own config /
 * prefix applies; a child that is unavailable or throws is skipped. `chain` is filtered out of its own
 * child list so it can never recurse into itself.
 *
 * @api
 */
class Tiger_Secret_Adapter_Chain implements Tiger_Secret_Adapter_Interface
{
    /** @var string[] ordered schemes to try */
    protected $_schemes;

    /**
     * @param string[] $schemes ordered backend schemes to try (e.g. ['ssm','env','local']); 'chain' is dropped
     */
    public function __construct(array $schemes)
    {
        $this->_schemes = array_values(array_filter(
            array_map(static fn($s) => strtolower(trim((string) $s)), $schemes),
            static fn($s) => $s !== '' && $s !== 'chain'
        ));
    }

    public function available(): bool
    {
        foreach ($this->_schemes as $scheme) {
            $a = Tiger_Secret::adapter($scheme);
            if ($a && $a->available()) {
                return true;
            }
        }
        return false;
    }

    public function get(string $path): ?string
    {
        foreach ($this->_schemes as $scheme) {
            $a = Tiger_Secret::adapter($scheme);
            if (!$a || !$a->available()) {
                continue;
            }
            try {
                $v = $a->get($path);
            } catch (Throwable $e) {
                $v = null;
            }
            if ($v !== null) {
                return $v;
            }
        }
        return null;
    }
}
