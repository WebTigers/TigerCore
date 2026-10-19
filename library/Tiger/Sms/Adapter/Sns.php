<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Tiger_Sms_Adapter_Sns — the Amazon SNS SMS adapter (native to an AWS-hosted Tiger).
 *
 * Sends via the SNS `Publish` action, SigV4-signed by hand (no SDK required). Credentials come from
 * config (`tiger.sms.adapters.sns.{key, secret_key, region}` — `secret_key` stored `secret_key_enc`,
 * encrypted) OR, when none are set, the EC2 instance role via IMDSv2 — so an AWS-hosted install sends
 * with zero keys, exactly like the fleet's SES mail. The sender (`from`) is an origination number
 * (10DLC/toll-free, required for US A2P) or, where supported, a sender ID.
 *
 * Note: a brand-new AWS account is in the SMS sandbox ($1/mo, verified destinations only) until
 * production access is requested — this adapter sends fine in the sandbox to verified numbers.
 *
 * @api
 */
class Tiger_Sms_Adapter_Sns extends Tiger_Sms_Adapter_Abstract
{
    const SERVICE   = 'sns';
    const ALGORITHM = 'AWS4-HMAC-SHA256';
    const VERSION   = '2010-03-31';

    public function label(): string { return 'Amazon SNS'; }

    public function fields(): array
    {
        return [
            ['key' => 'region',     'label' => 'AWS region',          'type' => 'text',   'placeholder' => 'us-east-1'],
            ['key' => 'from',       'label' => 'Origination number',  'type' => 'text',   'placeholder' => '+18885551234'],
            ['key' => 'key',        'label' => 'Access key (blank = use the instance role)', 'type' => 'text'],
            ['key' => 'secret_key', 'label' => 'Secret key',          'type' => 'secret', 'secret' => true],
        ];
    }

    public function isConfigured(): bool
    {
        // Either static keys OR an instance role (we can't cheaply prove the role here, so a region alone
        // is treated as "AWS-hosted, will use the role"). The send itself fails cleanly if neither works.
        return (string) $this->_cfg('region', '') !== '' || $this->_credentials() !== null;
    }

    public function send(string $to, string $message, string $from, array $opts = []): array
    {
        $region = (string) $this->_cfg('region', 'us-east-1');
        $creds  = $this->_credentials();
        if ($creds === null) { return ['ok' => false, 'error' => 'SNS has no credentials (set key/secret_key or attach an instance role)']; }

        $smsType = (string) ($opts['sms_type'] ?? 'Transactional');   // Transactional | Promotional
        $params  = [
            'Action'      => 'Publish',
            'Version'     => self::VERSION,
            'PhoneNumber' => $to,
            'Message'     => $message,
        ];
        $attrs = [['AWS.SNS.SMS.SMSType', $smsType]];
        if ($from !== '') {
            // A leading '+' = an origination number; otherwise a sender id (non-US).
            $attrs[] = strncmp($from, '+', 1) === 0 ? ['AWS.MM.SMS.OriginationNumber', $from] : ['AWS.SNS.SMS.SenderID', $from];
        }
        foreach ($attrs as $i => [$name, $value]) {
            $n = $i + 1;
            $params["MessageAttributes.entry.{$n}.Name"]                  = $name;
            $params["MessageAttributes.entry.{$n}.Value.DataType"]        = 'String';
            $params["MessageAttributes.entry.{$n}.Value.StringValue"]     = $value;
        }

        ksort($params);
        $body = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $host = 'sns.' . $region . '.amazonaws.com';
        $headers = $this->_sigV4Headers($host, $region, $body, $creds);

        [$status, $resp] = $this->_http('POST', 'https://' . $host . '/', $headers, $body, 15);
        if ($status >= 200 && $status < 300) {
            $id = '';
            if (preg_match('#<MessageId>([^<]+)</MessageId>#', $resp, $m)) { $id = $m[1]; }
            return ['ok' => true, 'id' => $id];
        }
        $err = '';
        if (preg_match('#<Code>([^<]+)</Code>#', $resp, $c)) { $err = $c[1]; }
        if (preg_match('#<Message>([^<]+)</Message>#', $resp, $mm)) { $err .= ($err !== '' ? ': ' : '') . $mm[1]; }
        return ['ok' => false, 'error' => 'SNS error (HTTP ' . $status . ')' . ($err !== '' ? ' ' . $err : '')];
    }

    // ----- credentials ------------------------------------------------------------------------------

    /**
     * Static config credentials, else the EC2 instance role (IMDSv2). @return array{key,secret,token}|null
     */
    protected function _credentials(): ?array
    {
        $key    = (string) ($this->_cfg('key', '') ?: '');
        $secret = $this->_secret('secret_key');
        if ($key !== '' && $secret !== '') {
            return ['key' => $key, 'secret' => $secret, 'token' => $this->_secret('session_token')];
        }
        return $this->_instanceRoleCreds();
    }

    /** Fetch temporary credentials from the EC2 instance role via IMDSv2. Null when not on EC2 / no role. */
    protected function _instanceRoleCreds(): ?array
    {
        try {
            [$st, $token] = $this->_http('PUT', 'http://169.254.169.254/latest/api/token',
                ['X-aws-ec2-metadata-token-ttl-seconds: 60'], '', 2);
            if ($st !== 200 || $token === '') { return null; }
            $h = ['X-aws-ec2-metadata-token: ' . $token];
            [$st2, $role] = $this->_http('GET', 'http://169.254.169.254/latest/meta-data/iam/security-credentials/', $h, '', 2);
            if ($st2 !== 200 || trim($role) === '') { return null; }
            $role = trim(strtok($role, "\n"));
            [$st3, $body] = $this->_http('GET', 'http://169.254.169.254/latest/meta-data/iam/security-credentials/' . rawurlencode($role), $h, '', 2);
            $j = json_decode($body, true);
            if ($st3 !== 200 || !is_array($j) || empty($j['AccessKeyId'])) { return null; }
            return ['key' => (string) $j['AccessKeyId'], 'secret' => (string) $j['SecretAccessKey'], 'token' => (string) ($j['Token'] ?? '')];
        } catch (Throwable $e) {
            return null;
        }
    }

    // ----- SigV4 ------------------------------------------------------------------------------------

    /**
     * The SigV4 header set for a form POST to SNS. Standard four-step SigV4 (canonical request →
     * string-to-sign → signing key HMAC chain → signature), signing host/content-type/x-amz-* headers.
     *
     * @param  array{key:string,secret:string,token:string} $creds
     * @return string[] curl header lines
     */
    protected function _sigV4Headers(string $host, string $region, string $body, array $creds): array
    {
        $now      = gmdate('Ymd\THis\Z');
        $date     = substr($now, 0, 8);
        $scope    = $date . '/' . $region . '/' . self::SERVICE . '/aws4_request';
        $bodyHash = hash('sha256', $body);
        $token    = (string) ($creds['token'] ?? '');

        $canonHeaders = "content-type:application/x-www-form-urlencoded\n"
                      . "host:{$host}\n"
                      . "x-amz-content-sha256:{$bodyHash}\n"
                      . "x-amz-date:{$now}\n"
                      . ($token !== '' ? "x-amz-security-token:{$token}\n" : '');
        $signedHeaders = 'content-type;host;x-amz-content-sha256;x-amz-date' . ($token !== '' ? ';x-amz-security-token' : '');

        $canonRequest = "POST\n/\n\n{$canonHeaders}\n{$signedHeaders}\n{$bodyHash}";
        $stringToSign = self::ALGORITHM . "\n{$now}\n{$scope}\n" . hash('sha256', $canonRequest);

        $kDate    = hash_hmac('sha256', $date, 'AWS4' . $creds['secret'], true);
        $kRegion  = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', self::SERVICE, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $auth = self::ALGORITHM . " Credential={$creds['key']}/{$scope}, SignedHeaders={$signedHeaders}, Signature={$signature}";
        $headers = [
            'Authorization: ' . $auth,
            'Content-Type: application/x-www-form-urlencoded',
            'x-amz-content-sha256: ' . $bodyHash,
            'x-amz-date: ' . $now,
        ];
        if ($token !== '') { $headers[] = 'x-amz-security-token: ' . $token; }
        return $headers;
    }
}
