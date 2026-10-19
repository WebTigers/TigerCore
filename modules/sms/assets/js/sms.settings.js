/* SMS settings — provider field toggle, save + test over /api (house primitives). */
(function () {
    'use strict';
    var sel  = document.getElementById('sms-provider');
    var fb   = document.getElementById('sms-feedback');
    if (!sel) { return; }

    function T(k) { return (window.Tiger && Tiger.t) ? Tiger.t(k) : k; }
    function notify(msg, type) { if (window.TigerDOM) { TigerDOM.notify(fb, msg, { type: type || 'info' }); } }

    /** Show only the selected provider's credential fields. */
    function toggle() {
        var cur = sel.value;
        document.querySelectorAll('[data-sms-fields]').forEach(function (el) {
            el.hidden = (el.getAttribute('data-sms-fields') !== cur);
        });
    }
    sel.addEventListener('change', toggle);
    toggle();

    /** Collect the save payload: provider + only the selected provider's fields. */
    function savePayload() {
        var fd = new URLSearchParams();
        fd.set('module', 'sms'); fd.set('service', 'settings'); fd.set('method', 'save');
        fd.set('provider', sel.value);
        document.querySelectorAll('[data-sms-field="' + sel.value + '"]').forEach(function (inp) {
            fd.set(inp.getAttribute('name'), inp.value);
        });
        return fd;
    }

    function post(fd) {
        return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
            .then(function (r) { return r.json().catch(function () { return {}; }); });
    }

    document.getElementById('sms-save').addEventListener('click', function () {
        var btn = this;
        TigerButton.run(btn, function () { return post(savePayload()); }).then(function (res) {
            if (res && res.result === 1) {
                notify(T('sms.settings.saved'), 'success');
                // a successful save may flip the "test" button's availability — reload keeps it honest
                setTimeout(function () { window.location.reload(); }, 700);
                return;
            }
            (res && res.messages || [{ message: T('core.api.error.general'), class: 'error' }]).forEach(function (m) { notify(m.message, m.class); });
        }).catch(function () { notify('Network error — please try again.', 'error'); });
    });

    var testBtn = document.getElementById('sms-test');
    if (testBtn) {
        testBtn.addEventListener('click', function () {
            var btn = this;
            var fd = new URLSearchParams();
            fd.set('module', 'sms'); fd.set('service', 'settings'); fd.set('method', 'test');
            fd.set('to', (document.getElementById('sms-test-to') || {}).value || '');
            fd.set('message', (document.getElementById('sms-test-msg') || {}).value || '');
            TigerButton.run(btn, function () { return post(fd); }).then(function (res) {
                if (res && res.result === 1) { notify(T('sms.settings.test_sent'), 'success'); return; }
                (res && res.messages || [{ message: T('sms.settings.error.test'), class: 'error' }]).forEach(function (m) { notify(m.message, m.class); });
            }).catch(function () { notify('Network error — please try again.', 'error'); });
        });
    }
})();
