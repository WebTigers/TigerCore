// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * tiger.consent-banner.js
 *
 * Cookie-consent banner (PUMA) — GDPR + CCPA in ONE banner, ONE Accept. Self-checking: renders
 * nothing unless Tiger_Consent::showBanner() is true. Accept writes the consent cookie ('all')
 * and reloads so consent-gated trackers (GA, …) load; "Necessary only" writes 'none' — which is
 * also the California opt-out of sale/sharing. When a Global Privacy Control signal is present
 * we say so and you're already opted out (honored server-side by Tiger_Consent); an explicit
 * Accept still wins.
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
(function () {
    var bar = document.getElementById('tiger-consent');
    if (!bar) { return; }
    // Belt-and-suspenders: if the browser exposes GPC via JS but a proxy stripped the Sec-GPC header,
    // still surface the opt-out acknowledgment.
    if (navigator.globalPrivacyControl === true) {
        var g = document.getElementById('tiger-consent-gpc');
        if (g) { g.classList.remove('d-none'); }
    }
    function setCookie(v) {
        document.cookie = 'tiger_consent=' + v + '; path=/; max-age=31536000; SameSite=Lax';
    }
    bar.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-consent]');
        if (!btn) { return; }
        if (btn.getAttribute('data-consent') === 'accept') {
            setCookie('all');           // explicit opt-in — overrides any GPC signal for this visitor
            location.reload();          // reload so consent-gated tags (e.g. GA) load this visit
        } else {
            setCookie('none');          // GDPR decline == CCPA opt-out of sale/sharing
            bar.parentNode.removeChild(bar);
        }
    });
})();
