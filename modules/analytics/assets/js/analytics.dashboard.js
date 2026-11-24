// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Analytics dashboard — fetches the org's NORMALIZED traffic report from /api
 * (Analytics_Service_Reports::report — Google Analytics, Matomo, or any Tiger_Analytics provider) and
 * paints the tiles, the tables and the Chart.js traffic line.
 *
 * Window + filters: a range preset (or a custom start/end) and up to four filters (campaign, channel,
 * device, country) drive every request. `options` says what the provider can do — a control it can't
 * honour stays hidden (Google Analytics: rolling windows only).
 *
 * Drill-downs: click a campaign (its keywords — e.g. which Facebook group), a channel (its sources), a top
 * page (its daily views + where its visitors came from), or a day on the chart (narrows the window to that
 * day; the chip undoes it). A campaign/channel drill can become a filter with "Show only this".
 *
 * Loaded with `defer`, after the admin layout's Chart.js. Strings arrive through the i18n carrier (Tiger.t).
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
    function $(id) { return document.getElementById(id); }

    function api(method, extra) {
        var body = new URLSearchParams(Object.assign({ module: 'analytics', service: 'reports', method: method }, extra || {}));
        return fetch('/api', { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: body })
            .then(function (r) { return r.json().catch(function () { return {}; }); });
    }

    // ── State: the window, the filters, what the provider can do ─────────────────────────────────────
    var caps = { range: false, filters: [], drilldowns: [] };
    var state = { preset: '28', start: '', end: '', filters: {}, day: null };   // day = {prev: {...}} while drilled
    function can(drill) { return caps.drilldowns.indexOf(drill) !== -1; }

    function ymd(d) { return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2); }
    function daysAgo(n) { var d = new Date(); d.setDate(d.getDate() - n); return d; }

    /** The request params for the current window + filters. */
    function windowParams() {
        var p = {}, now = new Date(), pr = state.preset;
        if (state.day) { p.start = state.day.date; p.end = state.day.date; }
        else if (/^\d+$/.test(pr)) {
            if (caps.range) { p.start = ymd(daysAgo(+pr - 1)); p.end = ymd(now); } else { p.days = pr; }
        }
        else if (pr === 'today')      { p.start = p.end = ymd(now); }
        else if (pr === 'yesterday')  { p.start = p.end = ymd(daysAgo(1)); }
        else if (pr === 'this_month') { p.start = ymd(new Date(now.getFullYear(), now.getMonth(), 1)); p.end = ymd(now); }
        else if (pr === 'last_month') { p.start = ymd(new Date(now.getFullYear(), now.getMonth() - 1, 1)); p.end = ymd(new Date(now.getFullYear(), now.getMonth(), 0)); }
        else if (pr === 'this_year')  { p.start = ymd(new Date(now.getFullYear(), 0, 1)); p.end = ymd(now); }
        else if (pr === 'custom' && state.start && state.end) { p.start = state.start; p.end = state.end; }
        else { p.days = '28'; }
        Object.keys(state.filters).forEach(function (k) { if (state.filters[k]) { p[k] = state.filters[k]; } });
        return p;
    }

    // ── Rendering ─────────────────────────────────────────────────────────────────────────────────────
    /** rows: [{label, <valueKey>}] → a two-column table; `drill(row)` returns {kind, item} to make a row clickable. */
    function rows(el, data, valueKey, drill) {
        if (!data || !data.length) { el.innerHTML = '<tr><td class="text-body-secondary small py-3">' + esc(Tiger.t('noDataRow')) + '</td></tr>'; return; }
        el.innerHTML = data.map(function (r) {
            var d = drill ? drill(r) : null;
            var attrs = d ? ' role="button" tabindex="0" data-drill-kind="' + esc(d.kind) + '" data-drill-item="' + esc(d.item) + '" data-drill-label="' + esc(d.label || r.label) + '"' : '';
            var icon  = d ? ' <i class="fa-solid fa-chevron-right small text-body-secondary ms-1" aria-hidden="true"></i>' : '';
            return '<tr' + attrs + '><td class="text-truncate">' + esc(r.label) + icon + '</td>'
                 + '<td class="text-end fw-semibold">' + num(r[valueKey]) + '</td></tr>';
        }).join('');
    }
    function channelLabel(key, fallback) {
        var k = { direct: 'chDirect', search: 'chSearch', website: 'chWebsite', social: 'chSocial', campaign: 'chCampaign' }[key];
        return k ? Tiger.t(k) : fallback;
    }

    // A zeroed report so the whole dashboard renders (0 tiles, a flat chart, empty tables) instead of gray
    // boxes when there's no data yet or the provider is momentarily unavailable.
    function zeroReport() {
        var out = [], d = daysAgo(27);
        for (var i = 0; i < 28; i++) { out.push({ date: ymd(d), visitors: 0, pageviews: 0 }); d.setDate(d.getDate() + 1); }
        return { totals: { visitors: 0, visits: 0, pageviews: 0 }, series: out, top_pages: [], top_referrers: [] };
    }
    function hasData(r) {
        var t = r.totals || {};
        return !!((t.visitors || t.visits || t.pageviews) || (r.top_pages && r.top_pages.length)
            || (r.series || []).some(function (p) { return p.visitors || p.pageviews; }));
    }

    var chart = null;
    function legendLabels(ch) {
        return ch.data.datasets.map(function (ds, i) {
            var on = ch.isDatasetVisible(i);
            return { text: ds.label, pointStyle: 'circle', fillStyle: on ? ds.borderColor : 'transparent', strokeStyle: ds.borderColor,
                     lineWidth: 2, fontColor: muted, hidden: false, datasetIndex: i };
        });
    }
    function lineChart(canvas, series, datasets, onDay) {
        return new Chart(canvas, {
            type: 'line',
            data: { labels: series.map(function (p) { return (p.date || '').slice(5); }), datasets: datasets },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                onClick: onDay ? function (e, els) { if (els && els.length) { onDay(series[els[0].index].date); } } : undefined,
                onHover: onDay ? function (e, els) { e.native.target.style.cursor = (els && els.length) ? 'pointer' : 'default'; } : undefined,
                plugins: {
                    legend: {
                        // Filled circle = series shown, hollow circle = hidden (no strike-through). Click toggles.
                        labels: { color: muted, usePointStyle: true, pointStyle: 'circle', boxWidth: 8, padding: 16, generateLabels: legendLabels },
                        onClick: function (e, item, legend) { var ci = legend.chart; ci.setDatasetVisibility(item.datasetIndex, !ci.isDatasetVisible(item.datasetIndex)); ci.update(); }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { color: muted, maxTicksLimit: 8, autoSkip: true } },
                    y: { grid: { color: grid }, ticks: { color: muted, precision: 0 }, beginAtZero: true, suggestedMax: 10 }
                }
            }
        });
    }

    function render(r) {
        var t = r.totals || {};
        $('ga-users').textContent    = num(t.visitors);
        $('ga-sessions').textContent = num(t.visits);
        $('ga-views').textContent    = num(t.pageviews);
        rows($('ga-pages'), r.top_pages, 'hits', can('page') ? function (p) { return p.url ? { kind: 'page', item: p.url } : null; } : null);

        // Matomo reports channels (drillable); a provider without them keeps the plain referrer list.
        var title = $('ga-sources-title');
        if (r.channels) {
            title.textContent = title.getAttribute('data-channels-title');
            var ch = r.channels.map(function (c) { return { label: channelLabel(c.key, c.label), key: c.key, visits: c.visits }; });
            rows($('ga-sources'), ch, 'visits', can('channel') ? function (c) { return c.key ? { kind: 'channel', item: c.key, label: c.label } : null; } : null);
        } else {
            rows($('ga-sources'), r.top_referrers, 'visits');
        }
        $('ga-campaigns-wrap').classList.toggle('d-none', !r.campaigns);
        if (r.campaigns) {
            rows($('ga-campaigns'), r.campaigns, 'visits', can('campaign') ? function (c) { return { kind: 'campaign', item: c.label }; } : null);
        }

        var series = r.series || [];
        if (chart) { chart.destroy(); chart = null; }
        if (typeof Chart === 'undefined') { return; }   // Chart.js not loaded — tiles + tables still rendered
        chart = lineChart($('ga-chart'), series, [
            // `order` is the z-index: Chart.js draws lowest-order LAST, so order:0 = on top.
            { label: Tiger.t('visitors'), order: 0, data: series.map(function (p) { return p.visitors; }),
              borderColor: primary, backgroundColor: rgba(primary, 0.3), fill: true, tension: 0.3, pointRadius: 0, pointHitRadius: 8, borderWidth: 2 },
            { label: Tiger.t('pageviews'), order: 1, data: series.map(function (p) { return p.pageviews; }),
              borderColor: muted, backgroundColor: rgba(muted, 0.15), fill: true, tension: 0.3, pointRadius: 0, pointHitRadius: 8, borderWidth: 1.5 }
        ], can('day') && !state.day ? drillDay : null);
    }

    var box = $('ga-feedback');
    function note(msg, type) { TigerDOM.notify(box, msg, { type: type || 'info' }); }
    function fixLink() { return ' <a href="' + esc(box.getAttribute('data-fix-url') || '/analytics/admin') + '" class="alert-link">' + esc(Tiger.t('fix')) + '</a>'; }

    // ── Loading ───────────────────────────────────────────────────────────────────────────────────────
    var optionsReady = Promise.resolve();
    var lastRange = null;
    function load() {
        box.innerHTML = '';
        summarize();
        // The report and the options travel together; rendering waits for both (clickable rows need the capabilities).
        return Promise.all([api('report', windowParams()), optionsReady]).then(function (all) {
            var res = all[0];
            lastRange = res && res.data && res.data.report ? res.data.report.range : null;
            summarize();
            if (res && res.result === 1 && res.data && res.data.report) {
                render(res.data.report);
                if (!hasData(res.data.report)) { note(esc(Tiger.t('noData'))); }
                return;
            }
            // The provider answered with a problem the admin can fix (grant expired, token refused, server
            // unreachable, setup incomplete): say what the server said, and link to the fix.
            render(zeroReport());
            var m = res && res.messages && res.messages[0] && res.messages[0].message;
            note(esc(m || Tiger.t('unavailable')) + fixLink(), 'alert');
        }).catch(function () { render(zeroReport()); note(esc(Tiger.t('unavailable'))); });
    }

    /** Refill the campaign / device / country choices for the current window, keeping what's selected. */
    function loadOptions() {
        var p = windowParams();
        ['campaign', 'channel', 'device', 'country'].forEach(function (k) { delete p[k]; });
        return api('options', p).then(function (res) {
            var d = (res && res.result === 1 && res.data) || {};
            caps = Object.assign({ range: false, filters: [], drilldowns: [] }, d.capabilities || {});
            // Presets that need a real date range hide for a rolling-window-only provider.
            document.querySelectorAll('#ga-range option[data-needs-range]').forEach(function (o) { o.hidden = !caps.range; o.disabled = !caps.range; });
            ['campaign', 'channel', 'device', 'country'].forEach(function (f) {
                var wrap = document.querySelector('[data-filter-wrap="' + f + '"]');
                var on = caps.filters.indexOf(f) !== -1;
                wrap.classList.toggle('d-none', !on);
                var opts = (d.filters || {})[f];
                if (!on || !opts) { return; }   // channel's choices are fixed in the markup
                var sel = $('ga-f-' + f), keep = sel.value;
                sel.innerHTML = '<option value="">' + esc(Tiger.t('all')) + '</option>' + opts.map(function (o) {
                    return '<option value="' + esc(o.value) + '"' + (o.value === keep ? ' selected' : '') + '>' + esc(o.label) + '</option>';
                }).join('');
                if (keep && sel.value !== keep) {   // keep a chosen value even if this window has none of it
                    sel.insertAdjacentHTML('beforeend', '<option value="' + esc(keep) + '" selected>' + esc(keep) + '</option>');
                }
            });
            $('ga-clear-wrap').classList.toggle('d-none', !caps.filters.length);
        }).catch(function () {});
    }

    /** The "what am I looking at" line: the drilled day (with an undo chip) and the active filters. */
    function summarize() {
        var el = $('ga-active'), bits = [];
        if (lastRange && !state.day) { bits.push('<span class="me-1"><i class="fa-regular fa-calendar me-1" aria-hidden="true"></i>' + esc(lastRange.start) + ' – ' + esc(lastRange.end) + '</span>'); }
        if (state.day) {
            bits.push('<button type="button" class="btn btn-sm btn-primary py-0" id="ga-day-undo">' + esc(Tiger.t('dayChip', state.day.date)) +
                ' <i class="fa-solid fa-xmark ms-1" aria-hidden="true"></i></button>');
        }
        Object.keys(state.filters).forEach(function (k) {
            if (!state.filters[k]) { return; }
            var sel = $('ga-f-' + k), opt = sel && sel.selectedOptions[0];
            bits.push('<span class="badge text-bg-light border">' + esc(opt ? opt.textContent : state.filters[k]) + '</span>');
        });
        el.innerHTML = bits.join(' ');
        var undo = $('ga-day-undo');
        if (undo) { undo.addEventListener('click', function () { state.day = null; load(); }); }
    }

    function drillDay(date) { state.day = { date: date }; load(); }

    // ── The drill-down modal ──────────────────────────────────────────────────────────────────────────
    var modalEl = $('ga-drill-modal'), drillChart = null, current = null;
    function openDrill(kind, item, label) {
        if (!modalEl || !window.bootstrap) { return; }
        current = { kind: kind, item: item };
        var titleKey = { campaign: 'drillCampaign', channel: 'drillChannel', page: 'drillPage' }[kind];
        $('ga-drill-title').textContent = Tiger.t(titleKey, label || item);
        $('ga-drill-feedback').innerHTML = '';
        $('ga-drill-rows').innerHTML = '<tr><td class="text-body-secondary small py-3">' + esc(Tiger.t('drillLoading')) + '</td></tr>';
        $('ga-drill-chart-wrap').classList.add('d-none');
        // "Show only this" turns a campaign/channel drill into a dashboard filter (when the provider filters on it).
        $('ga-drill-only').classList.toggle('d-none', !(kind !== 'page' && caps.filters.indexOf(kind) !== -1));
        if (drillChart) { drillChart.destroy(); drillChart = null; }
        bootstrap.Modal.getOrCreateInstance(modalEl).show();

        api('breakdown', Object.assign({ kind: kind, item: item }, windowParams())).then(function (res) {
            var b = res && res.result === 1 && res.data && res.data.breakdown;
            if (!b) {
                var m = res && res.messages && res.messages[0] && res.messages[0].message;
                $('ga-drill-rows').innerHTML = '';
                TigerDOM.notify($('ga-drill-feedback'), m || Tiger.t('unavailable'), { type: 'error' });
                return;
            }
            var list = (b.rows || []).map(function (r) { return { label: r.key ? channelLabel(r.key, r.label) : r.label, visits: r.visits }; });
            if (kind === 'channel' && item === 'direct') {
                $('ga-drill-rows').innerHTML = '<tr><td class="text-body-secondary small py-3">' + esc(Tiger.t('drillDirect')) + '</td></tr>';
            } else if (!list.length) {
                $('ga-drill-rows').innerHTML = '<tr><td class="text-body-secondary small py-3">' + esc(Tiger.t('drillNone')) + '</td></tr>';
            } else {
                rows($('ga-drill-rows'), list, 'visits');
            }
            if (b.series && typeof Chart !== 'undefined') {
                $('ga-drill-chart-wrap').classList.remove('d-none');
                drillChart = lineChart($('ga-drill-chart'), b.series, [
                    { label: Tiger.t('pageviews'), data: b.series.map(function (p) { return p.pageviews; }),
                      borderColor: primary, backgroundColor: rgba(primary, 0.25), fill: true, tension: 0.3, pointRadius: 0, borderWidth: 2 }
                ], null);
            }
        }).catch(function () { TigerDOM.notify($('ga-drill-feedback'), Tiger.t('unavailable'), { type: 'error' }); });
    }
    function onRow(e) {
        var tr = e.target.closest('[data-drill-kind]');
        if (!tr || (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ')) { return; }
        if (e.type === 'keydown') { e.preventDefault(); }
        openDrill(tr.getAttribute('data-drill-kind'), tr.getAttribute('data-drill-item'), tr.getAttribute('data-drill-label'));
    }
    ['ga-pages', 'ga-sources', 'ga-campaigns'].forEach(function (id) {
        var el = $(id);
        if (el) { el.addEventListener('click', onRow); el.addEventListener('keydown', onRow); }
    });
    $('ga-drill-only').addEventListener('click', function () {
        if (!current) { return; }
        var sel = $('ga-f-' + current.kind);
        if (sel && !Array.prototype.some.call(sel.options, function (o) { return o.value === current.item; })) {
            sel.insertAdjacentHTML('beforeend', '<option value="' + esc(current.item) + '">' + esc(current.item) + '</option>');
        }
        if (sel) { sel.value = current.item; }
        state.filters[current.kind] = current.item;
        bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        load();
    });

    // ── Toolbar wiring ────────────────────────────────────────────────────────────────────────────────
    var rangeSel = $('ga-range');
    function showCustom(on) { document.querySelectorAll('[data-custom-range]').forEach(function (el) { el.classList.toggle('d-none', !on); }); }
    rangeSel.addEventListener('change', function () {
        state.preset = rangeSel.value; state.day = null;
        if (state.preset === 'custom') {
            showCustom(true);
            if (!$('ga-start').value) { $('ga-start').value = ymd(daysAgo(27)); $('ga-end').value = ymd(new Date()); }
            return;   // wait for Apply
        }
        showCustom(false);
        optionsReady = loadOptions(); load();
    });
    $('ga-end').max = $('ga-start').max = ymd(new Date());
    $('ga-apply').addEventListener('click', function () {
        if (!$('ga-start').value || !$('ga-end').value) { return; }
        state.start = $('ga-start').value; state.end = $('ga-end').value; state.day = null;
        optionsReady = loadOptions(); load();
    });
    document.querySelectorAll('[data-filter]').forEach(function (sel) {
        sel.addEventListener('change', function () { state.filters[sel.getAttribute('data-filter')] = sel.value; load(); });
    });
    $('ga-clear').addEventListener('click', function () {
        state.filters = {}; state.day = null;
        document.querySelectorAll('[data-filter]').forEach(function (sel) { sel.value = ''; });
        load();
    });

    // First paint: the options and the report go out together (a rolling preset needs no capabilities to ask for).
    optionsReady = loadOptions();
    load();
})();
