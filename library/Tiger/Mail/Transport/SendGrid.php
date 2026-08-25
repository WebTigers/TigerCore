<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Mail_Transport_SendGrid — send over the SendGrid v3 Mail Send API.
 *
 * `POST https://api.sendgrid.com/v3/mail/send` with a Bearer API key. SendGrid returns **202 with
 * an empty body** on success, so the base's 2xx check is the only signal there is.
 *
 * Config: `key` (API key).
 *
 * @api
 */
class Tiger_Mail_Transport_SendGrid extends Tiger_Mail_Transport_Api
{
    /** @return string the v3 send endpoint */
    protected function _endpoint()
    {
        return 'https://api.sendgrid.com/v3/mail/send';
    }

    /** @return array<int,string> bearer auth + JSON */
    protected function _headers()
    {
        return ['Authorization: Bearer ' . $this->_cfg('key'), 'Content-Type: application/json'];
    }

    /**
     * @param  array $msg the normalized message
     * @return array       SendGrid's personalizations/content shape
     */
    protected function _payload(array $msg)
    {
        $content = [];
        if ($msg['text'] !== '') { $content[] = ['type' => 'text/plain', 'value' => $msg['text']]; }
        if ($msg['html'] !== '') { $content[] = ['type' => 'text/html',  'value' => $msg['html']]; }

        $payload = [
            'personalizations' => [['to' => array_map(static function ($t) {
                return array_filter(['email' => $t['email'], 'name' => $t['name']], 'strlen');
            }, $msg['to'])]],
            'from'    => array_filter(['email' => $msg['from']['email'], 'name' => $msg['from']['name']], 'strlen'),
            'subject' => $msg['subject'],
            'content' => $content,
        ];
        if ($msg['reply_to']) { $payload['reply_to'] = ['email' => $msg['reply_to']['email']]; }

        return $payload;
    }
}
