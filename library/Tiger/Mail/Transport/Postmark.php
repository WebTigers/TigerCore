<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Mail_Transport_Postmark — send over the Postmark Email API.
 *
 * `POST https://api.postmarkapp.com/email`, authenticated with the `X-Postmark-Server-Token`
 * header (a SERVER token, not the account token — a common mix-up that surfaces as a 401).
 *
 * Config: `key` (server token), optional `stream` (message stream, default `outbound`).
 *
 * @api
 */
class Tiger_Mail_Transport_Postmark extends Tiger_Mail_Transport_Api
{
    /** @return string the send endpoint */
    protected function _endpoint()
    {
        return 'https://api.postmarkapp.com/email';
    }

    /** @return array<int,string> the server-token header + JSON */
    protected function _headers()
    {
        return [
            'X-Postmark-Server-Token: ' . $this->_cfg('key'),
            'Content-Type: application/json',
            'Accept: application/json',
        ];
    }

    /**
     * @param  array $msg the normalized message
     * @return array       Postmark's PascalCase field shape
     */
    protected function _payload(array $msg)
    {
        $from = $msg['from']['name'] !== ''
            ? sprintf('"%s" <%s>', $msg['from']['name'], $msg['from']['email'])
            : $msg['from']['email'];

        $payload = [
            'From'          => $from,
            'To'            => implode(',', array_column($msg['to'], 'email')),
            'Subject'       => $msg['subject'],
            'MessageStream' => $this->_cfg('stream') ?: 'outbound',
        ];
        if ($msg['html'] !== '') { $payload['HtmlBody'] = $msg['html']; }
        if ($msg['text'] !== '') { $payload['TextBody'] = $msg['text']; }
        if ($msg['reply_to'])    { $payload['ReplyTo']  = $msg['reply_to']['email']; }

        return $payload;
    }
}
