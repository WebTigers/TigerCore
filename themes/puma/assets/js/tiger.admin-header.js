// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * tiger.admin-header.js
 *
 * PUMA admin header (MIT, Tiger-original).
 *
 * Registered by the view with $this->pageScript(); the view owns the markup, this owns the
 * behavior. Localized strings arrive through the $this->i18n() carrier (Tiger.t).
 */
// Live-update the header avatar the moment it changes elsewhere (e.g. the profile Basic tab),
// no reload. Any avatar source fires window 'tiger:avatar-changed' with { url } (null = removed).
window.addEventListener('tiger:avatar-changed', function (e) {
    var img = document.getElementById('header-avatar-img');
    var ico = document.getElementById('header-avatar-icon');
    if (!img || !ico) { return; }
    var url = e.detail && e.detail.url;
    if (url) { img.src = url; img.classList.remove('d-none'); ico.classList.add('d-none'); }
    else     { img.classList.add('d-none'); ico.classList.remove('d-none'); }
});
// Live-update the header label when the display name changes (Basic tab); falls back to the email.
window.addEventListener('tiger:display-name-changed', function (e) {
    var el = document.getElementById('header-user-name');
    if (!el) { return; }
    var name = e.detail && e.detail.name;
    el.textContent = name || el.getAttribute('data-email') || el.getAttribute('data-fallback') || '';
});
