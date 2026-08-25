<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Mail_Transport_Brevo — send over the Brevo (formerly Sendinblue) transactional API.
 *
 * `POST https://api.brevo.com/v3/smtp/email`, authenticated with an `api-key` header (not a
 * Bearer token). The `smtp` in the path is Brevo's naming for the transactional endpoint; this is
 * a plain HTTPS call, not SMTP.
 *
 * Config: `key` (API key, `xkeysib-…`).
 *
 * @api
 */
class Tiger_Mail_Transport_Brevo extends Tiger_Mail_Transport_Api
{
    /** @return string the transactional send endpoint */
    protected function _endpoint()
    {
        return 'https://api.brevo.com/v3/smtp/email';
    }

    /** @return array<int,string> the api-key header + JSON */
    protected function _headers()
    {
        return ['api-key: ' . $this->_cfg('key'), 'Content-Type: application/json', 'Accept: application/json'];
    }

    /**
     * @param  array $msg the normalized message
     * @return array       Brevo's sender/to shape
     */
    protected function _payload(array $msg)
    {
        $payload = [
            'sender'  => array_filter(['email' => $msg['from']['email'], 'name' => $msg['from']['name']], 'strlen'),
            'to'      => array_map(static function ($t) {
                return array_filter(['email' => $t['email'], 'name' => $t['name']], 'strlen');
            }, $msg['to']),
            'subject' => $msg['subject'],
        ];
        if ($msg['html'] !== '') { $payload['htmlContent'] = $msg['html']; }
        if ($msg['text'] !== '') { $payload['textContent'] = $msg['text']; }
        if ($msg['reply_to'])    { $payload['replyTo'] = ['email' => $msg['reply_to']['email']]; }

        return $payload;
    }
}
