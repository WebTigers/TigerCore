<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Git_Form_Write — the shared base for every TigerGit write form (TIGER-528).
 *
 * One CSRF salt for the whole git dashboard: the screen renders a single token and drives connect,
 * tag, submit, decide, apply, setPolicy, rollback, recheck and disconnect from the same page.
 * `Tiger_Form::csrfSalt()` defaults to the form class, which would mint a token per form and make the
 * second call on the page fail "your security token expired" — so the set shares this salt
 * (see `Desk_Form_TicketWrite`, the reference consumer).
 *
 * The helpers declare the fields the services read. The services keep their own semantic gates (the
 * slug guard, Tiger_Git::isSemver, the approved-release lookup) and their error keys; the form is the
 * input contract plus the CSRF token.
 */
abstract class Git_Form_Write extends Tiger_Form
{
    /**
     * One token for every git write on the dashboard.
     *
     * @return string
     */
    protected function csrfSalt(): string
    {
        return 'Git_Form_Write';
    }

    /**
     * An optional, filter-free text field. Presence and meaning stay the service's question: it already
     * answers both with its own error keys (`git.error.invalid_module`, `invalid_decision`,
     * `invalid_channel`, …) as top-level messages that callers and tests key on, so the element carries
     * the field into the contract without re-refusing it under a different envelope.
     *
     * @param  string $name the field name
     * @return array  [type, name, options]
     */
    protected function _field(string $name): array
    {
        return ['text', $name, ['required' => false, 'filters' => [], 'validators' => []]];
    }
}
