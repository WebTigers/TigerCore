<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Register_Service_Registration — the `/api` service behind the registration widget (and Settings screen).
 * Admin only. Registration is entirely optional and **gates nothing**: the widget is the offer, switching it
 * off (or deactivating this module) is the opt-out, and no feature anywhere is enabled or disabled by this.
 *
 *  - **register** — call the TigerRegistry `shop/register` for this domain → store the TSID + the challenge
 *    token (auto-served at `/.well-known/tiger-verify.txt`), email a verification link, self-verify the domain.
 *  - **verifyDomain** — (re)confirm domain control via the registry.
 *  - **resendEmail** — resend the email verification link.
 *  - **status** — the current progress (drives the widget + the screen).
 *
 * Nothing runs until the admin submits the widget/screen. Registry calls use an injectable transport (tests).
 *
 * @api
 */
class Register_Service_Registration extends Tiger_Service_Service
{
    const EMAIL_TTL        = 172800;   // 48h to click the verification link
    const DEFAULT_REGISTRY = 'https://registry.webtigers.com';

    /** @var callable|null test seam: fn(string $registryUrl, array $message): ?array (returns envelope `data`) */
    private static $_transport = null;

    /** Override the registry transport (tests). @param callable|null $t @return void */
    public static function setTransport(?callable $t): void { self::$_transport = $t; }

    /** The current registration progress. @param array $params @return void */
    public function status(array $params): void
    {
        if (!$this->_isAdmin('Register_AdminController')) { $this->_error('core.api.error.not_allowed'); return; }
        $this->_success(['state' => Register_Service_Status::state()]);
    }

    /**
     * Register the site: create/refresh its TSID for the current domain, arm the auto-served domain token,
     * email a verification link, and try to self-verify the domain immediately.
     *
     * @param  array $params `email`
     * @return void
     */
    public function register(array $params): void
    {
        if (!$this->_isAdmin('Register_AdminController')) { $this->_error('core.api.error.not_allowed'); return; }

        $form = new Register_Form_Register();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        $email  = (string) $form->getValue('email');
        $domain = $this->_detectDomain();
        if ($domain === '') { $this->_error('register.error.no_domain'); return; }

        $reg = $this->_registry('shop', 'register', ['domain' => $domain, 'shop_name' => $this->_siteName(), 'owner_id' => $email]);
        if ($reg === null || empty($reg['tsid'])) { $this->_error('register.error.registry_unreachable'); return; }

        $this->_set('register.tsid', (string) $reg['tsid']);
        $this->_set('register.domain', (string) ($reg['domain'] ?? $domain));
        $this->_set('register.domain_verified', !empty($reg['verified']) ? '1' : '0');
        $this->_set('register.verify_token', (string) ($reg['token'] ?? ''));
        $this->_set('register.email', $email);
        $this->_set('register.email_verified', '0');

        $this->_issueEmailToken($email, $domain);      // best-effort
        $this->_selfVerifyDomain();                    // best-effort auto domain-verify

        $this->_success(['state' => Register_Service_Status::state()], 'register.registered');
    }

    /** (Re)confirm domain control via the registry. @param array $params @return void */
    public function verifyDomain(array $params): void
    {
        if (!$this->_isAdmin('Register_AdminController')) { $this->_error('core.api.error.not_allowed'); return; }
        if (!Register_Service_Status::hasStarted()) { $this->_error('register.error.not_registered'); return; }
        $this->_selfVerifyDomain();
        $verified = Register_Service_Status::isDomainVerified();
        $this->_success(['state' => Register_Service_Status::state()], $verified ? 'register.domain_verified' : 'register.domain_pending');
    }

    /** Resend the admin email verification link. @param array $params @return void */
    public function resendEmail(array $params): void
    {
        if (!$this->_isAdmin('Register_AdminController')) { $this->_error('core.api.error.not_allowed'); return; }
        $email = (string) (new Tiger_Model_Config())->get(Tiger_Model_Config::SCOPE_GLOBAL, '', 'register.email');
        if ($email === '') { $this->_error('register.error.not_registered'); return; }
        $this->_issueEmailToken($email, (string) (new Tiger_Model_Config())->get(Tiger_Model_Config::SCOPE_GLOBAL, '', 'register.domain'));
        $this->_success(['state' => Register_Service_Status::state()], 'register.email_sent');
    }

    // ---- internals ---------------------------------------------------------------------------

    private function _selfVerifyDomain(): void
    {
        $tsid = (string) (new Tiger_Model_Config())->get(Tiger_Model_Config::SCOPE_GLOBAL, '', 'register.tsid');
        if ($tsid === '') { return; }
        $res = $this->_registry('shop', 'domainVerify', ['tsid' => $tsid]);
        if (is_array($res) && !empty($res['verified'])) {
            $this->_set('register.domain_verified', '1');
        }
    }

    private function _issueEmailToken(string $email, string $domain): void
    {
        $token = bin2hex(random_bytes(20));
        $this->_set('register.email_token', hash('sha256', $token));
        $this->_set('register.email_expires', (string) (time() + self::EMAIL_TTL));

        $url = $this->_baseUrl() . '/register/verify/email/token/' . $token;
        try {
            (new Tiger_Mail())
                ->to($email)
                ->subject('Verify your Tiger install')
                ->html('<p>Click to verify your site:</p>'
                    . '<p><a href="' . htmlspecialchars($url, ENT_QUOTES) . '">Verify my site &rarr;</a></p>'
                    . '<p>Domain: <strong>' . htmlspecialchars($domain, ENT_QUOTES) . '</strong></p>')
                ->send();
        } catch (Throwable $e) {
            // no MTA on a fresh install — the token is stored; the admin can resend.
        }
    }

    private function _registry(string $service, string $method, array $params): ?array
    {
        $message = ['module' => 'registry', 'service' => $service, 'method' => $method] + $params;
        $url     = $this->_registryUrl();

        if (self::$_transport !== null) {
            $d = (self::$_transport)($url, $message);
            return is_array($d) ? $d : null;
        }
        if (!function_exists('curl_init')) { return null; }

        $ch = curl_init(rtrim($url, '/') . '/api');
        curl_setopt_array($ch, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query($message), CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 4, CURLOPT_TIMEOUT => 8, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_FOLLOWLOCATION => false,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($body === false || $code !== 200) { return null; }
        $j = json_decode((string) $body, true);
        if (!is_array($j) || (int) ($j['result'] ?? 0) !== 1) { return null; }
        return is_array($j['data'] ?? null) ? $j['data'] : [];
    }

    private function _registryUrl(): string
    {
        $u = trim((string) (new Tiger_Model_Config())->get(Tiger_Model_Config::SCOPE_GLOBAL, '', 'register.registry_url'));
        return $u !== '' ? $u : self::DEFAULT_REGISTRY;
    }

    private function _set(string $key, $value): void
    {
        (new Tiger_Model_Config())->set(Tiger_Model_Config::SCOPE_GLOBAL, '', $key, (string) $value);
    }

    private function _detectDomain(): string
    {
        $host = '';
        $req  = Zend_Controller_Front::getInstance()->getRequest();
        if ($req && method_exists($req, 'getHttpHost')) { $host = (string) $req->getHttpHost(); }
        if ($host === '' && !empty($_SERVER['HTTP_HOST'])) { $host = (string) $_SERVER['HTTP_HOST']; }
        return (string) preg_replace('#:\d+$#', '', strtolower(trim($host)));
    }

    private function _baseUrl(): string
    {
        $req    = Zend_Controller_Front::getInstance()->getRequest();
        $scheme = ($req && method_exists($req, 'getScheme')) ? $req->getScheme() : 'https';
        $host   = ($req && method_exists($req, 'getHttpHost')) ? $req->getHttpHost() : $this->_detectDomain();
        return $scheme . '://' . $host;
    }

    private function _siteName(): string
    {
        $cfg  = Zend_Registry::isRegistered('Zend_Config') ? Zend_Registry::get('Zend_Config') : null;
        $t    = $cfg ? $cfg->get('tiger') : null;
        $site = $t ? $t->get('site') : null;
        $name = $site ? (string) $site->get('name') : '';
        return $name !== '' ? $name : $this->_detectDomain();
    }
}
