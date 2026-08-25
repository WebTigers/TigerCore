<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Mail_Transport_Mailgun — send over the Mailgun Messages API.
 *
 * `POST {base}/v3/{domain}/messages`, HTTP Basic auth with the literal username `api` and the API
 * key as the password. Unlike the JSON providers, Mailgun takes **form-encoded** fields.
 *
 * The API base is configurable because Mailgun runs separate US and EU regions
 * (`api.mailgun.net` / `api.eu.mailgun.net`) and a key is only valid in its own region — a
 * mismatch is a confusing 401, so it's an explicit field rather than a guess.
 *
 * Config: `domain`, `key`, optional `endpoint`.
 *
 * @api
 */
class Tiger_Mail_Transport_Mailgun extends Tiger_Mail_Transport_Api
{
    /** @return string the region-aware messages endpoint for the sending domain */
    protected function _endpoint()
    {
        $base = rtrim($this->_cfg('endpoint') ?: 'https://api.mailgun.net', '/');
        return $base . '/v3/' . rawurlencode($this->_cfg('domain')) . '/messages';
    }

    /** @return array<int,string> basic auth (api:KEY) + form encoding */
    protected function _headers()
    {
        return [
            'Authorization: Basic ' . base64_encode('api:' . $this->_cfg('key')),
            'Content-Type: application/x-www-form-urlencoded',
        ];
    }

    /**
     * @param  array $msg the normalized message
     * @return string      form-encoded body (Mailgun does not take JSON here)
     */
    protected function _payload(array $msg)
    {
        $from = $msg['from']['name'] !== ''
            ? sprintf('%s <%s>', $msg['from']['name'], $msg['from']['email'])
            : $msg['from']['email'];

        $fields = [
            'from'    => $from,
            'to'      => implode(',', array_column($msg['to'], 'email')),
            'subject' => $msg['subject'],
        ];
        if ($msg['text'] !== '') { $fields['text'] = $msg['text']; }
        if ($msg['html'] !== '') { $fields['html'] = $msg['html']; }
        if ($msg['reply_to'])    { $fields['h:Reply-To'] = $msg['reply_to']['email']; }

        return http_build_query($fields);
    }
}
