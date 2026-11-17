<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Register_Form_Action — a token-only form for the parameterless registration writes: verifyDomain and
 * resendEmail (TIGER-528).
 *
 * Neither takes input, but verifyDomain calls out to the registry and rewrites the stored state, and
 * resendEmail mints a fresh verification token and sends mail — state changes a cross-site page must not
 * be able to trigger. So the form declares no fields: its whole job is the `_csrf` element.
 */
class Register_Form_Action extends Register_Form_Write
{
}
