// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * newsletter.form.js — the embeddable subscribe form.
 *
 * Intercepts the [newsletter_form] form's submit and POSTs it to /api
 * (Newsletter_Service_Subscribe::subscribe), then swaps in the server's message. The form works as a
 * plain POST to /api without JS too; this only upgrades it to stay-on-page with inline feedback.
 */
(function () {
    function notify(box, message, cls) {
        if (window.TigerDOM && TigerDOM.notify) { TigerDOM.notify(box, message, { type: cls }); return; }
        if (box) {
            box.innerHTML = '<div class="alert alert-' + (cls === 'success' ? 'success' : 'danger') + '" role="alert"></div>';
            box.firstChild.textContent = message;
        }
    }

    function wire(form) {
        if (form.__tgNewsletter) { return; }
        form.__tgNewsletter = true;

        var section = form.closest('.tiger-newsletter') || document;
        var box     = section.querySelector('.tiger-newsletter-feedback');

        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            var btn  = form.querySelector('button[type="submit"]');
            var body = new URLSearchParams(new FormData(form));

            var run = function () {
                return fetch('/api', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: body
                }).then(function (r) { return r.json().catch(function () { return {}; }); });
            };

            var after = function (res) {
                (res && res.messages || []).forEach(function (m) { notify(box, m.message, m.class); });
                if (res && res.result === 1) { form.reset(); }
            };

            if (btn && window.TigerButton && TigerButton.run) {
                TigerButton.run(btn, run).then(after).catch(function () {});
            } else {
                run().then(after).catch(function () {});
            }
        });
    }

    function boot() {
        var forms = document.querySelectorAll('.tiger-newsletter-form');
        for (var i = 0; i < forms.length; i++) { wire(forms[i]); }
    }

    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', boot); } else { boot(); }
})();
