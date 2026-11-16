// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Analytics dashboard — fetches the org's NORMALIZED traffic report from /api
 * (Analytics_Service_Reports::report — Google Analytics, Matomo, or any Tiger_Analytics provider) and
 * paints the tiles, top-pages/referrers tables, and the Chart.js traffic line. Loaded with `defer`
 * from the dashboard view, so it runs after the DOM is parsed AND after the admin layout's
 * parser-blocking Chart.js has executed (no load-order race). Only included when a provider is set.
 * Strings arrive through the $this->i18n() carrier (Tiger.t).
 */
(function () {
    var css = getComputedStyle(document.documentElement);
    function tok(name, fb) { return (css.getPropertyValue(name) || fb).trim() || fb; }
    var primary = tok('--bs-primary', '#0d6efd');
    var muted   = tok('--bs-secondary-color', '#6c757d');
    var grid    = tok('--bs-border-color', '#dee2e6');
    // A color (hex or rgb[a]) at a given alpha — used for the translucent area fills below each line.
    function rgba(color, a) {
        var c = (color || '').trim();
        if (c.charAt(0) === '#') {
            var h = c.slice(1);
            if (h.length === 3) { h = h.charAt(0) + h.charAt(0) + h.charAt(1) + h.charAt(1) + h.charAt(2) + h.charAt(2); }
            var n = parseInt(h, 16);
            return 'rgba(' + ((n >> 16) & 255) + ',' + ((n >> 8) & 255) + ',' + (n & 255) + ',' + a + ')';
        }
        var m = c.match(/(\d+)[,\s]+(\d+)[,\s]+(\d+)/);
        return m ? 'rgba(' + m[1] + ',' + m[2] + ',' + m[3] + ',' + a + ')' : c;
    }
    function num(n) { return (n == null) ? '—' : Math.round(n).toLocaleString(); }
    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

    // rows: [{label, <valueKey>}] → a two-column table body.
    function rows(el, data, valueKey) {
        if (!data || !data.length) { el.innerHTML = '<tr><td class="text-body-secondary small py-3">' + esc(Tiger.t('noDataRow')) + '</td></tr>'; return; }
        el.innerHTML = data.map(function (r) {
            return '<tr><td class="text-truncate">' + esc(r.label) + '</td>'
                 + '<td class="text-end fw-semibold">' + num(r[valueKey]) + '</td></tr>';
        }).join('');
    }

    // A zeroed report so the whole dashboard renders (0 tiles, a flat chart over the window, empty
    // tables) instead of gray boxes when there's no data yet or the provider is momentarily unavailable.
    function zeroSeries(days) {
        var out = [], d = new Date();
        d.setDate(d.getDate() - (days - 1));
        for (var i = 0; i < days; i++) {
            var mm = ('0' + (d.getMonth() + 1)).slice(-2), dd = ('0' + d.getDate()).slice(-2);
            out.push({ date: d.getFullYear() + '-' + mm + '-' + dd, visitors: 0, pageviews: 0 });
            d.setDate(d.getDate() + 1);
        }
        return out;
    }
    function zeroReport() { return { totals: { visitors: 0, visits: 0, pageviews: 0 }, series: zeroSeries(28), top_pages: [], top_referrers: [] }; }
    function hasData(r) {
        var t = r.totals || {};
        return !!((t.visitors || t.visits || t.pageviews) || (r.top_pages && r.top_pages.length)
            || (r.series || []).some(function (p) { return p.visitors || p.pageviews; }));
    }

    var chart = null;
    function render(r) {
        var t = r.totals || {};
        document.getElementById('ga-users').textContent    = num(t.visitors);
        document.getElementById('ga-sessions').textContent = num(t.visits);
        document.getElementById('ga-views').textContent    = num(t.pageviews);
        rows(document.getElementById('ga-pages'),   r.top_pages, 'hits');
        rows(document.getElementById('ga-sources'), r.top_referrers, 'visits');

        var series = r.series || [];
        if (chart) { chart.destroy(); chart = null; }
        if (typeof Chart === 'undefined') { return; }   // Chart.js not loaded — tiles + tables still rendered
        chart = new Chart(document.getElementById('ga-chart'), {
            type: 'line',
            data: {
                labels: series.map(function (p) { return (p.date || '').slice(5); }),
                datasets: [
                    // `order` is the z-index: Chart.js draws lowest-order LAST, so order:0 = on top.
                    { label: Tiger.t('visitors'), order: 0, data: series.map(function (p) { return p.visitors; }),
                      borderColor: primary, backgroundColor: rgba(primary, 0.3), fill: true, tension: 0.3, pointRadius: 0, borderWidth: 2 },
                    { label: Tiger.t('pageviews'), order: 1, data: series.map(function (p) { return p.pageviews; }),
                      borderColor: muted, backgroundColor: rgba(muted, 0.15), fill: true, tension: 0.3, pointRadius: 0, borderWidth: 1.5 }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        // Filled circle = series shown, hollow circle = hidden (no strike-through). Click toggles.
                        labels: {
                            color: muted, usePointStyle: true, pointStyle: 'circle', boxWidth: 8, padding: 16,
                            generateLabels: function (ch) {
                                return ch.data.datasets.map(function (ds, i) {
                                    var on = ch.isDatasetVisible(i);
                                    return {
                                        text: ds.label,
                                        pointStyle: 'circle',
                                        fillStyle: on ? ds.borderColor : 'transparent',
                                        strokeStyle: ds.borderColor,
                                        lineWidth: 2,
                                        fontColor: muted,
                                        hidden: false,          // never render the label struck-through
                                        datasetIndex: i
                                    };
                                });
                            }
                        },
                        onClick: function (e, item, legend) {
                            var ci = legend.chart, i = item.datasetIndex;
                            ci.setDatasetVisibility(i, !ci.isDatasetVisible(i));
                            ci.update();
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: muted, maxTicksLimit: 8, autoSkip: true } },
                    y: { grid: { color: grid }, ticks: { color: muted, precision: 0 }, beginAtZero: true, suggestedMax: 10 }
                }
            }
        });
    }

    var box = document.getElementById('ga-feedback');
    function note(msg, type) { TigerDOM.notify(box, msg, { type: type || 'info' }); }
    function fixLink() { return ' <a href="' + esc(box.getAttribute('data-fix-url') || '/analytics/admin') + '" class="alert-link">' + esc(Tiger.t('fix')) + '</a>'; }

    var fd = new URLSearchParams({ module: 'analytics', service: 'reports', method: 'report', days: '28' });
    fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
        .then(function (r) { return r.json().catch(function () { return {}; }); })
        .then(function (res) {
            if (res && res.result === 1 && res.data && res.data.report) {
                render(res.data.report);
                if (!hasData(res.data.report)) { note(esc(Tiger.t('noData'))); }
                return;
            }
            // The provider answered with a problem the admin can fix (grant expired, token refused,
            // server unreachable, setup incomplete): say what the server said, and link to the fix.
            render(zeroReport());
            var m = res && res.messages && res.messages[0] && res.messages[0].message;
            note(esc(m || Tiger.t('unavailable')) + fixLink(), 'alert');
        })
        .catch(function () {
            render(zeroReport());
            note(esc(Tiger.t('unavailable')));
        });
})();
