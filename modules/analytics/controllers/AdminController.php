<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Analytics_AdminController — the analytics setup screen. Step 1 chooses the reporting provider
 * (Tiger_Analytics: Google Analytics or Matomo); step 2 is that provider's setup — GA's form + OAuth
 * connect, or the matomo module's own form. Thin per ADMIN.md: renders forms pre-filled from the live
 * config; every save is an /api call (Analytics_Service_Analytics, Matomo_Service_Matomo). Its own ACL
 * resource (admin+), so access is grantable independently.
 */
class Analytics_AdminController extends Tiger_Controller_Admin_Action
{
    /**
     * Admin shell (layout) comes from the base; keep the explicit init cascade.
     *
     * @return void
     */
    public function init()
    {
        parent::init();
    }

    /**
     * Render the settings form, pre-filled from the live config.
     *
     * @return void
     */
    public function indexAction()
    {
        // The caller's org tier (its own value, else the install-wide one) — the tier the save writes
        // (TIGER-511), so the screen shows exactly what it will save.
        $form = new Analytics_Form_Settings();
        $form->populate([
            'ga4_measurement_id' => (string) Tiger_Model_Config::currentValue('tiger.analytics.ga4.measurement_id', ''),
        ]);

        $b = static function ($key, $default = false) {
            $v = Tiger_Model_Config::currentValue($key);
            return $v !== null ? filter_var((string) $v, FILTER_VALIDATE_BOOLEAN) : $default;
        };

        $this->view->title           = 'Analytics — Tiger Admin';
        $this->view->form            = $form;
        $this->view->enabled         = $b('tiger.analytics.enabled', false);
        $this->view->excludeSignedIn = $b('tiger.analytics.exclude_signed_in', true);

        // Reporting/OAuth connection state for the "Connect Google" section.
        $this->view->ga = [
            'connected'    => Tiger_Google_Analytics::isConnected(),
            'configurable' => Tiger_Google_Analytics::isConfigurable(),
            'mode'         => Tiger_Google_Analytics::mode(),
            'client_id'    => Tiger_Google_Analytics::clientId(),
            'property_id'  => Tiger_Google_Analytics::propertyId(),
            'redirect_uri' => $this->_redirectUri(),
        ];
        $this->view->flash = $this->_takeFlash();

        // Step 1 — the reporting provider (Tiger_Analytics, org-scoped). `overview` carries no secret.
        // `?provider=` only chooses which setup panel opens (deep link from /matomo/admin); it saves nothing.
        $orgId    = (string) Tiger_Model_Config::currentOrgId();
        $overview = Tiger_Analytics::overview($orgId);
        $want     = strtolower((string) $this->getRequest()->getParam('provider', ''));
        $this->view->analytics = $overview;
        $this->view->panel     = isset($overview['providers'][$want]) ? $want : $overview['provider'];

        // The Matomo panel renders the matomo module's OWN form (one setup, one service) — only when
        // that module is active. The token is write-only: the screen gets `hasToken`, never the value.
        $this->view->matomo = null;
        if (class_exists('Matomo_Form_Settings')) {
            $mForm = new Matomo_Form_Settings();
            $mForm->populate([
                'matomo_url'                    => (string) Tiger_Model_Config::currentValue('tiger.matomo.url', ''),
                'matomo_site_id'                => (string) Tiger_Model_Config::currentValue('tiger.matomo.site_id', ''),
                'matomo_affiliate_dimension_id' => (string) Tiger_Model_Config::currentValue('tiger.matomo.affiliate.dimension_id', '1'),
            ]);
            $this->view->matomo = [
                'form'            => $mForm,
                'enabled'         => $b('tiger.matomo.enabled', false),
                'excludeSignedIn' => $b('tiger.matomo.exclude_signed_in', true),
                'hasToken'        => (new Tiger_Analytics_Adapter_Matomo())->hasToken($orgId),
            ];
        }
    }

    /**
     * `/analytics/admin/connect` — kick off the Google OAuth consent flow (offline access).
     *
     * @return void
     */
    public function connectAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        if (!Tiger_Google_Analytics::isConfigurable()) {
            $msg = Tiger_Google_Analytics::mode() === Tiger_Google_Analytics::MODE_BROKER
                ? 'Enter your GA4 property ID first, then connect.'
                : 'Enter your OAuth client ID, secret, and GA4 property ID first, then connect.';
            $this->_flash($msg, 'error');
            $this->_redirect('/analytics/admin');
            return;
        }
        $ns = new Zend_Session_Namespace('AnalyticsOauth');

        if (Tiger_Google_Analytics::mode() === Tiger_Google_Analytics::MODE_BROKER) {
            // PKCE: keep the verifier server-side; only its challenge travels to the broker.
            $verifier     = bin2hex(random_bytes(32));
            $ns->verifier = $verifier;
            unset($ns->state);
            $this->_redirect(Tiger_Google_Analytics::brokerAuthUrl(
                $this->_redirectUri(), Tiger_Google_Analytics::pkceChallenge($verifier)));
            return;
        }

        // BYO: run Google's consent flow directly against the operator's own OAuth client.
        $state     = bin2hex(random_bytes(16));
        $ns->state = $state;
        unset($ns->verifier);
        $this->_redirect(Tiger_Google_Analytics::authUrl($this->_redirectUri(), $state));
    }

    /**
     * `/analytics/admin/callback` — the connect flow redirects back here: the broker with ?handoff
     * (broker mode), or Google with ?code & ?state (BYO mode). Complete the token exchange.
     *
     * @return void
     */
    public function callbackAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        $req = $this->getRequest();
        $ns  = new Zend_Session_Namespace('AnalyticsOauth');

        if (Tiger_Google_Analytics::mode() === Tiger_Google_Analytics::MODE_BROKER) {
            $verifier = (string) ($ns->verifier ?? '');
            unset($ns->verifier);
            $handoff  = (string) $req->getParam('handoff');
            if ((string) $req->getParam('error') !== '') {
                $this->_flash('Google authorization was cancelled or denied.', 'error');
            } elseif ($verifier === '' || $handoff === '') {
                $this->_flash('The connection request expired. Please try again.', 'error');
            } else {
                $res = Tiger_Google_Analytics::exchangeHandoff($handoff, $verifier);
                $this->_flash($res['ok'] ? 'Connected to Google Analytics.' : ('Could not connect: ' . $res['error']), $res['ok'] ? 'success' : 'error');
            }
            $this->_redirect('/analytics/admin');
            return;
        }

        // BYO: Google redirected back with ?code & ?state.
        $expected = (string) ($ns->state ?? '');
        unset($ns->state);
        if ((string) $req->getParam('error') !== '') {
            $this->_flash('Google authorization was cancelled or denied.', 'error');
        } elseif ($expected === '' || !hash_equals($expected, (string) $req->getParam('state'))) {
            $this->_flash('The connection request expired or did not match. Please try again.', 'error');
        } else {
            $res = Tiger_Google_Analytics::exchangeCode((string) $req->getParam('code'), $this->_redirectUri());
            $this->_flash($res['ok'] ? 'Connected to Google Analytics.' : ('Could not connect: ' . $res['error']), $res['ok'] ? 'success' : 'error');
        }
        $this->_redirect('/analytics/admin');
    }

    /**
     * `/analytics/admin/disconnect` — forget the stored Google connection (drop the refresh token).
     *
     * @return void
     */
    public function disconnectAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        // Two different outcomes, said differently: "we revoked it" and "we could not reach Google to
        // revoke it — here is where you can" are not the same message (TIGER-117).
        $out = Tiger_Google_Analytics::disconnect();
        if (!empty($out['revoked'])) {
            $this->_flash($this->view->t('analytics.disconnected'), 'success');
        } else {
            $this->_flash($this->view->t('analytics.disconnected_not_revoked'), 'alert');
        }
        $this->_redirect('/analytics/admin');
    }

    /**
     * `/analytics/admin/dashboard` — the in-app GA reports dashboard. Thin: renders the shell; the data
     * arrives over /api (Analytics_Service_Reports) and Chart.js draws it.
     *
     * @return void
     */
    public function dashboardAction()
    {
        $this->view->title     = 'Analytics — Dashboard';
        // Provider-agnostic since 1.61: "connected" = the org has an effective reporting provider.
        $this->view->provider  = Tiger_Analytics::provider((string) Tiger_Model_Config::currentOrgId());
        $this->view->connected = $this->view->provider !== '';
        $this->view->useChart  = true;   // the admin layout ships Chart.js only when a page declares a chart
    }

    /** The OAuth redirect URI (must match the one registered in the Google OAuth client). */
    private function _redirectUri()
    {
        $req = $this->getRequest();
        return $req->getScheme() . '://' . $req->getHttpHost() . '/analytics/admin/callback';
    }

    /** Stash a one-shot flash message (survives the OAuth redirect) in the session. */
    private function _flash($message, $type)
    {
        $ns = new Zend_Session_Namespace('AnalyticsFlash');
        $ns->message = (string) $message;
        $ns->type    = (string) $type;
    }

    /** Read + clear the flash message, or null. */
    private function _takeFlash()
    {
        $ns = new Zend_Session_Namespace('AnalyticsFlash');
        if (empty($ns->message)) { return null; }
        $flash = ['message' => (string) $ns->message, 'type' => (string) ($ns->type ?: 'info')];
        unset($ns->message, $ns->type);
        return $flash;
    }
}
