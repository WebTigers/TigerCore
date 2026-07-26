// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Analytics dashboard — fetches the GA4 summary from /api (Analytics_Service_Reports::summary) and
 * paints the tiles, top-pages/channels tables, and the Chart.js traffic line. Loaded with `defer`
 * from the dashboard view, so it runs after the DOM is parsed AND after the admin layout's
 * parser-blocking Chart.js has executed (no load-order race). Only included when GA is connected.
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
    function num(n) { return Math.round(n || 0).toLocaleString(); }
    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

    function rows(el, data) {
        if (!data || !data.length) { el.innerHTML = '<tr><td class="text-body-secondary small py-3">No data yet.</td></tr>'; return; }
        el.innerHTML = data.map(function (r) {
            return '<tr><td class="text-truncate" style="max-width:1px;">' + esc(r.label) + '</td>'
                 + '<td class="text-end fw-semibold">' + num(r.value) + '</td></tr>';
        }).join('');
    }

    // A zeroed summary so the whole dashboard renders (0 tiles, a flat chart over the window, empty
    // tables) instead of gray boxes when there's no data yet or the API is momentarily unavailable.
    function zeroSeries(days) {
        var out = [], d = new Date();
        d.setDate(d.getDate() - (days - 1));
        for (var i = 0; i < days; i++) {
            var mm = ('0' + (d.getMonth() + 1)).slice(-2), dd = ('0' + d.getDate()).slice(-2);
            out.push({ date: d.getFullYear() + '-' + mm + '-' + dd, users: 0, views: 0 });
            d.setDate(d.getDate() + 1);
        }
        return out;
    }
    function zeroSummary() { return { totals: [0, 0, 0], series: zeroSeries(28), top_pages: [], top_sources: [] }; }
    function hasData(s) {
        var t = s.totals || [];
        return !!((t[0] || t[1] || t[2]) || (s.top_pages && s.top_pages.length)
            || (s.series || []).some(function (p) { return p.users || p.views; }));
    }

    var chart = null;
    function render(s) {
        var t = s.totals || [];
        document.getElementById('ga-users').textContent    = num(t[0]);
        document.getElementById('ga-sessions').textContent = num(t[1]);
        document.getElementById('ga-views').textContent    = num(t[2]);
        rows(document.getElementById('ga-pages'),   s.top_pages);
        rows(document.getElementById('ga-sources'), s.top_sources);

        var series = s.series || [];
        if (chart) { chart.destroy(); chart = null; }
        if (typeof Chart === 'undefined') { return; }   // Chart.js not loaded — tiles + tables still rendered
        chart = new Chart(document.getElementById('ga-chart'), {
            type: 'line',
            data: {
                labels: series.map(function (p) { return (p.date || '').slice(5); }),
                datasets: [
                    // Page views first (drawn beneath); Users last so its blue line + fill sit on top.
                    { label: 'Page views', data: series.map(function (p) { return p.views; }),
                      borderColor: muted, backgroundColor: rgba(muted, 0.3), fill: true, tension: 0.3, pointRadius: 0, borderWidth: 1.5 },
                    { label: 'Users', data: series.map(function (p) { return p.users; }),
                      borderColor: primary, backgroundColor: rgba(primary, 0.3), fill: true, tension: 0.3, pointRadius: 0, borderWidth: 2 }
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
                                }).reverse();   // Users listed first even though it's drawn last (on top)
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

    function note(msg) { TigerDOM.notify(document.getElementById('ga-feedback'), msg, { type: 'info' }); }

    var fd = new URLSearchParams({ module: 'analytics', service: 'reports', method: 'summary', days: '28' });
    fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
        .then(function (r) { return r.json().catch(function () { return {}; }); })
        .then(function (res) {
            if (res && res.result === 1 && res.data && res.data.summary) {
                render(res.data.summary);
                if (!hasData(res.data.summary)) {
                    note('No traffic recorded in the last 28 days yet — new data usually appears within a day of connecting.');
                }
                return;
            }
            // Couldn't load — still render the zero scaffold, and explain calmly (with the usual cause).
            render(zeroSummary());
            note('No Analytics data to show yet. If you just connected, it can take up to a day — otherwise make sure the Google Analytics Data API is enabled for your Google project.');
        })
        .catch(function () {
            render(zeroSummary());
            note('Couldn’t reach Analytics just now — showing zeros. Please try again shortly.');
        });
})();
