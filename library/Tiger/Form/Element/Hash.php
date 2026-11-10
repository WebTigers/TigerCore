<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Form_Element_Hash — a CSRF token that lives for its full TIMEOUT, not a single request.
 *
 * Zend_Form_Element_Hash arms the token with a 1-HOP session expiration, which makes it effectively
 * single-use: the first submit consumes it, so if that submit fails ANY other field's validation the
 * corrected resubmit dies with "your security token expired" and the only cure is a full page refresh.
 * That bit the profile Security / change-password form — correct the flagged field, resubmit, and the
 * token is already gone.
 *
 * Tiger's CSRF design is timeout-based and salt-shared across related endpoints (see
 * Tiger_Form::csrfSalt / CSRF_TIMEOUT — "the token isn't single-use; it validates until it times out").
 * So we arm the token on the seconds TTL only and DROP the hop limit: one rendered token stays valid for
 * CSRF_TIMEOUT within the session, surviving a failed-then-corrected resubmit.
 *
 * A render also REUSES the live token for its salt instead of minting a new one (TIGER-481). Stock Zend
 * overwrites the session hash on every render, so a second form sharing the salt — a second tab, or a
 * page that renders several forms on one salt — silently invalidated the token every earlier page was
 * holding. One token per salt per session, kept until it times out; session expiry still drops it (the
 * next render then mints a fresh one), and salt isolation, forged-token rejection and the validator are
 * inherited unchanged.
 *
 * @api
 */
class Tiger_Form_Element_Hash extends Zend_Form_Element_Hash
{
    /**
     * Arm the CSRF token for its timeout WITHOUT the single-hop expiration Zend applies, reusing the
     * salt's unexpired token when there is one so earlier renders stay valid.
     *
     * @return void
     */
    public function initCsrfToken()
    {
        $session = $this->getSession();
        if ($this->_hash === null && isset($session->hash) && is_string($session->hash) && $session->hash !== '') {
            $this->_hash = $session->hash;
            $this->setValue($this->_hash);
        }
        $session->setExpirationSeconds($this->getTimeout());
        $session->hash = $this->getHash();
    }
}
