// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * access.user-index.js
 *
 * Users list — a DataTables grid sourced from /api (Access_Service_User::datatable), per the
 * client/server paradigm. Server-side search/sort/paginate; a Status filter toolbar (incl. Deleted)
 * via the tigerDataTable extraData hook. Action controls render off per-row ACL flags (can_edit /
 * can_delete); you can't delete your own account.
 *
 * Add + Edit share one tabbed modal (Details · Sign-in & status · Memberships), filled from
 * Access_Service_User::get and saved through ::save — identity, status, the Deleted switch and the
 * user's org memberships (org + role) in one validate→transaction call. A new user whose password is
 * left blank is emailed a set-your-password link by the server.
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
document.addEventListener('DOMContentLoaded', function () {
    if (typeof tigerDataTable !== 'function') { return; }
    var $   = jQuery;
    var esc = tigerDataTable.esc;
    var BADGE = { active: 'success', suspended: 'warning', deleted: 'danger' };
    function cap(s) { s = String(s || ''); return s.charAt(0).toUpperCase() + s.slice(1); }

    function api(method, extra) {
        var body = new URLSearchParams(Object.assign({ module: 'access', service: 'user', method: method }, extra || {}));
        return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
            .then(function (r) { return r.json(); });
    }

    var fStatus = document.getElementById('access-user-status');

    var table = tigerDataTable('#access-user-table', {
        service: { module: 'access', service: 'user', action: 'datatable' },
        order: [[5, 'desc']],
        language: { search: '', searchPlaceholder: Tiger.t('searchUsers') },
        extraData: function () { return { status: fStatus.value }; },
        columns: [
            { data: 'email', render: function (t, type, row) {
                return type !== 'display' ? t :
                    '<a href="#" data-user-edit="' + esc(row.user_id) + '" class="fw-semibold text-decoration-none">' + esc(t) + '</a>';
            } },
            { data: 'username', render: function (t, type) {
                if (type !== 'display') { return t; }
                return t ? '<code class="text-body-secondary">' + esc(t) + '</code>' : '<span class="text-body-secondary">—</span>';
            } },
            { data: 'roles', orderable: false, render: function (t, type) {
                if (type !== 'display') { return t; }
                if (!t) { return '<span class="text-body-secondary">—</span>'; }
                return String(t).split(',').map(function (r) {
                    return '<span class="badge text-bg-light border me-1">' + esc(r.trim()) + '</span>';
                }).join('');
            } },
            { data: 'org_count', render: function (t, type) {
                return type !== 'display' ? t : '<span class="badge rounded-pill text-bg-secondary">' + (parseInt(t, 10) || 0) + '</span>';
            } },
            { data: 'status', render: function (t, type, row) {
                if (type !== 'display') { return t; }
                if (row.deleted) { return '<span class="badge text-bg-' + BADGE.deleted + '">' + esc(Tiger.t('statusDeleted')) + '</span>'; }
                return '<span class="badge text-bg-' + (BADGE[t] || 'secondary') + '">' + esc(cap(t)) + '</span>';
            } },
            { data: 'created' },
            { data: null, orderable: false, searchable: false, className: 'text-end text-nowrap', render: function (t, type, row) {
                var html = '';
                if (row.can_edit) {
                    html += '<button type="button" data-user-edit="' + esc(row.user_id) + '" class="btn btn-sm btn-outline-secondary" title="' + esc(Tiger.t('edit')) + '" aria-label="' + esc(Tiger.t('edit')) + '"><i class="fa-solid fa-pen"></i></button> ';
                }
                if (row.deleted) { return html || '<span class="text-body-secondary">—</span>'; }
                if (row.can_delete) {
                    html += '<button type="button" class="btn btn-sm btn-outline-danger" data-user-delete="' + esc(row.user_id) + '" title="' + esc(Tiger.t('del')) + '" aria-label="' + esc(Tiger.t('del')) + '"><i class="fa-solid fa-trash"></i></button>';
                } else {
                    var noDel = esc(row.is_self ? Tiger.t('deleteSelf') : Tiger.t('notPermitted'));
                    html += '<button type="button" class="btn btn-sm btn-outline-danger" disabled title="' + noDel + '" aria-label="' + noDel + '"><i class="fa-solid fa-trash"></i></button>';
                }
                return html || '<span class="text-body-secondary">—</span>';
            } }
        ]
    });

    $('#access-user-status').on('change', function () { table.ajax.reload(); });
    $('#access-user-clear').on('click', function () { fStatus.value = ''; table.ajax.reload(); });

    $('#access-user-table tbody').on('click', '[data-user-delete]', function () {
        var btn = this;
        TigerModal.confirm({ title: Tiger.t('del'), body: Tiger.t('confirmDelUser'), confirmLabel: Tiger.t('del'), variant: 'danger' }).then(function (ok) {
            if (!ok) { return; }
            var tok  = document.querySelector('#access-user-token [name="_csrf"]');   // the grid's CSRF token (TIGER-528)
            var body = new URLSearchParams({ module: 'access', service: 'user', method: 'delete', user_id: btn.getAttribute('data-user-delete'), _csrf: tok ? tok.value : '' });
            TigerButton.run(btn, function () {
                return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
                    .then(function (r) { return r.json(); });
            })
                .then(function (res) {
                    // A refused delete says why — it must not just reload the grid as if it worked (TIGER-529).
                    if (!res || res.result !== 1) {
                        (res && res.messages || []).forEach(function (m) { TigerDOM.toast(m.message, { type: m.class || 'error' }); });
                    }
                    table.ajax.reload(null, false);
                })
                .catch(function () { table.ajax.reload(null, false); });
        });
    });

    // ── The Add / Edit modal ─────────────────────────────────────────────────────────────────────
    var modalEl = document.getElementById('access-user-modal');
    if (!modalEl || !window.bootstrap) { return; }
    var modal   = bootstrap.Modal.getOrCreateInstance(modalEl);
    var form    = document.getElementById('access-user-form');
    var fb      = document.getElementById('access-user-feedback');
    var rowsEl  = document.getElementById('access-membership-rows');
    var countEl = document.getElementById('access-membership-count');
    var orgIn   = document.getElementById('access-membership-org');
    var orgList = document.getElementById('access-membership-org-list');
    var roleSel = document.getElementById('access-membership-role');
    var addBtn  = document.getElementById('access-membership-add-btn');
    var selfNote = document.getElementById('access-membership-self');

    var roles = [], defaultOrg = null, optionsReady = null;
    var memberships = [];      // [{org_id, org_name, role}]
    var lockMemberships = false;
    var chosenOrg = null;      // the org picked in the add row

    function loadOptions() {
        if (!optionsReady) {
            optionsReady = api('options').then(function (res) {
                if (res && res.result === 1 && res.data) { roles = res.data.roles || []; defaultOrg = res.data.default_org || null; }
                roleSel.innerHTML = roles.map(function (r) {
                    return '<option value="' + esc(r) + '"' + (r === 'user' ? ' selected' : '') + '>' + esc(r) + '</option>';
                }).join('');
            });
        }
        return optionsReady;
    }

    function roleOptions(selected) {
        var list = roles.slice();
        if (selected && list.indexOf(selected) === -1) { list.unshift(selected); }   // a role held but not grantable still shows
        return list.map(function (r) {
            return '<option value="' + esc(r) + '"' + (r === selected ? ' selected' : '') + '>' + esc(r) + '</option>';
        }).join('');
    }

    function renderMemberships() {
        countEl.textContent = String(memberships.length);
        if (!memberships.length) {
            rowsEl.innerHTML = '<tr><td colspan="3" class="text-body-secondary small">' + esc(Tiger.t('mNone')) + '</td></tr>';
            return;
        }
        rowsEl.innerHTML = memberships.map(function (m, i) {
            var dis = lockMemberships ? ' disabled' : '';
            return '<tr><td>' + esc(m.org_name || m.org_id) + '</td>' +
                '<td><select class="form-select form-select-sm" data-m-role="' + i + '"' + dis + '>' + roleOptions(m.role) + '</select></td>' +
                '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" data-m-remove="' + i + '"' + dis +
                ' title="' + esc(Tiger.t('mRemove')) + '" aria-label="' + esc(Tiger.t('mRemove')) + '"><i class="fa-solid fa-xmark"></i></button></td></tr>';
        }).join('');
    }
    rowsEl.addEventListener('change', function (e) {
        var i = e.target.getAttribute('data-m-role');
        if (i !== null) { memberships[+i].role = e.target.value; }
    });
    rowsEl.addEventListener('click', function (e) {
        var b = e.target.closest('[data-m-remove]');
        if (b) { memberships.splice(+b.getAttribute('data-m-remove'), 1); renderMemberships(); }
    });

    // Org picker — a small typeahead over /api orgSearch (the caller's visible orgs only).
    var searchTimer = null;
    function showOrgs(list) {
        orgList.innerHTML = list.map(function (o) {
            return '<li><button type="button" class="dropdown-item" data-org-id="' + esc(o.org_id) + '" data-org-name="' + esc(o.name) + '">' +
                esc(o.name) + ' <span class="text-body-secondary small">' + esc(o.slug || '') + '</span></button></li>';
        }).join('');
        var open = list.length > 0;
        orgList.classList.toggle('show', open);
        orgIn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    orgIn.addEventListener('input', function () {
        chosenOrg = null;
        clearTimeout(searchTimer);
        var term = orgIn.value.trim();
        searchTimer = setTimeout(function () {
            api('orgSearch', { term: term }).then(function (res) {
                showOrgs((res && res.result === 1 && res.data && res.data.orgs) || []);
            }).catch(function () { showOrgs([]); });
        }, 200);
    });
    orgIn.addEventListener('focus', function () { if (!orgIn.value.trim()) { orgIn.dispatchEvent(new Event('input')); } });
    orgList.addEventListener('click', function (e) {
        var b = e.target.closest('[data-org-id]');
        if (!b) { return; }
        chosenOrg = { org_id: b.getAttribute('data-org-id'), org_name: b.getAttribute('data-org-name') };
        orgIn.value = chosenOrg.org_name;
        showOrgs([]);
    });
    document.addEventListener('click', function (e) { if (!e.target.closest('#access-membership-add')) { showOrgs([]); } });

    addBtn.addEventListener('click', function () {
        if (!chosenOrg) { TigerDOM.notify(fb, Tiger.t('pickOrg'), { type: 'error' }); orgIn.focus(); return; }
        var existing = memberships.filter(function (m) { return m.org_id === chosenOrg.org_id; })[0];
        if (existing) { existing.role = roleSel.value; }
        else { memberships.push({ org_id: chosenOrg.org_id, org_name: chosenOrg.org_name, role: roleSel.value }); }
        chosenOrg = null; orgIn.value = '';
        renderMemberships();
    });

    function setVal(name, value) {
        var el = form.elements[name];
        if (!el) { return; }
        el.value = value == null ? '' : value;
        if (el.tagName === 'SELECT') { el.dispatchEvent(new Event('change', { bubbles: true })); }   // syncs TigerCombo
    }
    function clearErrors() {
        fb.innerHTML = '';
        form.querySelectorAll('.is-invalid').forEach(function (e) { e.classList.remove('is-invalid'); });
    }
    function showTab(id) { bootstrap.Tab.getOrCreateInstance(document.getElementById(id)).show(); }

    function openModal(userId) {
        clearErrors();
        showTab('access-tab-details-btn');
        lockMemberships = false;
        selfNote.classList.add('d-none');
        document.getElementById('access-membership-add').classList.remove('d-none');
        loadOptions().then(function () {
            if (!userId) {
                document.getElementById('access-user-modal-title').textContent = Tiger.t('titleNew');
                document.getElementById('access-password-help').textContent = Tiger.t('pwNewHelp');
                ['user_id', 'email', 'username', 'new_password'].forEach(function (n) { setVal(n, ''); });
                setVal('locale', ''); setVal('timezone', ''); setVal('status', 'active');
                form.elements.deleted.checked = false;
                // Start the new user in the admin's own org; the admin changes it on the Memberships tab.
                memberships = defaultOrg ? [{ org_id: defaultOrg.org_id, org_name: defaultOrg.name, role: roles.indexOf('user') !== -1 ? 'user' : (roles[0] || '') }] : [];
                renderMemberships();
                modal.show();
                return;
            }
            document.getElementById('access-user-modal-title').textContent = Tiger.t('titleEdit');
            document.getElementById('access-password-help').textContent = Tiger.t('pwEditHelp');
            api('get', { user_id: userId }).then(function (res) {
                if (!res || res.result !== 1 || !res.data) {
                    (res && res.messages || []).forEach(function (m) { TigerDOM.toast(m.message, { type: m.class || 'error' }); });
                    return;
                }
                var u = res.data;
                setVal('user_id', u.user_id); setVal('email', u.email); setVal('username', u.username);
                setVal('locale', u.locale); setVal('timezone', u.timezone); setVal('status', u.status);
                setVal('new_password', '');
                form.elements.deleted.checked = !!u.deleted;
                memberships = (u.memberships || []).map(function (m) { return { org_id: m.org_id, org_name: m.org_name, role: m.role }; });
                lockMemberships = !!u.is_self;
                selfNote.classList.toggle('d-none', !u.is_self);
                document.getElementById('access-membership-add').classList.toggle('d-none', !!u.is_self);
                renderMemberships();
                modal.show();
            }).catch(function () { TigerDOM.toast(Tiger.t('networkError'), { type: 'error' }); });
        });
    }

    document.getElementById('access-user-new').addEventListener('click', function () { openModal(''); });
    $('#access-user-table tbody').on('click', '[data-user-edit]', function (e) {
        e.preventDefault();
        openModal(this.getAttribute('data-user-edit'));
    });

    document.getElementById('access-user-save').addEventListener('click', function () {
        var btn = this;
        clearErrors();
        form.elements.memberships.value = JSON.stringify(memberships.map(function (m) { return { org_id: m.org_id, role: m.role }; }));
        var fd = new URLSearchParams(new FormData(form));
        fd.set('module', 'access'); fd.set('service', 'user'); fd.set('method', 'save');
        TigerButton.run(btn, function () {
            return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
                .then(function (r) { return r.json(); });
        })
            .then(function (res) {
                if (res && res.result === 1) {
                    modal.hide();
                    (res.messages || []).forEach(function (m) { TigerDOM.toast(m.message, { type: m.class || 'success' }); });
                    table.ajax.reload(null, false);
                    return;
                }
                var firstTab = null;
                if (res && res.form) {
                    Object.keys(res.form).forEach(function (field) {
                        var input = form.querySelector('[name="' + field + '"]');
                        if (input && input.type !== 'hidden') { input.classList.add('is-invalid'); }
                        var pane = field === 'memberships' ? document.getElementById('access-tab-memberships') : (input && input.closest('.tab-pane'));
                        if (!firstTab && pane) { firstTab = pane.id + '-btn'; }
                        Object.keys(res.form[field] || {}).forEach(function (k) { TigerDOM.notify(fb, res.form[field][k], { type: 'error', replace: false }); });
                    });
                }
                if (firstTab) { showTab(firstTab); }
                var msgs = (res && res.messages) || [];
                msgs.forEach(function (m) { TigerDOM.notify(fb, m.message, { type: m.class || 'error', replace: false }); });
                if (!msgs.length && !(res && res.form)) { TigerDOM.notify(fb, Tiger.t('fixFields'), { type: 'error' }); }
            })
            .catch(function () { TigerDOM.notify(fb, Tiger.t('networkError'), { type: 'error' }); });
    });

    // /access/user/index/id/<uuid> (and the retired edit page's redirect) open straight into the modal.
    if (modalEl.getAttribute('data-open-user')) { openModal(modalEl.getAttribute('data-open-user')); }
    else if (modalEl.getAttribute('data-open-new')) { openModal(''); }
});
