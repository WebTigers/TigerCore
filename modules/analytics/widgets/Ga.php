<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Analytics_Widget_Ga — the admin-dashboard "Traffic" widget. Renders a shell (top-line numbers + a
 * sparkline canvas) that fetches its data over /api (Analytics_Service_Reports) and draws it with the
 * vendored Chart.js — so the dashboard never blocks on a (possibly cold) GA API call. Shows a connect
 * prompt when Google Analytics isn't wired up yet.
 *
 * @api
 */
class Analytics_Widget_Ga
{
    /**
     * Render the widget card body.
     *
     * @return string HTML
     */
    public function render(): string
    {
        if (!class_exists('Tiger_Google_Analytics') || !Tiger_Google_Analytics::isConnected()) {
            return '<div class="text-center text-body-secondary py-3">'
                 . '<i class="fa-solid fa-chart-line fs-3 mb-2 d-block opacity-50"></i>'
                 . '<p class="small mb-2">Connect Google Analytics to see traffic.</p>'
                 . '<a href="/analytics/admin" class="btn btn-sm btn-outline-primary">Set up</a></div>';
        }

        $id = 'gaw-' . substr(md5(uniqid('', true)), 0, 8);
        ob_start(); ?>
<div id="<?= $id ?>-body" style="position:relative;">
    <div class="d-flex justify-content-between align-items-start" style="position:relative; z-index:2; pointer-events:none;">
        <div style="pointer-events:auto;"><div class="fs-2 fw-bold lh-1" id="<?= $id ?>-users"><span class="placeholder col-6"></span></div>
             <div class="small text-body-secondary">active users &middot; 28d</div></div>
        <div id="<?= $id ?>-legend" class="d-flex gap-3 align-items-center small mt-1" style="pointer-events:auto;"></div>
        <div class="text-end" style="pointer-events:auto;"><div class="fs-2 fw-bold lh-1" id="<?= $id ?>-views"><span class="placeholder col-6"></span></div>
             <div class="small text-body-secondary">page views</div></div>
    </div>
    <div style="height:190px; margin-top:-2.5rem;"><canvas id="<?= $id ?>-chart"></canvas></div>
    <div class="mt-2"><a href="/analytics/admin/dashboard" class="small text-decoration-none">View dashboard <i class="fa-solid fa-arrow-right ms-1"></i></a></div>
</div>
<script>
(function () {
    function draw() {
        var css = getComputedStyle(document.documentElement);
        function tok(n, fb) { return (css.getPropertyValue(n) || fb).trim() || fb; }
        var primary = tok('--bs-primary', '#0d6efd');
        var muted   = tok('--bs-secondary-color', '#6c757d');
        var grid    = tok('--bs-border-color', '#dee2e6');
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
        var fd = new URLSearchParams({ module: 'analytics', service: 'reports', method: 'summary', days: '28' });
        fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd })
            .then(function (r) { return r.json().catch(function () { return {}; }); })
            .then(function (res) {
                if (!res || res.result !== 1 || !res.data || !res.data.summary) { return; }
                var s = res.data.summary, t = s.totals || [], series = s.series || [];
                document.getElementById('<?= $id ?>-users').textContent = Math.round(t[0] || 0).toLocaleString();
                document.getElementById('<?= $id ?>-views').textContent = Math.round(t[2] || 0).toLocaleString();
                if (typeof Chart === 'undefined') { return; }   // numbers still show; just skip the chart
                var chart = new Chart(document.getElementById('<?= $id ?>-chart'), {
                    type: 'line',
                    data: {
                        labels: series.map(function (p) { return (p.date || '').slice(5); }),
                        datasets: [
                            { label: 'Users', order: 0, data: series.map(function (x) { return x.users; }),
                              borderColor: primary, backgroundColor: rgba(primary, 0.3), fill: true, tension: 0.35, pointRadius: 0, borderWidth: 2 },
                            { label: 'Page views', order: 1, data: series.map(function (x) { return x.views; }),
                              borderColor: muted, backgroundColor: rgba(muted, 0.15), fill: true, tension: 0.35, pointRadius: 0, borderWidth: 1.5 }
                        ]
                    },
                    options: {
                        responsive: true, maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        // Custom HTML legend (in the numbers row) drives visibility, so Chart's own is off.
                        plugins: { legend: { display: false }, tooltip: { enabled: false } },
                        scales: {
                            x: { grid: { display: false }, ticks: { color: muted, maxTicksLimit: 6, autoSkip: true, font: { size: 10 } } },
                            y: { grid: { color: grid }, ticks: { color: muted, precision: 0, maxTicksLimit: 4, font: { size: 10 } }, beginAtZero: true, suggestedMax: 10 }
                        }
                    }
                });
                // Build the legend into the numbers row: filled circle = shown, hollow = hidden; click toggles.
                var box = document.getElementById('<?= $id ?>-legend');
                if (box) {
                    box.innerHTML = '';
                    chart.data.datasets.forEach(function (ds, i) {
                        var b = document.createElement('button');
                        b.type = 'button';
                        b.className = 'btn btn-link btn-sm p-0 text-decoration-none d-inline-flex align-items-center gap-1';
                        b.style.color = muted;
                        function paint() {
                            var on = chart.isDatasetVisible(i);
                            b.innerHTML = '<span style="display:inline-block;width:10px;height:10px;border-radius:50%;border:2px solid '
                                + ds.borderColor + ';background:' + (on ? ds.borderColor : 'transparent') + ';"></span>' + ds.label;
                        }
                        b.addEventListener('click', function () { chart.setDatasetVisibility(i, !chart.isDatasetVisible(i)); chart.update(); paint(); });
                        paint();
                        box.appendChild(b);
                    });
                }
            }).catch(function () {});
    }
    // Run after parsing so the admin layout's Chart.js (loaded later in the page) is available.
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', draw); } else { draw(); }
})();
</script>
<?php
        return (string) ob_get_clean();
    }
}
