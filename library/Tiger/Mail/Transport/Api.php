<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Mail_Transport_Api — base for the HTTPS mail-API drivers (SendGrid, Mailgun, Postmark,
 * Resend, Brevo, Mailjet, SES).
 *
 * It extends `Zend_Mail_Transport_Abstract`, which is the whole trick: an API driver is just
 * another transport, so `Tiger_Mail::send()`, the per-call override, and the test-capture default
 * transport all keep working with no special-casing anywhere. `Zend_Mail_Transport_Abstract::send()`
 * assigns the `Zend_Mail` to `$this->_mail` before calling `_sendMail()`, so a subclass reads the
 * STRUCTURED message (to / subject / html / text) rather than trying to parse rendered MIME —
 * which matters because most of these APIs take JSON fields, not a raw message.
 *
 * A subclass implements `_endpoint()`, `_headers()` and `_payload()`. This base owns the HTTP call,
 * the User-Agent (an outbound request with no UA is refused by some WAFs), timeouts, and turning a
 * non-2xx response into a `Zend_Mail_Transport_Exception` carrying the provider's own error text —
 * because on a failed send that text is the entire diagnostic.
 *
 * @api
 * @see Tiger_Mail_Provider
 */
abstract class Tiger_Mail_Transport_Api extends Zend_Mail_Transport_Abstract
{
    /** Connect + total timeouts (seconds). A hung provider must never hang a request. */
    const TIMEOUT_CONNECT = 10;
    const TIMEOUT_TOTAL   = 30;

    /** @var array the provider's stored credential values */
    protected $_config = [];

    /**
     * @param array $config the provider's credential values (api key, region, domain, …)
     */
    public function __construct(array $config = [])
    {
        $this->_config = $config;
    }

    /** @return string the absolute HTTPS endpoint to POST to */
    abstract protected function _endpoint();

    /** @return array<int,string> request headers, as "Name: value" strings */
    abstract protected function _headers();

    /**
     * Build the provider's request body from the normalized message.
     *
     * @param  array $msg from{email,name}, to[{email,name}], reply_to, subject, html, text
     * @return array|string an array (JSON-encoded by default) or a pre-encoded body string
     */
    abstract protected function _payload(array $msg);

    /** @return string a config value, or '' */
    protected function _cfg($key)
    {
        return isset($this->_config[$key]) ? (string) $this->_config[$key] : '';
    }

    /**
     * Normalize the Zend_Mail into the flat shape every driver builds its payload from.
     *
     * @return array{from:array,to:array,reply_to:array|null,subject:string,html:string,text:string}
     */
    protected function _message()
    {
        $mail = $this->_mail;

        $to = [];
        foreach ((array) $mail->getRecipients() as $address) {
            $to[] = ['email' => (string) $address, 'name' => ''];
        }

        $replyTo = null;
        $rt = method_exists($mail, 'getReplyTo') ? $mail->getReplyTo() : null;
        if ($rt) { $replyTo = ['email' => (string) $rt, 'name' => '']; }

        return [
            'from'     => ['email' => (string) $mail->getFrom(), 'name' => $this->_fromName()],
            'to'       => $to,
            'reply_to' => $replyTo,
            'subject'  => (string) $mail->getSubject(),
            'html'     => $this->_part($mail->getBodyHtml()),
            'text'     => $this->_part($mail->getBodyText()),
        ];
    }

    /**
     * A MIME part's ORIGINAL content.
     *
     * Deliberately `getRawContent()`, not `getContent()`: the latter runs `Zend_Mime::encode()` and
     * hands back quoted-printable/base64 for wire transmission, which is exactly wrong for a JSON
     * API field — the recipient would see the encoded source. `getBodyHtml(true)` has the same
     * problem, since it returns `getContent()` internally.
     *
     * @param  Zend_Mime_Part|false|null $part the value from getBodyHtml()/getBodyText()
     * @return string                          the unencoded body, or ''
     */
    protected function _part($part)
    {
        if (!$part instanceof Zend_Mime_Part) { return ''; }
        return (string) $part->getRawContent();
    }

    /**
     * The sender's display name.
     *
     * Zend_Mail has no `getFromName()` — `setFrom($email, $name)` folds the name into the `From`
     * header — so it's read back out of the formatted header (`"Name" <email>`).
     *
     * @return string the display name, or '' when the sender is a bare address
     */
    protected function _fromName()
    {
        $headers = (array) $this->_mail->getHeaders();
        $from    = isset($headers['From'][0]) ? (string) $headers['From'][0] : '';
        if ($from === '' || strpos($from, '<') === false) { return ''; }

        $name = trim(substr($from, 0, strpos($from, '<')));
        return trim($name, '" ');
    }

    /**
     * Send the message over the provider's API. Called by Zend_Mail_Transport_Abstract::send().
     *
     * @return void
     * @throws Zend_Mail_Transport_Exception on a transport or provider error
     */
    protected function _sendMail()
    {
        $payload = $this->_payload($this->_message());
        $body    = is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $this->_post($this->_endpoint(), $body, $this->_headers());
    }

    /**
     * POST to the provider and assert a 2xx.
     *
     * @param  string $url     the endpoint
     * @param  string $body    the request body
     * @param  array  $headers request headers
     * @return string          the response body (for drivers that want the provider's message id)
     * @throws Zend_Mail_Transport_Exception when the request fails or the provider refuses it
     */
    protected function _post($url, $body, array $headers)
    {
        if (!function_exists('curl_init')) {
            throw new Zend_Mail_Transport_Exception('The PHP cURL extension is required to send mail over a provider API.');
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => self::TIMEOUT_CONNECT,
            CURLOPT_TIMEOUT        => self::TIMEOUT_TOTAL,
            // Some WAFs (including ours) refuse a request with no User-Agent.
            CURLOPT_USERAGENT      => 'Tiger/' . (class_exists('Tiger_Version') ? Tiger_Version::VERSION : '1.0') . ' (+https://webtigers.com)',
        ]);

        $response = curl_exec($ch);
        $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($response === false || $error !== '') {
            throw new Zend_Mail_Transport_Exception('Mail API request failed: ' . $error);
        }
        if ($status < 200 || $status >= 300) {
            // The provider's own message is the diagnostic — surface it, trimmed.
            $detail = trim((string) $response);
            if (strlen($detail) > 500) { $detail = substr($detail, 0, 500) . '…'; }
            throw new Zend_Mail_Transport_Exception(
                'Mail API rejected the message (HTTP ' . $status . ')' . ($detail !== '' ? ': ' . $detail : '')
            );
        }

        return (string) $response;
    }
}
