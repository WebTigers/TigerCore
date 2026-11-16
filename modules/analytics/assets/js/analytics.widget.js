// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * analytics.widget.js — the dashboard "Traffic" widget (Analytics_Widget_Traffic).
 *
 * Finds every `[data-analytics-traffic]` shell, fetches the org's NORMALIZED report over /api
 * (analytics/reports/report — whichever provider the org chose: Google Analytics, Matomo, …) and paints
 * the two headline numbers + a Chart.js line. A provider error (unreachable, token refused, grant
 * expired) replaces the chart with the server's message and a link to fix it. Strings arrive as
 * data-* attributes on the shell; colors are read from the --bs-* tokens so it follows the theme.
 *
 * Registered by the widget with pageScript() (defer), so it runs after the DOM is parsed and after the
 * admin layout's Chart.js. Numbers still render when Chart.js is absent.
 */
(function () {
    'use strict';

    function esc(s) { var d = document.createElement('div'); d.textContent = (s == null ? '' : s); return d.innerHTML; }
    function num(n) { return (n == null) ? '—' : Math.round(n).toLocaleString(); }
    function rgba(c, a) {
        c = (c || '').trim();
        if (c.charAt(0) === '#') {
            var h = c.slice(1);
            if (h.length === 3) { h = h.charAt(0) + h.charAt(0) + h.charAt(1) + h.charAt(1) + h.charAt(2) + h.charAt(2); }
            var n = parseInt(h, 16);
            return 'rgba(' + ((n >> 16) & 255) + ',' + ((n >> 8) & 255) + ',' + (n & 255) + ',' + a + ')';
        }
        var m = c.match(/(\d+)[,\s]+(\d+)[,\s]+(\d+)/);
        return m ? 'rgba(' + m[1] + ',' + m[2] + ',' + m[3] + ',' + a + ')' : c;
    }

    function showError(box, msg) {
        var err = box.querySelector('[data-error]');
        var chart = box.querySelector('[data-chart]');
        if (chart && chart.parentNode) { chart.parentNode.classList.add('d-none'); }
        box.querySelectorAll('[data-metric]').forEach(function (m) { m.textContent = '—'; });
        if (!err) { return; }
        err.className = 'alert alert-warning small mb-0 mt-2';
        err.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-2" aria-hidden="true"></i>' + esc(msg)
            + ' <a class="alert-link" href="' + esc(box.getAttribute('data-fix-url')) + '">' + esc(box.getAttribute('data-fix-label')) + '</a>';
    }

    function draw(box, r) {
        var t = r.totals || {}, series = r.series || [];
        var v = box.querySelector('[data-metric="visitors"]'), p = box.querySelector('[data-metric="pageviews"]');
        if (v) { v.textContent = num(t.visitors != null ? t.visitors : t.visits); }
        if (p) { p.textContent = num(t.pageviews); }
        if (typeof Chart === 'undefined') { return; }
        var css = getComputedStyle(document.documentElement);
        function tok(n, fb) { return (css.getPropertyValue(n) || fb).trim() || fb; }
        var primary = tok('--bs-primary', '#0d6efd'), muted = tok('--bs-secondary-color', '#6c757d'), grid = tok('--bs-border-color', '#dee2e6');
        new Chart(box.querySelector('[data-chart]'), {
            type: 'line',
            data: {
                labels: series.map(function (x) { return (x.date || '').slice(5); }),
                datasets: [
                    { label: box.getAttribute('data-label-visitors'), order: 0, data: series.map(function (x) { return x.visitors; }),
                      borderColor: primary, backgroundColor: rgba(primary, 0.3), fill: true, tension: 0.35, pointRadius: 0, borderWidth: 2 },
                    { label: box.getAttribute('data-label-pageviews'), order: 1, data: series.map(function (x) { return x.pageviews; }),
                      borderColor: muted, backgroundColor: rgba(muted, 0.15), fill: true, tension: 0.35, pointRadius: 0, borderWidth: 1.5 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: { legend: { display: true, position: 'bottom', labels: { color: muted, usePointStyle: true, pointStyle: 'circle', boxWidth: 8 } } },
                scales: {
                    x: { grid: { display: false }, ticks: { color: muted, maxTicksLimit: 6, autoSkip: true, font: { size: 10 } } },
                    y: { grid: { color: grid }, ticks: { color: muted, precision: 0, maxTicksLimit: 4, font: { size: 10 } }, beginAtZero: true, suggestedMax: 10 }
                }
            }
        });
    }

    function load(box) {
        if (box.getAttribute('data-loaded')) { return; }
        box.setAttribute('data-loaded', '1');
        var fd = new URLSearchParams({ module: 'analytics', service: 'reports', method: 'report', days: box.getAttribute('data-days') || '28' });
        fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
            .then(function (r) { return r.json().catch(function () { return {}; }); })
            .then(function (res) {
                if (res && res.result === 1 && res.data && res.data.report) { draw(box, res.data.report); return; }
                var m = res && res.messages && res.messages[0] && res.messages[0].message;
                showError(box, m || box.getAttribute('data-error-fallback'));
            })
            .catch(function () { showError(box, box.getAttribute('data-error-fallback')); });
    }

    function init() { document.querySelectorAll('[data-analytics-traffic]').forEach(load); }
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', init); } else { init(); }
}());
