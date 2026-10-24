// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * system.settings-index.js
 *
 * Settings — the platform settings screen (site, auth, session, mail, consent, locale). Saving
 * is one /api call to System_Service_Settings; this only wires the controls and the per-provider
 * reveals.
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('sys-settings-form');
    var fb   = document.getElementById('sys-settings-feedback');

    // TigerDOM.notify takes HTML, so any value interpolated into a message is escaped first —
    // a provider's error text and a submitted address are both attacker-influenceable.
    var esc = function (s) {
        var d = document.createElement('div');
        d.textContent = (s == null ? '' : String(s));
        return d.innerHTML;
    };

    document.getElementById('sys-settings-save').addEventListener('click', function () {
        var btn = this;
        fb.innerHTML = '';
        form.querySelectorAll('.is-invalid').forEach(function (e) { e.classList.remove('is-invalid'); });
        var fd = new URLSearchParams(new FormData(form));
        // Unchecked switches don't submit — send explicit "0" so a toggle-off persists.
        ['autologout_enabled', 'recaptcha_enabled', 'recaptcha_fail_open', 'recaptcha_hide_badge', 'signup_disabled'].forEach(function (k) {
            if (!fd.has(k)) { fd.set(k, '0'); }
        });
        fd.set('module', 'system'); fd.set('service', 'settings'); fd.set('method', 'save');
        TigerButton.run(btn, function () {
            return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                .then(function (r) { return r.json().catch(function () { return {}; }); });
        })
            .then(function (res) {
                if (res && res.result === 1) { TigerDOM.notify(fb, Tiger.t('settingsSaved'), { type: 'success' }); return; }
                if (res && res.form) {
                    Object.keys(res.form).forEach(function (field) {
                        var input = form.querySelector('[name="' + field + '"]');
                        if (input) { input.classList.add('is-invalid'); }
                    });
                }
                var msgs = (res && res.messages) || [];
                if (msgs.length) { fb.innerHTML = ''; msgs.forEach(function (m) { TigerDOM.notify(fb, m.message, { type: m.class, replace: false }); }); }
                else { TigerDOM.notify(fb, Tiger.t('fixFields'), { type: 'error' }); }
            })
            .catch(function () { TigerDOM.notify(fb, Tiger.t('networkError'), { type: 'error' }); });
    });

    // Live human-readable durations + dim the options when off.
    function humanize(total) {
        total = Math.max(0, parseInt(total, 10) || 0);
        if (!total) { return '0 seconds'; }
        var units = [['year', 31536000], ['month', 2592000], ['day', 86400], ['hour', 3600], ['minute', 60]];
        var parts = [], rem = total;
        units.forEach(function (u) {
            var n = Math.floor(rem / u[1]);
            if (n > 0) { parts.push(n + ' ' + u[0] + (n > 1 ? 's' : '')); rem -= n * u[1]; }
        });
        if (rem > 0 && parts.length < 2) { parts.push(rem + ' second' + (rem > 1 ? 's' : '')); }
        return parts.join(', ');
    }
    function bindDuration(inputId, outId) {
        var i = document.getElementById(inputId), o = document.getElementById(outId);
        if (!i || !o) { return; }
        var upd = function () { o.textContent = '≈ ' + humanize(i.value); };
        i.addEventListener('input', upd); upd();
    }
    bindDuration('set-session-ttl-priv', 'set-session-ttl-priv-h');
    bindDuration('set-session-ttl', 'set-session-ttl-h');
    bindDuration('set-session-ttl-guest', 'set-session-ttl-guest-h');
    bindDuration('set-autologout-seconds', 'set-autologout-seconds-h');

    // PHP gc_maxlifetime ceiling awareness. The DB session store ignores gc_maxlifetime (per-row TTL),
    // so a tier above it is honored; the FILE fallback is hard-capped by it, so any tier above the PHP
    // ceiling would be reaped early no matter what's set here — warn, and name the offending tiers.
    (function () {
        var panel = document.getElementById('php-session-panel');
        if (!panel) { return; }
        var phpMax = parseInt(panel.getAttribute('data-php-max'), 10) || 0;
        var dbHandler = panel.getAttribute('data-db-handler') === '1';
        var maxH = document.getElementById('php-max-h');
        if (maxH && phpMax) { maxH.textContent = '(≈ ' + humanize(phpMax) + ')'; }
        var warn = document.getElementById('php-max-warn'), warnBody = document.getElementById('php-max-warn-body'), ok = document.getElementById('php-max-ok');
        var tiers = [['Admin / privileged', 'set-session-ttl-priv'], ['Standard user', 'set-session-ttl'], ['Guest', 'set-session-ttl-guest']];
        function check() {
            if (dbHandler) { if (ok) { ok.classList.remove('d-none'); } if (warn) { warn.classList.add('d-none'); } return; }
            var over = tiers.filter(function (t) { var e = document.getElementById(t[1]); return e && (parseInt(e.value, 10) || 0) > phpMax; }).map(function (t) { return t[0]; });
            if (phpMax && over.length) {
                warnBody.innerHTML = 'This install uses the <strong>PHP file session store</strong>, so <code>session.gc_maxlifetime</code> (' + phpMax + ' sec ≈ ' + humanize(phpMax) + ') is the hard ceiling. <strong>' + over.join(', ') + '</strong> set above it will be reaped early by PHP regardless of the value here — raise <code>gc_maxlifetime</code> in <code>php.ini</code>, or switch to the database session store.';
                warn.classList.remove('d-none'); if (ok) { ok.classList.add('d-none'); }
            } else if (warn) { warn.classList.add('d-none'); }
        }
        tiers.forEach(function (t) { var e = document.getElementById(t[1]); if (e) { e.addEventListener('input', check); } });
        check();
    })();

    var alToggle = document.getElementById('set-autologout-enabled');
    var alOpts   = document.getElementById('set-autologout-opts');
    if (alToggle && alOpts) {
        var sync = function () { alOpts.classList.toggle('opacity-50', !alToggle.checked); };
        alToggle.addEventListener('change', sync); sync();
    }

    // Show the v3-only options only when v3 is selected.
    var rcVer = document.getElementById('set-rc-version');
    var rcV3  = document.getElementById('set-rc-v3');
    if (rcVer && rcV3) {
        var syncV3 = function () { rcV3.classList.toggle('d-none', rcVer.value !== 'v3'); };
        rcVer.addEventListener('change', syncV3); syncV3();
    }

    // Location: show only the selected provider's config fields.
    function bindProvider(selectId, groupClass) {
        var sel = document.getElementById(selectId);
        if (!sel) { return; }
        var groups = document.querySelectorAll('.' + groupClass);
        var sync = function () { groups.forEach(function (g) { g.style.display = (g.getAttribute('data-provider') === sel.value) ? '' : 'none'; }); };
        sel.addEventListener('change', sync); sync();
    }
    bindProvider('loc-ip-provider', 'loc-ip-fields');
    bindProvider('loc-addr-provider', 'loc-addr-fields');

    // Location: live "Test" lookup (sends the selected provider + its current field values).
    var locTestBtn = document.getElementById('loc-test-btn');
    if (locTestBtn) {
        locTestBtn.addEventListener('click', function () {
            var out      = document.getElementById('loc-test-result');
            var provider = document.getElementById('loc-ip-provider').value;
            var fd = new URLSearchParams();
            fd.set('module', 'system'); fd.set('service', 'settings'); fd.set('method', 'locationTest');
            fd.set('ip', document.getElementById('loc-test-ip').value);
            fd.set('provider', provider);
            document.querySelectorAll('.loc-ip-fields[data-provider="' + provider + '"] input').forEach(function (inp) {
                var m = inp.name.match(/\[[^\]]+\]\[([^\]]+)\]/);
                if (m && inp.value !== '') { fd.set('config[' + m[1] + ']', inp.value); }
            });
            out.className = 'small mt-2 text-body-secondary'; out.textContent = 'Testing…';
            TigerButton.run(this, function () {
                return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd }).then(function (r) { return r.json().catch(function () { return {}; }); });
            }).then(function (res) {
                var d = (res && res.data) || {};
                if (d.ok) { out.className = 'small mt-2 text-success'; out.textContent = '✓ ' + (d.label || (d.city ? d.city + ', ' : '') + (d.country || '?')); }
                else { out.className = 'small mt-2 text-danger'; out.textContent = '✗ ' + (d.error || Tiger.t('lookupFailed')); }
            }).catch(function () { out.className = 'small mt-2 text-danger'; out.textContent = Tiger.t('networkErrorShort'); });
        });
    }

    // --- Email SMTP: the provider drives which blocks are relevant ---
    var mailProvider = document.getElementById('set-mail-provider');
    var mailSmtpBox  = document.getElementById('set-mail-smtp');
    if (mailProvider) {
        // Which providers use the SMTP connection block, and each one's host/port defaults —
        // carried from the PHP catalog on #set-mail-kinds[data-kinds] so the two can't drift apart.
        var kindsEl   = document.getElementById('set-mail-kinds');
        var mailKinds = {};
        try { mailKinds = JSON.parse((kindsEl && kindsEl.getAttribute('data-kinds')) || '{}'); } catch (e) {}

        var syncProvider = function (prefill) {
            var key  = mailProvider.value;
            var def  = mailKinds[key] || {};
            var smtp = def.kind === 'smtp' && key !== 'sendmail';

            if (mailSmtpBox) { mailSmtpBox.classList.toggle('d-none', !smtp); }
            document.querySelectorAll('.mail-provider-fields').forEach(function (box) {
                box.style.display = (box.getAttribute('data-provider') === key) ? '' : 'none';
            });

            // Prefill the connection defaults on a real change, so picking "SendGrid (SMTP)" fills
            // in host/port/encryption instead of leaving the operator to look them up. Never on
            // first paint — that would clobber a saved custom host.
            if (prefill && smtp && def.smtp) {
                var set = function (id, val) {
                    var elx = document.getElementById(id);
                    if (elx && val !== null && val !== undefined && String(val) !== '') { elx.value = String(val); }
                };
                if (String(def.smtp.host || '').indexOf('{') === -1) { set('set-mail-host', def.smtp.host); }
                set('set-mail-port', def.smtp.port);
                set('set-mail-ssl', def.smtp.ssl);
                set('set-mail-auth', def.smtp.auth);
            }
        };
        mailProvider.addEventListener('change', function () { syncProvider(true); });
        syncProvider(false);
    }

    // --- Email SMTP: send a test with the values CURRENTLY in the form (no save required) ---
    var mailTestBtn = document.getElementById('mail-test-btn');
    if (mailTestBtn) {
        mailTestBtn.addEventListener('click', function () {
            var out = document.getElementById('mail-test-result');
            var to  = document.getElementById('set-mail-test-to').value.trim();
            if (to === '') {
                TigerDOM.notify(out, Tiger.t('mailTestNeedsAddress'), { type: 'error', dismissOnClick: true });
                return;
            }
            var fd = new URLSearchParams();
            fd.set('module', 'system'); fd.set('service', 'settings'); fd.set('method', 'mailTest');
            fd.set('to', to);
            out.innerHTML = '';
            ['mail_provider', 'mail_smtp_host', 'mail_smtp_port', 'mail_smtp_ssl', 'mail_smtp_auth',
             'mail_smtp_username', 'mail_smtp_password', 'mail_from_email', 'mail_from_name'].forEach(function (k) {
                var input = form.querySelector('[name="' + k + '"]');
                fd.set(k, input ? input.value : '');
            });
            // The selected provider's credential fields, so an API key can be tested before saving.
            var credBox = document.querySelector('.mail-provider-fields[data-provider="' + (document.getElementById('set-mail-provider') || {}).value + '"]');
            if (credBox) {
                credBox.querySelectorAll('input[name^="mail_field"]').forEach(function (inp) {
                    if (inp.value !== '') { fd.set(inp.name, inp.value); }
                });
            }
            // The button carries the in-flight state (TigerButton spinner) and TigerDOM.notify owns
            // the message envelope — themed alert, icon, reveal animation, dismiss. Never hand-rolled.
            TigerButton.run(this, function () {
                return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd }).then(function (r) { return r.json().catch(function () { return {}; }); });
            }).then(function (res) {
                var d = (res && res.data) || {};
                if (d.ok) {
                    var detail = [d.via, (d.ms || 0) + 'ms'].filter(Boolean).join(' · ');
                    TigerDOM.notify(out,
                        '<strong>' + Tiger.t('mailTestSent') + ' ' + esc(d.to) + '</strong>' +
                        (detail ? '<div class="text-body-secondary mt-1">' + esc(detail) + '</div>' : ''),
                        { type: 'success', dismissOnClick: true });
                } else {
                    // The transport's own message is the diagnostic — verbatim, and it STICKS
                    // (notify keeps errors until dismissed) so it can actually be read and acted on.
                    var msg = d.error || ((res && res.messages && res.messages[0]) ? res.messages[0].message : Tiger.t('mailTestFailed'));
                    TigerDOM.notify(out, '<span class="text-break">' + esc(msg) + '</span>',
                        { type: 'error', dismissOnClick: true });
                }
            }).catch(function () {
                TigerDOM.notify(out, Tiger.t('networkErrorShort'), { type: 'error', dismissOnClick: true });
            });
        });
    }
});
