// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * backup.index-index.js
 *
 * Backup & Restore screen (/backup) — create a backup (components + destination), the backup
 * history (download / restore / delete), restore from an uploaded file, and the scheduled-backup
 * settings (embedding the reusable schedule-control + cron-setup partials). Follows ADMIN.md:
 * header + feedback mount + cards; every mutation is an /api call via TigerButton/TigerDOM. No
 * page-POSTs, no bespoke CSS.
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
(function () {
    var fb = document.getElementById('backup-feedback');
    // The screen's ONE CSRF token (rendered in #backup-token); every write below posts it (TIGER-528).
    function csrf() { var el = document.querySelector('#backup-token [name="_csrf"]'); return el ? el.value : ''; }
    function notify(res) {
        (res && res.messages || []).forEach(function (m) { TigerDOM.notify(fb, m.message, { type: m.class }); });
        // Per-field errors (e.g. notify_email) — show each field's message beside the summary.
        if (res && res.form) {
            Object.keys(res.form).forEach(function (f) {
                var msgs = res.form[f] || {};
                Object.keys(msgs).forEach(function (k) { TigerDOM.notify(fb, msgs[k], { type: 'error', replace: false }); });
            });
        }
    }
    function api(method, params, btn) {
        var body = new URLSearchParams(params || {});
        body.set('module', 'backup'); body.set('service', 'backup'); body.set('method', method);
        body.set('_csrf', csrf());
        var task = function () {
            return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
                .then(function (r) { return r.json().catch(function () { return {}; }); });
        };
        return btn ? TigerButton.run(btn, task) : task();
    }
    function checkedComponents() {
        return Array.prototype.slice.call(document.querySelectorAll('#backup-components input:checked')).map(function (i) { return i.value; });
    }
    // The secrets switch and the restore controls are rendered only for a role that may use them
    // (server-computed, TIGER-511). Absent switch → the request carries no include_secrets at all, so
    // the server leaves the stored setting alone instead of reading a hidden "off".
    var secretsEl = document.getElementById('backup-secrets');
    function setSecrets(body) {
        if (secretsEl) { body.set('include_secrets', secretsEl.checked ? 1 : 0); }
    }

    // Back up now
    document.getElementById('backup-run').addEventListener('click', function () {
        var comps = checkedComponents();
        if (!comps.length) { TigerDOM.notify(fb, Tiger.t('selectComponent'), { type: 'error' }); return; }
        var body = new URLSearchParams();
        body.set('module', 'backup'); body.set('service', 'backup'); body.set('method', 'run');
        body.set('_csrf', csrf());
        body.set('disk', document.getElementById('backup-disk').value);
        setSecrets(body);
        comps.forEach(function (c) { body.append('components[]', c); });
        TigerButton.run(this, function () {
            return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body }).then(function (r) { return r.json().catch(function () { return {}; }); });
        }).then(function (res) { notify(res); if (res && res.result === 1) { setTimeout(function () { location.reload(); }, 900); } });
    });

    // Delete
    document.addEventListener('click', function (e) {
        var del = e.target.closest('[data-delete]');
        if (del) {
            TigerModal.confirm({ body: Tiger.t('confirmDeleteNamed', del.getAttribute('data-name')), variant: 'danger' }).then(function (ok) {
                if (!ok) { return; }
                api('remove', { backup_id: del.getAttribute('data-delete') }, del).then(function (res) { notify(res); if (res && res.result === 1) { setTimeout(function () { location.reload(); }, 700); } });
            });
        }
    });

    // Save schedule settings (components + destination come from the Create card)
    document.getElementById('backup-save-settings').addEventListener('click', function () {
        var comps = checkedComponents();
        var body = new URLSearchParams();
        body.set('module', 'backup'); body.set('service', 'backup'); body.set('method', 'saveSettings');
        body.set('_csrf', csrf());
        body.set('disk', document.getElementById('backup-disk').value);
        setSecrets(body);
        body.set('retention_max', document.getElementById('set-retention').value || '0');
        body.set('notify_enabled', document.getElementById('set-notify').checked ? 1 : 0);
        body.set('notify_email', document.getElementById('set-email').value || '');
        comps.forEach(function (c) { body.append('components[]', c); });
        TigerButton.run(this, function () {
            return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body }).then(function (r) { return r.json().catch(function () { return {}; }); });
        }).then(function (res) { notify(res); });
    });

    // Restore — only wired when the role may restore (the modal is rendered only then).
    var modalEl = document.getElementById('restore-modal');
    if (!modalEl) { return; }
    var modal = new bootstrap.Modal(modalEl);
    var confirmInput = document.getElementById('restore-confirm');
    var pending = null;   // { kind:'history', id } | { kind:'upload' }

    // Restore (history) — open the confirm modal
    document.addEventListener('click', function (e) {
        var r = e.target.closest('[data-restore]');
        if (r) {
            pending = { kind: 'history', id: r.getAttribute('data-restore') };
            document.getElementById('restore-target').textContent = r.getAttribute('data-name');
            confirmInput.value = ''; modal.show();
        }
    });

    // Restore from upload
    document.getElementById('restore-upload').addEventListener('click', function () {
        var f = document.getElementById('restore-file').files[0];
        if (!f) { TigerDOM.notify(fb, Tiger.t('chooseZip'), { type: 'error' }); return; }
        pending = { kind: 'upload', file: f };
        document.getElementById('restore-target').textContent = f.name;
        confirmInput.value = ''; modal.show();
    });

    // Confirm restore
    document.getElementById('restore-go').addEventListener('click', function () {
        if (confirmInput.value !== 'RESTORE') { confirmInput.classList.add('is-invalid'); return; }
        confirmInput.classList.remove('is-invalid');
        var btn = this;
        if (pending && pending.kind === 'history') {
            api('restore', { backup_id: pending.id, confirm: 'RESTORE' }, btn).then(function (res) {
                modal.hide(); notify(res); if (res && res.result === 1) { setTimeout(function () { location.reload(); }, 1500); }
            });
        } else if (pending && pending.kind === 'upload') {
            var fd = new FormData();
            fd.append('archive', pending.file); fd.append('confirm', 'RESTORE'); fd.append('_csrf', csrf());
            TigerButton.run(btn, function () {
                return fetch('/backup/index/upload', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd }).then(function (r) { return r.json().catch(function () { return {}; }); });
            }).then(function (res) { modal.hide(); notify(res); if (res && res.result === 1) { setTimeout(function () { location.reload(); }, 1500); } });
        }
    });
})();
