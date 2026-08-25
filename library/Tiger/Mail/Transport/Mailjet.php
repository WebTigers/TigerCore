<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Mail_Transport_Mailjet — send over the Mailjet Send API v3.1.
 *
 * `POST https://api.mailjet.com/v3.1/send`, HTTP Basic auth with the API key as the username and
 * the SECRET key as the password — Mailjet is the one provider here that needs two credentials.
 *
 * Config: `key` (API key), `secret` (secret key).
 *
 * @api
 */
class Tiger_Mail_Transport_Mailjet extends Tiger_Mail_Transport_Api
{
    /** @return string the v3.1 send endpoint */
    protected function _endpoint()
    {
        return 'https://api.mailjet.com/v3.1/send';
    }

    /** @return array<int,string> basic auth (key:secret) + JSON */
    protected function _headers()
    {
        return [
            'Authorization: Basic ' . base64_encode($this->_cfg('key') . ':' . $this->_cfg('secret')),
            'Content-Type: application/json',
        ];
    }

    /**
     * @param  array $msg the normalized message
     * @return array       Mailjet's Messages[] envelope
     */
    protected function _payload(array $msg)
    {
        $message = [
            'From' => array_filter(['Email' => $msg['from']['email'], 'Name' => $msg['from']['name']], 'strlen'),
            'To'   => array_map(static function ($t) {
                return array_filter(['Email' => $t['email'], 'Name' => $t['name']], 'strlen');
            }, $msg['to']),
            'Subject' => $msg['subject'],
        ];
        if ($msg['html'] !== '') { $message['HTMLPart'] = $msg['html']; }
        if ($msg['text'] !== '') { $message['TextPart'] = $msg['text']; }
        if ($msg['reply_to'])    { $message['ReplyTo']  = ['Email' => $msg['reply_to']['email']]; }

        return ['Messages' => [$message]];
    }
}
