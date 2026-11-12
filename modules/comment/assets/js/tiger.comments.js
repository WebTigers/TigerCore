// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * tiger.comments.js — the reader-facing comment thread.
 *
 * Binds every `[data-comment-subject]` the shortcode emitted, loads the thread from /api, and posts
 * through the same endpoint. Server-rendered shell, client-loaded data — the house client/server
 * split, so a cached CMS page never bakes in a stale thread.
 */
(function () {
    if (window.__tigerComments) { return; }
    window.__tigerComments = true;

    // Self-contained: a public theme need not load Tiger's app JS (Tiger.t, TigerButton, TigerDOM), so
    // strings come from the shell's data-strings and busy/notify fall back to plain DOM when those are absent.
    var STR = {};

    function t(key, fallback) {
        if (STR[key]) { return STR[key]; }
        try { if (window.Tiger && Tiger.t) { var v = Tiger.t(key); if (v && v !== key) { return v; } } } catch (e) {}
        return fallback;
    }

    function notify(box, message, type) {
        if (window.TigerDOM && TigerDOM.notify) { TigerDOM.notify(box, message, { type: type }); return; }
        if (!box) { return; }
        var div = document.createElement('div');
        div.className = 'alert alert-' + (type === 'success' ? 'success' : 'danger');
        div.setAttribute('role', 'alert');
        div.textContent = message;
        box.appendChild(div);
    }

    function busy(btn, fn) {
        if (window.TigerButton && TigerButton.run) { return TigerButton.run(btn, fn); }
        btn.disabled = true; btn.setAttribute('aria-busy', 'true');
        var done = function () { btn.disabled = false; btn.removeAttribute('aria-busy'); };
        return fn().then(function (r) { done(); return r; }, function (e) { done(); throw e; });
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function api(data) {
        var body = new URLSearchParams(Object.assign({ module: 'comment', service: 'comment' }, data));
        return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
            .then(function (r) { return r.json().catch(function () { return {}; }); });
    }

    /** Whole stars only for INPUT — halves are an averaging artifact, never something you pick. */
    function ratingSelect() {
        var opts = '<option value="">' + esc(t('commentRatingNone', 'No rating')) + '</option>';
        for (var i = 5; i >= 1; i--) { opts += '<option value="' + i + '">' + i + '</option>'; }
        return '<select class="form-select form-select-sm w-auto tc-rating" aria-label="'
             + esc(t('commentRating', 'Rating')) + '">' + opts + '</select>';
    }

    function starsHTML(rating) {
        if (!rating) { return ''; }
        var html = '';
        for (var i = 1; i <= 5; i++) {
            html += '<i class="fa-' + (i <= rating ? 'solid' : 'regular') + ' fa-star"></i>';
        }
        return '<span class="text-warning me-2" role="img" aria-label="'
             + esc(rating + ' ' + t('commentOutOf5', 'out of 5')) + '">' + html + '</span>';
    }

    function commentHTML(c, canReply) {
        var badge = c.verified
            ? '<span class="badge text-bg-success-subtle text-success-emphasis border border-success-subtle ms-2">'
              + '<i class="fa-solid fa-circle-check me-1"></i>' + esc(t('commentVerified', 'Verified purchase')) + '</span>'
            : '';
        var mine = c.mine
            ? '<button type="button" class="btn btn-link btn-sm p-0 ms-2 tc-delete" data-id="' + esc(c.comment_id) + '">'
              + esc(t('commentDelete', 'Delete')) + '</button>'
            : '';
        // A reply is only offered where one can actually be POSTED — the subject's depth limit is the
        // server's rule, so the button disappears at the deepest level rather than failing on submit.
        var reply = canReply
            ? '<button type="button" class="btn btn-link btn-sm p-0 ms-2 tc-reply" data-id="' + esc(c.comment_id) + '">'
              + esc(t('commentReply', 'Reply')) + '</button>'
            : '';

        // The INDENT caps at 3 even when the tree is deeper: past that a thread walks off the right
        // edge of a phone, and the parentage is already carried by the reply header.
        return '<div class="tc-item py-3 border-bottom" data-id="' + esc(c.comment_id) + '"'
             + ' style="margin-left:' + (Math.min(c.depth || 0, 3) * 1.5) + 'rem">'
             + '<div class="d-flex align-items-center flex-wrap mb-1">'
             +   starsHTML(c.rating)
             +   '<strong>' + esc(c.author) + '</strong>' + badge
             +   '<span class="text-body-secondary small ms-2">' + esc((c.created_at || '').slice(0, 10)) + '</span>'
             +   reply + mine
             + '</div>'
             + (c.body ? '<div class="tc-body">' + esc(c.body).replace(/\n/g, '<br>') + '</div>' : '')
             + '<div class="tc-reply-mount"></div>'
             + '</div>';
    }

    /**
     * Order a flat list into a TREE — each comment immediately followed by its replies, depth-first.
     *
     * The server returns the thread in creation order, which for a nested conversation interleaves
     * replies with unrelated later top-level comments. Sorting client-side keeps the API a simple
     * ordered read and keeps the tree a rendering concern.
     */
    function tree(list) {
        var byParent = {}, out = [];
        list.forEach(function (c) {
            var key = c.parent_id || '';
            (byParent[key] = byParent[key] || []).push(c);
        });
        (function walk(parent) {
            (byParent[parent] || []).forEach(function (c) {
                out.push(c);
                walk(c.comment_id);
            });
        })('');
        // Anything whose parent was removed mid-thread would otherwise vanish — append the orphans
        // rather than silently dropping somebody's words.
        if (out.length < list.length) {
            var seen = {};
            out.forEach(function (c) { seen[c.comment_id] = true; });
            list.forEach(function (c) { if (!seen[c.comment_id]) { out.push(c); } });
        }
        return out;
    }

    function render(root, res) {
        var list = root.querySelector('.tiger-comments-list');
        var form = root.querySelector('.tiger-comments-form');
        var d    = (res && res.data) || {};
        var all  = d.comments || [];

        var maxDepth = typeof d.threading === 'number' ? d.threading : 0;
        var canPost  = d.canPost !== false;
        root.dataset.tcGuest = d.guest ? '1' : '';
        list.innerHTML = all.length
            ? tree(all).map(function (c) { return commentHTML(c, canPost && (c.depth || 0) < maxDepth); }).join('')
            : '<p class="text-body-secondary">' + esc(t('commentEmpty', 'No comments yet.')) + '</p>';

        // The reCAPTCHA box is for guests; a signed-in reader is already identified.
        var captcha = root.querySelector('.tiger-comments-captcha');
        if (captcha) { captcha.hidden = !(d.guest && canPost); }

        if (!canPost) {
            form.innerHTML = '<p class="text-body-secondary mt-3">'
                + esc(t('commentSignIn', 'Sign in to join the conversation.')) + '</p>';
            return;
        }

        // A guest is identified by name + email (the server requires both for a guest).
        var guestFields = d.guest
            ? '<div class="row g-2 mb-2">'
              + '<div class="col-sm"><input type="text" class="form-control tc-author-name" autocomplete="name" placeholder="'
              +   esc(t('commentName', 'Your name')) + '" aria-label="' + esc(t('commentName', 'Your name')) + '"></div>'
              + '<div class="col-sm"><input type="email" class="form-control tc-author-email" autocomplete="email" placeholder="'
              +   esc(t('commentEmail', 'Your email')) + '" aria-label="' + esc(t('commentEmail', 'Your email')) + '"></div>'
              + '</div>'
            : '';

        // The honeypot must be reachable by nothing a human uses: hidden from layout, from the
        // accessibility tree, and skipped by tab order. A `display:none` input some bots skip is
        // weaker than one that is simply never focusable.
        form.innerHTML =
            '<form class="tc-form mt-3" onsubmit="return false;" novalidate>'
          +   '<div class="tc-hp" aria-hidden="true" style="position:absolute;left:-9999px">'
          +     '<label>Leave this empty<input type="text" class="tc-hp-field" tabindex="-1" autocomplete="off"></label>'
          +   '</div>'
          +   guestFields
          +   '<textarea class="form-control mb-2 tc-body-input" rows="3" placeholder="'
          +     esc(t('commentBody', 'Write a comment…')) + '"></textarea>'
          +   '<div class="d-flex align-items-center gap-2">'
          +     (d.ratings ? ratingSelect() : '')
          +     '<button type="button" class="btn btn-primary btn-sm tc-submit">'
          +       esc(t('commentSubmit', 'Post')) + '</button>'
          +   '</div>'
          + '</form>';

        form.querySelector('.tc-form').dataset.rendered = String(Math.floor(Date.now() / 1000));
    }

    function load(root) {
        var subject = root.getAttribute('data-comment-subject') || '';
        return api({ method: 'list', subject: subject }).then(function (res) {
            if (!res || res.result !== 1) { return; }
            render(root, res);
        });
    }

    /**
     * What a guest post adds: the name + email from the main form and a reCAPTCHA token. v3 mints a fresh
     * token per post (the Post buttons are type="button", so the helper's submit hook never fires); v2
     * reads the checkbox. A signed-in reader adds nothing.
     */
    function guestExtras(root) {
        if (!root.dataset.tcGuest) { return Promise.resolve({}); }
        var extras = {
            author_name:  (root.querySelector('.tc-author-name') || {}).value || '',
            author_email: (root.querySelector('.tc-author-email') || {}).value || ''
        };
        var site = root.getAttribute('data-recaptcha-site') || '';
        if (!window.grecaptcha) { return Promise.resolve(extras); }
        if (!site) {
            try { extras['g-recaptcha-response'] = grecaptcha.getResponse() || ''; } catch (e) {}
            return Promise.resolve(extras);
        }
        return new Promise(function (resolve) {
            try {
                grecaptcha.ready(function () {
                    grecaptcha.execute(site, { action: 'comment_post' }).then(function (token) {
                        extras['g-recaptcha-response'] = token; resolve(extras);
                    }, function () { resolve(extras); });
                });
            } catch (e) { resolve(extras); }
        });
    }

    function bind(root) {
        var fb = root.querySelector('.tiger-comments-feedback');

        root.addEventListener('click', function (ev) {
            var submit  = ev.target.closest('.tc-submit');
            var del     = ev.target.closest('.tc-delete');
            var replyTo = ev.target.closest('.tc-reply');
            var send    = ev.target.closest('.tc-reply-send');

            if (replyTo) {
                var item  = replyTo.closest('.tc-item');
                var mount = item ? item.querySelector('.tc-reply-mount') : null;
                if (!mount) { return; }
                if (mount.innerHTML) { mount.innerHTML = ''; return; }   // a second click closes it
                mount.innerHTML =
                    '<div class="mt-2">'
                  + '<textarea class="form-control mb-2 tc-reply-body" rows="2" placeholder="'
                  +   esc(t('commentReply', 'Reply')) + '"></textarea>'
                  + '<button type="button" class="btn btn-sm btn-primary tc-reply-send" data-parent="'
                  +   esc(replyTo.getAttribute('data-id')) + '">' + esc(t('commentSubmit', 'Post')) + '</button>'
                  + '</div>';
                var box = mount.querySelector('.tc-reply-body');
                if (box) { box.focus(); }
                return;
            }

            if (send) {
                var mountEl = send.closest('.tc-reply-mount');
                var bodyEl  = mountEl ? mountEl.querySelector('.tc-reply-body') : null;
                busy(send, function () {
                    return guestExtras(root).then(function (extras) {
                        return api(Object.assign({
                            method:    'post',
                            subject:   root.getAttribute('data-comment-subject') || '',
                            body:      bodyEl ? bodyEl.value : '',
                            parent_id: send.getAttribute('data-parent'),
                            _t:        String(Math.floor(Date.now() / 1000) - 5)
                        }, extras));
                    });
                }).then(function (res) { after(res); });
                return;
            }

            if (!submit && !del) { return; }

            if (del) {
                busy(del, function () {
                    return api({ method: 'delete', comment_id: del.getAttribute('data-id') });
                }).then(function (res) { after(res); });
                return;
            }

            var form = root.querySelector('.tc-form');
            busy(submit, function () {
                return guestExtras(root).then(function (extras) {
                    return api(Object.assign({
                        method:     'post',
                        subject:    root.getAttribute('data-comment-subject') || '',
                        body:       (root.querySelector('.tc-body-input') || {}).value || '',
                        rating:     (root.querySelector('.tc-rating') || {}).value || '',
                        _hp:        (root.querySelector('.tc-hp-field') || {}).value || '',
                        _t:         form ? form.dataset.rendered : ''
                    }, extras));
                });
            }).then(function (res) { after(res); });
        });

        function after(res) {
            (res && res.messages || []).forEach(function (m) {
                notify(fb, m.message, m.class);
            });
            // Field-level refusals (a guest's name/email, the reCAPTCHA) arrive under `form`.
            if (res && res.form) {
                Object.keys(res.form).forEach(function (field) {
                    var msgs = res.form[field];
                    var msg  = (msgs && typeof msgs === 'object') ? Object.values(msgs)[0] : msgs;
                    if (msg) { notify(fb, String(msg), 'error'); }
                });
            }
            // A v2 checkbox token is single-use; re-arm it for the next try.
            if (root.querySelector('.tiger-comments-captcha .g-recaptcha') && window.grecaptcha && grecaptcha.reset) {
                try { grecaptcha.reset(); } catch (e) {}
            }
            if (res && res.result === 1) { load(root); }
        }
    }

    function init() {
        document.querySelectorAll('[data-comment-subject]').forEach(function (root) {
            if (root.dataset.tcBound) { return; }
            root.dataset.tcBound = '1';
            try { Object.assign(STR, JSON.parse(root.getAttribute('data-strings') || '{}')); } catch (e) {}
            bind(root);
            load(root);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
