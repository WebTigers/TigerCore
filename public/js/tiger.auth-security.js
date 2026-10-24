// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * tiger.auth-security.js
 *
 * Two-factor authentication management (admin shell). Two states: - disabled -> an "Enable"
 * wizard: setup (QR + manual key + recovery codes) then a live-code confirm (POST
 * /auth/totp-setup, then /auth/totp-activate).
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
document.addEventListener('DOMContentLoaded', function () {
    var fb = document.getElementById('twofa-feedback');
    function note(msg, kind) {
        TigerDOM.notify(fb, msg, { type: kind === 'success' ? 'success' : 'error' });
    }
    function post(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams(body || {})
        }).then(function (r) { return r.json().catch(function () { return {}; }); });
    }

    // --- Disable flow ---
    var disableForm = document.getElementById('twofa-disable-form');
    if (disableForm) {
        disableForm.addEventListener('submit', function (e) {
            e.preventDefault();
            fb.innerHTML = '';
            var btn = document.getElementById('disable-btn');
            TigerButton.run(btn, function () {
                return post('/auth/totp-disable', { code: document.getElementById('disable-code').value });
            }).then(function (res) {
                if (res && res.result === 1) { note(Tiger.t('twofaDisabled'), 'success'); setTimeout(function(){ location.reload(); }, 800); return; }
                note(Tiger.t('twofaCodeWrongOn'));
            }).catch(function () { note(Tiger.t('networkError')); });
        });
    }

    // --- Enable wizard ---
    var beginBtn = document.getElementById('twofa-begin');
    if (!beginBtn) { return; }

    function renderQr(uri) {
        var box = document.getElementById('totp-qr');
        box.innerHTML = '';
        if (window.QRCode) {                       // progressive: renderer present
            try { new window.QRCode(box, { text: uri, width: 164, height: 164 }); return; }
            catch (e) { /* fall through to the manual-key note */ }
        }
        box.innerHTML = '<span class="text-body-secondary small text-center px-2">Use the setup key &rarr;</span>';
    }

    beginBtn.addEventListener('click', function () {
        fb.innerHTML = '';
        TigerButton.run(beginBtn, function () {
            return post('/auth/totp-setup');
        }).then(function (res) {
            if (!res || res.result !== 1 || !res.data) { note(Tiger.t('setupFailed')); return; }
            var d = res.data;
            document.getElementById('totp-secret').textContent = d.secret;
            var link = document.getElementById('totp-uri-link');
            link.setAttribute('href', d.otpauth);
            var rc = document.getElementById('recovery-codes');
            rc.innerHTML = '';
            (d.recovery || []).forEach(function (code) {
                var span = document.createElement('span');
                span.textContent = code;
                rc.appendChild(span);
            });
            renderQr(d.otpauth);
            document.getElementById('twofa-intro').style.display = 'none';
            document.getElementById('twofa-setup').style.display = '';
            document.getElementById('confirm-code').focus();
        }).catch(function () { note(Tiger.t('networkError')); });
    });

    document.getElementById('copy-recovery').addEventListener('click', function () {
        var codes = Array.prototype.map.call(document.querySelectorAll('#recovery-codes span'), function (s) { return s.textContent; }).join('\n');
        if (navigator.clipboard) { navigator.clipboard.writeText(codes); this.innerHTML = '<i class="fa-solid fa-check me-1"></i>Copied'; }
    });

    document.getElementById('twofa-confirm-form').addEventListener('submit', function (e) {
        e.preventDefault();
        fb.innerHTML = '';
        var btn = document.getElementById('confirm-btn');
        TigerButton.run(btn, function () {
            return post('/auth/totp-activate', { code: document.getElementById('confirm-code').value });
        }).then(function (res) {
            if (res && res.result === 1) { note(Tiger.t('twofaOn'), 'success'); setTimeout(function(){ location.reload(); }, 900); return; }
            note(Tiger.t('twofaCodeWrong'));
            document.getElementById('confirm-code').value = '';
            document.getElementById('confirm-code').focus();
        }).catch(function () { note(Tiger.t('networkError')); });
    });
});
