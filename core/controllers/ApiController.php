<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * ApiController — the single TIGER webservice gateway (default namespace).
 *
 * ALL /api traffic dispatches here (see the /api routes registered in
 * Tiger_Application_Bootstrap::_initApiRoutes). It stays dumb: hand the request to
 * Tiger_Ajax_ServiceFactory, then either _forward (controller mode) or JSON-encode
 * the ResponseObject (service mode). All logic/authorization lives in the factory
 * and the services. Ported from AskLevi's Core_ApiController.
 */
class ApiController extends Zend_Controller_Action
{
    /**
     * Configure the request for a machine endpoint: no layout, no view render, JSON headers.
     *
     * @return void
     */
    public function init()
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        $this->getResponse()->setHeader('Content-Type', 'application/json; charset=UTF-8');

        // /api is a machine endpoint — the body must be pure JSON, so suppress
        // display_errors for this request (a stray notice can never leak into the
        // response). Errors still hit the log.
        @ini_set('display_errors', '0');
    }

    /**
     * Dispatch the /api message: forward in controller mode, else emit the ResponseObject as JSON.
     *
     * @return void
     */
    public function indexAction()
    {
        $factory = new Tiger_Ajax_ServiceFactory($this->getRequest());

        // Controller mode: the factory ACL-checked; the target controller owns its
        // own response. Hand off through the normal dispatch loop — no JSON here.
        if ($fwd = $factory->getForward()) {
            $this->_forward($fwd['action'], $fwd['controller'], $fwd['module'], $fwd['params']);
            return;
        }

        // Service mode: emit the resolved ResponseObject as JSON.
        $this->getResponse()->setHttpResponseCode(200);
        echo json_encode($factory->getResponse(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
