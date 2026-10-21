// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
//
// TigerGit admin dashboard — the client for Git_Service_Repo / _Release / _Update over /api
// (TIGER-334 / TIGER-367). The same surface an agent drives over /api or MCP; this just draws it.
// House rules: no page-POST (every call is /api), TigerButton for busy states, TigerDOM for feedback,
// TigerModal for confirms, all interpolation escaped.
(function () {
    'use strict';

    var fb = document.getElementById('git-feedback');

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = (s === null || s === undefined) ? '' : String(s);
        return d.innerHTML;
    }

    function notify(msg, type, where) {
        TigerDOM.notify(where || fb, msg, { type: type || 'info' });
    }

    // One /api POST (mutations must be POST — the SameSite defense; reads ride POST fine too).
    function post(fields) {
        var body = new URLSearchParams();
        Object.keys(fields).forEach(function (k) {
            if (fields[k] !== undefined && fields[k] !== null) { body.set(k, fields[k]); }
        });
        return fetch('/api', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (r) { return r.json(); }).catch(function () {
            return { result: 0, messages: [{ message: 'Network error — please try again.', class: 'error' }] };
        });
    }

    // A form-backed POST (carries the form's CSRF token + fields) plus the message routing.
    function postForm(form, extra) {
        var body = new URLSearchParams(new FormData(form));
        Object.keys(extra).forEach(function (k) { body.set(k, extra[k]); });
        return fetch('/api', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        }).then(function (r) { return r.json(); }).catch(function () {
            return { result: 0, messages: [{ message: 'Network error — please try again.', class: 'error' }] };
        });
    }

    function flash(res, where) {
        (res && res.messages || []).forEach(function (m) { notify(m.message, m.class, where); });
    }

    function git(service, method, params) {
        var p = params || {};
        p.module = 'git'; p.service = service; p.method = method;
        return post(p);
    }

    // ---- connections list -------------------------------------------------------------------

    var list = document.getElementById('git-connections');

    function badge(text, cls) { return '<span class="badge ' + cls + ' me-1">' + esc(text) + '</span>'; }

    function connectionCard(c) {
        var statusCls = c.status === 'connected' ? 'text-bg-success' : (c.status === 'degraded' ? 'text-bg-warning' : 'text-bg-secondary');
        var verified = c.last_verified_at ? ('· verified ' + esc(c.last_verified_at)) : '';
        return '' +
        '<div class="col-md-6">' +
          '<div class="card h-100" data-module="' + esc(c.module_key) + '">' +
            '<div class="card-body">' +
              '<div class="d-flex justify-content-between align-items-start">' +
                '<div>' +
                  '<h3 class="h6 mb-1"><i class="fa-brands fa-github me-1"></i>' + esc(c.slug) + '</h3>' +
                  '<div class="small text-body-secondary">' + esc(c.module_key) + ' · ' + esc(c.default_branch) + ' ' + verified + '</div>' +
                '</div>' +
                '<div class="text-nowrap">' +
                  badge(c.transport, 'text-bg-info') +
                  badge(c.status, statusCls) +
                  (c.write_enabled ? badge('write', 'text-bg-dark') : '') +
                '</div>' +
              '</div>' +
              '<div class="git-update-state small mt-2 text-body-secondary"></div>' +
              '<div class="d-flex flex-wrap gap-2 mt-3">' +
                '<button type="button" class="btn btn-sm btn-outline-primary" data-act="check"><i class="fa-solid fa-rotate me-1"></i>Check updates</button>' +
                '<button type="button" class="btn btn-sm btn-outline-secondary" data-act="releases"><i class="fa-solid fa-tags me-1"></i>Releases</button>' +
                '<button type="button" class="btn btn-sm btn-outline-secondary" data-act="activity"><i class="fa-solid fa-clock-rotate-left me-1"></i>Activity</button>' +
                '<button type="button" class="btn btn-sm btn-outline-secondary" data-act="recheck"><i class="fa-solid fa-plug-circle-check me-1"></i>Re-check</button>' +
                '<button type="button" class="btn btn-sm btn-outline-danger ms-auto" data-act="disconnect"><i class="fa-solid fa-link-slash me-1"></i>Disconnect</button>' +
              '</div>' +
            '</div>' +
          '</div>' +
        '</div>';
    }

    function loadConnections() {
        return git('repo', 'list', {}).then(function (res) {
            if (!res || res.result !== 1) { flash(res); list.innerHTML = ''; return; }
            var rows = (res.data && res.data.connections) || [];
            if (!rows.length) {
                list.innerHTML = '<div class="col-12"><div class="card"><div class="card-body text-center text-body-secondary py-5">' +
                    '<i class="fa-solid fa-code-branch fs-2 d-block mb-2"></i>No modules are connected yet. Use “Connect a module” above.</div></div></div>';
                return;
            }
            list.innerHTML = rows.map(connectionCard).join('');
        });
    }

    // ---- per-connection actions (event delegation) ------------------------------------------

    if (list) {
        list.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-act]');
            if (!btn) { return; }
            var card = btn.closest('[data-module]');
            var mod = card ? card.getAttribute('data-module') : '';
            var act = btn.getAttribute('data-act');
            if (act === 'check')      { return doCheck(btn, mod, card); }
            if (act === 'recheck')    { return doRecheck(btn, mod); }
            if (act === 'releases')   { return openReleases(mod); }
            if (act === 'activity')   { return openActivity(mod); }
            if (act === 'disconnect') { return doDisconnect(mod); }
            if (act === 'apply')      { return doApply(btn, mod); }
            if (act === 'rollback')   { return doRollback(btn, mod); }
        });

        list.addEventListener('change', function (e) {
            var sel = e.target.closest('[data-act="policy"]');
            if (!sel) { return; }
            var mod = sel.closest('[data-module]').getAttribute('data-module');
            git('update', 'setPolicy', { module_key: mod, channel: sel.value }).then(function (res) {
                notify((res && res.result === 1) ? 'Update policy saved.' : 'Could not save the policy.', (res && res.result === 1) ? 'success' : 'error');
            });
        });
    }

    function updateStateHtml(u) {
        var policy = '<select class="form-select form-select-sm d-inline-block w-auto ms-2" data-act="policy" aria-label="Update policy">' +
            ['auto', 'notify', 'pinned'].map(function (p) {
                return '<option value="' + p + '"' + (u.channel === p ? ' selected' : '') + '>' + p + '</option>';
            }).join('') + '</select>';
        var line = 'Installed: <strong>' + esc(u.installed_version || '—') + '</strong> · latest approved: <strong>' + esc(u.latest_approved_version || '—') + '</strong>' + policy;
        if (u.pending) {
            line += ' <button type="button" class="btn btn-sm btn-success ms-2" data-act="apply"><i class="fa-solid fa-download me-1"></i>Apply ' + esc(u.latest_approved_version) + '</button>';
        } else {
            line += ' <span class="badge text-bg-success ms-2">Up to date</span>';
        }
        line += ' <button type="button" class="btn btn-sm btn-outline-secondary ms-1" data-act="rollback"><i class="fa-solid fa-rotate-left me-1"></i>Roll back</button>';
        return line;
    }

    function doCheck(btn, mod, card) {
        TigerButton.run(btn, function () { return git('update', 'check', { module_key: mod }); }).then(function (res) {
            var slot = card.querySelector('.git-update-state');
            if (!res || res.result !== 1) { flash(res); return; }
            var u = (res.data.updates || [])[0];
            slot.innerHTML = u ? updateStateHtml(u) : '<span class="text-body-secondary">No release information.</span>';
        });
    }

    function doApply(btn, mod) {
        TigerButton.run(btn, function () { return git('update', 'apply', { module_key: mod }); }).then(function (res) {
            flash(res);
            if (res && res.result === 1) { doCheckByModule(mod); }
        });
    }

    function doRollback(btn, mod) {
        TigerButton.run(btn, function () { return git('update', 'rollback', { module_key: mod }); }).then(function (res) {
            flash(res);
            if (res && res.result === 1) { doCheckByModule(mod); }
        });
    }

    function doCheckByModule(mod) {
        var card = list.querySelector('[data-module="' + (window.CSS && CSS.escape ? CSS.escape(mod) : mod) + '"]');
        if (card) { doCheck(card.querySelector('[data-act="check"]'), mod, card); }
    }

    function doRecheck(btn, mod) {
        TigerButton.run(btn, function () { return git('repo', 'recheck', { module_key: mod }); }).then(function (res) {
            flash(res);
            if (res && res.result === 1) { loadConnections(); }
        });
    }

    function doDisconnect(mod) {
        TigerModal.confirm({
            title: 'Disconnect module',
            body: 'Disconnect “' + mod + '” from its repository? The stored credential is revoked. The module’s files are untouched.',
            confirmLabel: 'Disconnect', variant: 'danger'
        }).then(function (ok) {
            if (!ok) { return; }
            git('repo', 'disconnect', { module_key: mod }).then(function (res) {
                flash(res);
                if (res && res.result === 1) { loadConnections(); }
            });
        });
    }

    // ---- connect ----------------------------------------------------------------------------

    var connectForm = document.getElementById('git-connect-form');
    var connectSave = document.getElementById('git-connect-save');
    if (connectSave) {
        connectSave.addEventListener('click', function () {
            TigerButton.run(this, function () {
                return postForm(connectForm, { module: 'git', service: 'repo', method: 'connect' });
            }).then(function (res) {
                flash(res);
                if (res && res.result === 1) {
                    connectForm.reset();
                    loadConnections();
                } else if (res && res.form) {
                    Object.keys(res.form).forEach(function (f) {
                        var el = connectForm.querySelector('[name="' + f + '"]');
                        if (el) { el.classList.add('is-invalid'); }
                    });
                }
            });
        });
    }

    // ---- releases + QA ----------------------------------------------------------------------

    var relModal, actModal;
    var relFb = document.getElementById('git-release-feedback');

    function relStatusBadge(s) {
        var map = { approved: 'text-bg-success', submitted: 'text-bg-warning', in_qa: 'text-bg-info', rejected: 'text-bg-danger', withdrawn: 'text-bg-secondary' };
        return '<span class="badge ' + (map[s] || 'text-bg-secondary') + '">' + esc(s) + '</span>';
    }

    function releaseRow(r) {
        var actions = '';
        if (r.status === 'submitted' || r.status === 'in_qa') {
            actions =
                '<button type="button" class="btn btn-sm btn-outline-success me-1" data-rel-act="approve" data-id="' + esc(r.module_release_id) + '" aria-label="Approve"><i class="fa-solid fa-check"></i></button>' +
                '<button type="button" class="btn btn-sm btn-outline-danger" data-rel-act="reject" data-id="' + esc(r.module_release_id) + '" aria-label="Reject"><i class="fa-solid fa-xmark"></i></button>';
        }
        return '<tr><td><code>' + esc(r.version) + '</code></td><td>' + relStatusBadge(r.status) + '</td><td class="text-end">' + actions + '</td></tr>';
    }

    function loadReleases(mod) {
        var rows = document.getElementById('git-releases-rows');
        rows.innerHTML = '<tr><td colspan="3" class="text-center text-body-secondary py-3"><i class="fa-solid fa-circle-notch fa-spin"></i></td></tr>';
        return git('release', 'list', { module_key: mod }).then(function (res) {
            if (!res || res.result !== 1) { flash(res, relFb); rows.innerHTML = ''; return; }
            var rel = res.data.releases || [];
            rows.innerHTML = rel.length ? rel.map(releaseRow).join('') : '<tr><td colspan="3" class="text-center text-body-secondary py-3">No releases yet.</td></tr>';
        });
    }

    function openReleases(mod) {
        document.getElementById('git-rel-module').value = mod;
        document.getElementById('git-releases-title').textContent = 'Releases — ' + mod;
        var tagForm = document.getElementById('git-tag-form');
        if (tagForm) { tagForm.reset(); document.getElementById('git-rel-module').value = mod; }
        if (!relModal) { relModal = new bootstrap.Modal(document.getElementById('git-releases-modal')); }
        loadReleases(mod).then(function () { relModal.show(); });
    }

    var tagBtn = document.getElementById('git-tag-btn');
    if (tagBtn) {
        tagBtn.addEventListener('click', function () {
            var mod = document.getElementById('git-rel-module').value;
            var version = document.getElementById('git-rel-version').value;
            TigerButton.run(this, function () { return git('release', 'tag', { module_key: mod, version: version }); }).then(function (res) {
                flash(res, relFb);
                if (res && res.result === 1 && res.data) {
                    document.getElementById('git-rel-sha').value = res.data.sha || '';
                    notify('Tagged ' + esc(res.data.tag) + ' — the commit sha is filled in; now submit it to QA.', 'info', relFb);
                    loadReleases(mod);
                }
            });
        });
    }

    var submitBtn = document.getElementById('git-submit-btn');
    if (submitBtn) {
        submitBtn.addEventListener('click', function () {
            var mod = document.getElementById('git-rel-module').value;
            var tagForm = document.getElementById('git-tag-form');
            TigerButton.run(this, function () {
                return postForm(tagForm, { module: 'git', service: 'release', method: 'submit', module_key: mod });
            }).then(function (res) {
                flash(res, relFb);
                if (res && res.result === 1) { loadReleases(mod); }
            });
        });
    }

    var relRowsEl = document.getElementById('git-releases-rows');
    if (relRowsEl) {
        relRowsEl.addEventListener('click', function (e) {
            var b = e.target.closest('[data-rel-act]');
            if (!b) { return; }
            var decision = b.getAttribute('data-rel-act');
            var id = b.getAttribute('data-id');
            var mod = document.getElementById('git-rel-module').value;
            TigerButton.run(b, function () { return git('release', 'decide', { module_release_id: id, decision: decision }); }).then(function (res) {
                flash(res, relFb);
                if (res && res.result === 1) { loadReleases(mod); }
            });
        });
    }

    // ---- activity ---------------------------------------------------------------------------

    function openActivity(mod) {
        var rows = document.getElementById('git-activity-rows');
        document.getElementById('git-activity-title').textContent = 'Activity — ' + mod;
        rows.innerHTML = '<li class="list-group-item text-center text-body-secondary"><i class="fa-solid fa-circle-notch fa-spin"></i></li>';
        if (!actModal) { actModal = new bootstrap.Modal(document.getElementById('git-activity-modal')); }
        actModal.show();
        git('repo', 'log', { module_key: mod }).then(function (res) {
            if (!res || res.result !== 1) { rows.innerHTML = '<li class="list-group-item">No activity.</li>'; return; }
            var entries = res.data.entries || [];
            rows.innerHTML = entries.length ? entries.map(function (e2) {
                var ok = e2.result === 'ok';
                return '<li class="list-group-item d-flex justify-content-between">' +
                    '<span><i class="fa-solid ' + (ok ? 'fa-circle-check text-success' : 'fa-circle-exclamation text-danger') + ' me-2"></i>' +
                    esc(e2.operation) + (e2.ref_after ? ' <code class="small">' + esc(String(e2.ref_after).slice(0, 10)) + '</code>' : '') +
                    (e2.error_code ? ' <span class="small text-danger">(' + esc(e2.error_code) + ')</span>' : '') + '</span>' +
                    '<span class="small text-body-secondary">' + esc(e2.created_at) + '</span></li>';
            }).join('') : '<li class="list-group-item text-body-secondary">No activity yet.</li>';
        });
    }

    // ---- boot -------------------------------------------------------------------------------

    loadConnections();
})();
