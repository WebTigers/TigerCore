/*
 * SPDX-License-Identifier: BSD-3-Clause
 * Copyright (c) 2026 WebTigers.
 *
 * Matomo settings — save wiring. Posts the form to /api (matomo/matomo/save) through the house UI
 * primitives: TigerButton.run for the busy state, TigerDOM.notify for feedback, res.form for inline
 * field errors. The two switches (enabled, exclude_signed_in) ride along as checkbox values. No
 * page-POST, no <button type="submit"> — per ADMIN.md.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var saveBtn = document.getElementById('matomo-save');
        if (!saveBtn) { return; }

        var form = document.getElementById('matomo-form');
        var fb   = document.getElementById('matomo-feedback');

        saveBtn.addEventListener('click', function () {
            // Clear any prior inline errors.
            Array.prototype.forEach.call(form.querySelectorAll('.is-invalid'), function (el) {
                el.classList.remove('is-invalid');
            });

            var fd = new URLSearchParams(new FormData(form));
            fd.set('module', 'matomo');
            fd.set('service', 'matomo');
            fd.set('method', 'save');

            TigerButton.run(this, function () {
                return fetch('/api', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd
                }).then(function (r) { return r.json().catch(function () { return {}; }); });
            }).then(function (res) {
                if (res && res.result === 1) {
                    TigerDOM.notify(fb, (res.messages && res.messages[0] && res.messages[0].message) || 'Saved.', { type: 'success' });
                    return;
                }
                if (res && res.form) {
                    Object.keys(res.form).forEach(function (f) {
                        var el = form.querySelector('[name="' + f + '"]');
                        if (el) { el.classList.add('is-invalid'); }
                    });
                }
                (res && res.messages || []).forEach(function (m) {
                    TigerDOM.notify(fb, m.message, { type: m.class });
                });
            }).catch(function () {
                TigerDOM.notify(fb, 'Network error — please try again.', { type: 'error' });
            });
        });
    });
}());
