<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Git_Exception — a structured, agent-readable, SECRET-FREE git failure (TIGER-334).
 *
 * Every Tiger_Git operation that fails throws this with a stable `code` an agent can branch on
 * (e.g. NON_FAST_FORWARD, MERGE_CONFLICT, DIRTY_TREE, CREDENTIAL_INVALID, RATE_LIMITED,
 * HOST_NOT_ALLOWED, TRANSPORT_UNAVAILABLE). The message is a short human sentence; it NEVER contains a
 * token, a key, a full command line, or a stack trace — the controller maps `code` to a translation
 * key and returns only that. `detail` carries safe extra context (e.g. conflicting file names), never
 * secrets.
 *
 * @api
 * @since 1.38.0
 */
class Tiger_Git_Exception extends RuntimeException
{
    const NON_FAST_FORWARD     = 'NON_FAST_FORWARD';
    const MERGE_CONFLICT       = 'MERGE_CONFLICT';
    const DIRTY_TREE           = 'DIRTY_TREE';
    const CREDENTIAL_INVALID   = 'CREDENTIAL_INVALID';
    const CREDENTIAL_MISSING   = 'CREDENTIAL_MISSING';
    const RATE_LIMITED         = 'RATE_LIMITED';
    const HOST_NOT_ALLOWED     = 'HOST_NOT_ALLOWED';
    const TRANSPORT_UNAVAILABLE = 'TRANSPORT_UNAVAILABLE';
    const PATH_ESCAPE          = 'PATH_ESCAPE';
    const NOT_CONNECTED        = 'NOT_CONNECTED';
    const PROTECTED_BRANCH     = 'PROTECTED_BRANCH';
    const INVALID_REF          = 'INVALID_REF';
    const API_ERROR            = 'API_ERROR';
    const SHELL_ERROR          = 'SHELL_ERROR';
    const NOT_IMPLEMENTED      = 'NOT_IMPLEMENTED';   // a transport method not yet built (phased delivery)
    const NOTHING_TO_COMMIT    = 'NOTHING_TO_COMMIT';

    /** @var string the stable error code */
    protected $gitCode;

    /** @var array<string,mixed> safe extra context (no secrets) */
    protected $detail;

    /**
     * @param string             $code    one of the class constants
     * @param string             $message a short, secret-free human sentence
     * @param array<string,mixed> $detail  safe structured context (conflicting files, http status, …)
     */
    public function __construct(string $code, string $message = '', array $detail = [])
    {
        parent::__construct($message !== '' ? $message : $code);
        $this->gitCode = $code;
        $this->detail  = $detail;
    }

    /** The stable error code (Exception::getCode() is final, so this is the accessor). */
    public function code(): string
    {
        return $this->gitCode;
    }

    /** Safe structured context. @return array<string,mixed> */
    public function getDetail(): array
    {
        return $this->detail;
    }
}
