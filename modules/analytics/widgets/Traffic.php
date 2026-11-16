<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Analytics_Widget_Traffic — the admin-dashboard "Traffic" widget, provider-agnostic (1.61).
 *
 * Renders from the org's reporting provider (Tiger_Analytics — Google Analytics or Matomo), so it is
 * the same card whichever one measures the site. Three server-rendered states:
 *
 *  - no provider        → an empty state: "Choose an analytics provider" + a button to the setup screen;
 *  - chosen, not set up → "Finish setting up <provider>" + a button to its setup panel;
 *  - ready              → a shell (two headline numbers + a chart canvas) whose data arrives async over
 *                         /api (Analytics_Service_Reports::report, normalized), so a cold provider call
 *                         never blocks the dashboard. A provider that errors (unreachable, token refused,
 *                         grant expired) shows its message in the card with a link to fix it.
 *
 * The widget id stays `analytics.traffic` (dashboards keep their layout). Behaviour is an asset
 * (analytics.widget.js) registered with pageScript(); strings travel as data-* attributes — no inline
 * script or style.
 *
 * @api
 */
class Analytics_Widget_Traffic
{
    /** The setup screen the empty/unconfigured states link to. */
    const SETUP_URL = '/analytics/admin';

    /** The widget's script asset. */
    const SCRIPT = '/_modules/analytics/js/analytics.widget.js';

    /**
     * Render the widget card body.
     *
     * @return string HTML
     */
    public function render(): string
    {
        $orgId    = (string) Tiger_Model_Config::currentOrgId();
        $provider = Tiger_Analytics::provider($orgId);
        $adapter  = $provider !== '' ? Tiger_Analytics::adapter($provider) : null;

        if (!$adapter) {
            return $this->_prompt('fa-chart-line', 'analytics.widget.choose', 'analytics.widget.choose_cta', self::SETUP_URL);
        }
        if (!$adapter->isConfigured($orgId)) {
            return $this->_prompt('fa-plug', 'analytics.widget.finish_setup', 'analytics.widget.setup',
                self::SETUP_URL . '?provider=' . rawurlencode($provider), $adapter->label());
        }

        $this->_registerScript();
        $e  = static function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES); };
        $id = 'atw-' . substr(md5(uniqid('', true)), 0, 8);
        return '<div class="analytics-traffic" id="' . $id . '" data-analytics-traffic data-days="28"'
             . ' data-provider="' . $e($provider) . '"'
             . ' data-label-visitors="' . $e($this->_t('analytics.widget.series_visitors')) . '"'
             . ' data-label-pageviews="' . $e($this->_t('analytics.widget.series_pageviews')) . '"'
             . ' data-error-fallback="' . $e($this->_t('analytics.reports.error')) . '"'
             . ' data-fix-label="' . $e($this->_t('analytics.widget.fix')) . '"'
             . ' data-fix-url="' . $e(self::SETUP_URL . '?provider=' . rawurlencode($provider)) . '">'
             . '<div class="d-flex justify-content-between align-items-start gap-3 mb-2">'
             .   '<div><div class="fs-2 fw-bold lh-1" data-metric="visitors"><span class="placeholder col-6"></span></div>'
             .   '<div class="small text-body-secondary">' . $e($this->_t('analytics.widget.visitors_28d')) . '</div></div>'
             .   '<div class="text-end"><div class="fs-2 fw-bold lh-1" data-metric="pageviews"><span class="placeholder col-6"></span></div>'
             .   '<div class="small text-body-secondary">' . $e($this->_t('analytics.widget.page_views')) . '</div></div>'
             . '</div>'
             . '<div class="ratio ratio-21x9"><canvas data-chart aria-label="' . $e($this->_t('analytics.card.traffic')) . '" role="img"></canvas></div>'
             . '<div data-error class="d-none" role="status"></div>'
             . '<div class="mt-2 d-flex justify-content-between align-items-center">'
             .   '<span class="small text-body-secondary"><i class="fa-solid fa-circle-nodes me-1" aria-hidden="true"></i>' . $e($adapter->label()) . '</span>'
             .   '<a href="/analytics/admin/dashboard" class="small text-decoration-none">' . $e($this->_t('analytics.widget.view_dashboard'))
             .   ' <i class="fa-solid fa-arrow-right ms-1" aria-hidden="true"></i></a>'
             . '</div>'
             . '</div>';
    }

    /** An empty/next-step state: icon, one line, one button. */
    private function _prompt(string $icon, string $lineKey, string $ctaKey, string $href, string $label = ''): string
    {
        $e    = static function ($s) { return htmlspecialchars((string) $s, ENT_QUOTES); };
        $line = $this->_t($lineKey);
        if ($label !== '') {
            $line = sprintf($line, $label);
        }
        return '<div class="text-center text-body-secondary py-3" data-analytics-empty>'
             . '<i class="fa-solid ' . $e($icon) . ' fs-3 mb-2 d-block opacity-50" aria-hidden="true"></i>'
             . '<p class="small mb-2">' . $e($line) . '</p>'
             . '<a href="' . $e($href) . '" class="btn btn-sm btn-outline-primary">' . $e($this->_t($ctaKey)) . '</a></div>';
    }

    /**
     * Register the widget's script with the page's view (the layout emits it). A widget has no view of
     * its own, so it uses the MVC view the layout renders against; with none (a lazy /api body, a test)
     * there is nothing to register into and the card stays a static shell.
     */
    private function _registerScript(): void
    {
        try {
            $layout = class_exists('Zend_Layout', false) ? Zend_Layout::getMvcInstance() : null;
            $view   = $layout ? $layout->getView() : null;
            if ($view === null) {
                $vr   = Zend_Controller_Action_HelperBroker::getStaticHelper('viewRenderer');
                $view = $vr ? $vr->view : null;
            }
            if ($view !== null) {
                $view->pageScript(self::SCRIPT, ['defer' => true]);
            }
        } catch (Throwable $e) {
            // no view in play — the shell renders without its script
        }
    }

    /** Translate a key with the shared translator (a widget has no view helper). */
    private function _t(string $key): string
    {
        try {
            return (string) Zend_Registry::get('Zend_Translate')->translate($key);
        } catch (Throwable $e) {
            return $key;
        }
    }
}
