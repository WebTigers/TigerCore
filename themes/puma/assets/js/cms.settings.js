/* SPDX-License-Identifier: BSD-3-Clause
 * Copyright (c) 2026 WebTigers. Tiger and WebTigers are trademarks of WebTigers.
 *
 * CMS Settings — save handler. The home-page control is a TigerPathbox (auto-initialised by
 * tiger.pathbox.js over Cms_Service_Paths); this only wires the Save button, which posts the form to
 * /api (Cms_Service_Settings::save) and drives the feedback via the house primitives. No page-POST.
 */
(function (document) {
    'use strict';

    function init() {
        var form = document.getElementById('cms-settings-form');
        var fb   = document.getElementById('cms-settings-feedback');
        var save = document.getElementById('cms-settings-save');
        if (!form || !save) { return; }

        save.addEventListener('click', function () {
            var btn = this;
            fb.innerHTML = '';
            form.querySelectorAll('.is-invalid').forEach(function (e) { e.classList.remove('is-invalid'); });

            // Commit any text typed into the pathbox but not yet blurred, SYNCHRONOUSLY, so the value
            // typed a moment before clicking Save is in the hidden field before FormData reads it.
            if (window.TigerPathbox) { TigerPathbox.flushAll(form); }

            var fd = new URLSearchParams(new FormData(form));
            fd.set('module', 'cms'); fd.set('service', 'settings'); fd.set('method', 'save');

            TigerButton.run(btn, function () {
                return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                    .then(function (r) { return r.json().catch(function () { return {}; }); });
            })
                .then(function (res) {
                    if (res && res.result === 1) { TigerDOM.notify(fb, Tiger.t('settingsSaved'), { type: 'success' }); return; }
                    if (res && res.form) {
                        Object.keys(res.form).forEach(function (field) {
                            // home_page is a hidden backing field; show the error on the VISIBLE search input.
                            var input = field === 'home_page'
                                ? document.getElementById('set-home-page-search')
                                : form.querySelector('[name="' + field + '"]');
                            if (input) { input.classList.add('is-invalid'); }
                        });
                    }
                    var msgs = (res && res.messages) || [];
                    if (msgs.length) { fb.innerHTML = ''; msgs.forEach(function (m) { TigerDOM.notify(fb, m.message, { type: m.class, replace: false }); }); }
                    else { TigerDOM.notify(fb, Tiger.t('fixFields'), { type: 'error' }); }
                })
                .catch(function () { TigerDOM.notify(fb, Tiger.t('networkError'), { type: 'error' }); });
        });
    }

    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); }
    else { init(); }
})(document);
