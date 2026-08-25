<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Mail_Transport_Resend — send over the Resend API.
 *
 * `POST https://api.resend.com/emails` with a Bearer API key.
 *
 * Config: `key` (API key, `re_…`).
 *
 * @api
 */
class Tiger_Mail_Transport_Resend extends Tiger_Mail_Transport_Api
{
    /** @return string the send endpoint */
    protected function _endpoint()
    {
        return 'https://api.resend.com/emails';
    }

    /** @return array<int,string> bearer auth + JSON */
    protected function _headers()
    {
        return ['Authorization: Bearer ' . $this->_cfg('key'), 'Content-Type: application/json'];
    }

    /**
     * @param  array $msg the normalized message
     * @return array       Resend's flat shape (to is an array of addresses)
     */
    protected function _payload(array $msg)
    {
        $from = $msg['from']['name'] !== ''
            ? sprintf('%s <%s>', $msg['from']['name'], $msg['from']['email'])
            : $msg['from']['email'];

        $payload = [
            'from'    => $from,
            'to'      => array_column($msg['to'], 'email'),
            'subject' => $msg['subject'],
        ];
        if ($msg['html'] !== '') { $payload['html'] = $msg['html']; }
        if ($msg['text'] !== '') { $payload['text'] = $msg['text']; }
        if ($msg['reply_to'])    { $payload['reply_to'] = $msg['reply_to']['email']; }

        return $payload;
    }
}
