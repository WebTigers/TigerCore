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

    function t(key, fallback) {
        try { if (window.Tiger && Tiger.t) { var v = Tiger.t(key); if (v && v !== key) { return v; } } } catch (e) {}
        return fallback;
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

    function commentHTML(c) {
        var badge = c.verified
            ? '<span class="badge text-bg-success-subtle text-success-emphasis border border-success-subtle ms-2">'
              + '<i class="fa-solid fa-circle-check me-1"></i>' + esc(t('commentVerified', 'Verified purchase')) + '</span>'
            : '';
        var mine = c.mine
            ? '<button type="button" class="btn btn-link btn-sm p-0 ms-2 tc-delete" data-id="' + esc(c.comment_id) + '">'
              + esc(t('commentDelete', 'Delete')) + '</button>'
            : '';
        return '<div class="tc-item py-3 border-bottom" style="margin-left:' + (Math.min(c.depth || 0, 3) * 1.5) + 'rem">'
             + '<div class="d-flex align-items-center mb-1">'
             +   starsHTML(c.rating)
             +   '<strong>' + esc(c.author) + '</strong>' + badge
             +   '<span class="text-body-secondary small ms-2">' + esc((c.created_at || '').slice(0, 10)) + '</span>'
             +   mine
             + '</div>'
             + (c.body ? '<div class="tc-body">' + esc(c.body).replace(/\n/g, '<br>') + '</div>' : '')
             + '</div>';
    }

    function render(root, res) {
        var list = root.querySelector('.tiger-comments-list');
        var form = root.querySelector('.tiger-comments-form');
        var d    = (res && res.data) || {};
        var all  = d.comments || [];

        list.innerHTML = all.length
            ? all.map(commentHTML).join('')
            : '<p class="text-body-secondary">' + esc(t('commentEmpty', 'No comments yet.')) + '</p>';

        // The honeypot must be reachable by nothing a human uses: hidden from layout, from the
        // accessibility tree, and skipped by tab order. A `display:none` input some bots skip is
        // weaker than one that is simply never focusable.
        form.innerHTML =
            '<form class="tc-form mt-3" onsubmit="return false;" novalidate>'
          +   '<div class="tc-hp" aria-hidden="true" style="position:absolute;left:-9999px">'
          +     '<label>Leave this empty<input type="text" class="tc-hp-field" tabindex="-1" autocomplete="off"></label>'
          +   '</div>'
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

    function bind(root) {
        var fb = root.querySelector('.tiger-comments-feedback');

        root.addEventListener('click', function (ev) {
            var submit = ev.target.closest('.tc-submit');
            var del    = ev.target.closest('.tc-delete');
            if (!submit && !del) { return; }

            if (del) {
                TigerButton.run(del, function () {
                    return api({ method: 'delete', comment_id: del.getAttribute('data-id') });
                }).then(function (res) { after(res); });
                return;
            }

            var form = root.querySelector('.tc-form');
            TigerButton.run(submit, function () {
                return api({
                    method:     'post',
                    subject:    root.getAttribute('data-comment-subject') || '',
                    body:       (root.querySelector('.tc-body-input') || {}).value || '',
                    rating:     (root.querySelector('.tc-rating') || {}).value || '',
                    _hp:        (root.querySelector('.tc-hp-field') || {}).value || '',
                    _t:         form ? form.dataset.rendered : ''
                });
            }).then(function (res) { after(res); });
        });

        function after(res) {
            (res && res.messages || []).forEach(function (m) {
                TigerDOM.notify(fb, m.message, { type: m.class });
            });
            if (res && res.result === 1) { load(root); }
        }
    }

    function init() {
        document.querySelectorAll('[data-comment-subject]').forEach(function (root) {
            if (root.dataset.tcBound) { return; }
            root.dataset.tcBound = '1';
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
