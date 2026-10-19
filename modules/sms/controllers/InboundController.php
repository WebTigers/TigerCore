<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Sms_InboundController — the inbound-SMS webhook (`/sms/inbound/<provider>`).
 *
 * The carrier POSTs here when someone texts your number. This controller does ONLY the transport:
 * resolve the provider adapter, **verify the request signature**, normalize the payload, and **publish
 * it to subscribers** (Tiger_Sms::dispatchInbound). It never interprets the message — receiving is
 * application-specific, so a module subscribes with Tiger_Sms::onInbound() and decides what it means
 * (reply, STOP/opt-out, a keyword flow, an order lookup…). Loose coupling: TigerSMS owns ingest, the
 * app owns meaning.
 *
 * Public by design (a carrier can't authenticate), so the signature IS the gate — an un-verifiable
 * request is 403'd before any listener sees it. Point your provider's inbound webhook at this URL.
 */
class Sms_InboundController extends Zend_Controller_Action
{
    public function init()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
    }

    public function indexAction()
    {
        $req  = $this->getRequest();
        $resp = $this->getResponse();

        $provider = preg_replace('/[^a-z0-9]/', '', strtolower((string) $req->getParam('provider')));
        $adapter  = Tiger_Sms::adapterFor($provider);
        if ($adapter === null) { $resp->setHttpResponseCode(404); return; }

        $post    = $req->getPost();
        $url     = $req->getScheme() . '://' . (string) ($_SERVER['HTTP_HOST'] ?? '') . (string) ($_SERVER['REQUEST_URI'] ?? '');
        $headers = $this->_headers();

        if (!$adapter->verifyInbound($url, $post, $headers)) {
            $this->_log('sms.inbound.rejected', ['provider' => $provider]);
            $resp->setHttpResponseCode(403);
            return;
        }

        $msg = $adapter->parseInbound($post);
        if ($msg === null) { $resp->setHttpResponseCode(204); return; }   // a non-message payload (e.g. a status callback)

        $msg['received_at'] = date('c');
        $delivered = Tiger_Sms::dispatchInbound($msg);
        $this->_log('sms.inbound', ['provider' => $provider, 'from' => (string) ($msg['from'] ?? ''), 'listeners' => $delivered]);

        // Twilio expects TwiML or an empty 200 ack; an empty <Response/> acks without an auto-reply.
        $resp->setHeader('Content-Type', 'text/xml')
             ->setBody('<?xml version="1.0" encoding="UTF-8"?><Response></Response>');
    }

    /** The request headers, Title-Cased (so an adapter can read e.g. X-Twilio-Signature). */
    protected function _headers(): array
    {
        $h = [];
        foreach ($_SERVER as $k => $v) {
            if (strncmp((string) $k, 'HTTP_', 5) === 0) {
                $name = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr((string) $k, 5)))));
                $h[$name] = $v;
            }
        }
        return $h;
    }

    /** Best-effort structured log; never breaks the webhook. */
    protected function _log(string $event, array $ctx): void
    {
        if (class_exists('Tiger_Log')) { try { Tiger_Log::info($event, $ctx); } catch (Throwable $e) { /* never fatal */ } }
    }
}
