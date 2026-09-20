<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Newsletter_Service_Subscribe — the `/api` surface for newsletter opt-in collection.
 *
 * This module COLLECTS consent-first subscribers; it does NOT own consent policy or sending. A
 * subscribe is never auto-confirmed: it stores the row `pending`, mints an opaque token, and (best
 * effort) emails a confirmation link. `confirm()` redeems that token and records consent;
 * `unsubscribe()` opts out. Only CONFIRMED rows are ever exposed as an audience
 * (Newsletter_Service_Audience) — so a consumer such as TigerList receives opt-ins only, then still
 * applies its own consent/suppression before a single message is sent.
 *
 * BOUNDARY (registry = entitlement, TigerList = consent, a send = the intersection): the double
 * opt-in here is the *collection* confirmation a free standalone install needs to have a genuine
 * opt-in at all — it is deliberately NOT campaign consent or a suppression engine, which remain
 * TigerList's.
 *
 * `subscribe`/`confirm`/`unsubscribe` are guest-allowed (public acts). The honeypot, the too-fast
 * gate and the per-IP rate limit mirror the comment service — the same cheap flood guards a public,
 * write-capable endpoint needs.
 *
 * @api
 */
class Newsletter_Service_Subscribe extends Tiger_Service_Service
{
    /** Max subscribe attempts one address (IP) may make in the window. */
    const RATE_LIMIT  = 5;
    const RATE_WINDOW = 300;   // 5 minutes

    /** A form rendered and submitted faster than this is a bot, not a reader. */
    const MIN_FILL_SECONDS = 2;

    /**
     * Subscribe an email to the site's newsletter.
     *
     * Consent-first: the row is stored `pending` and a confirmation link is emailed. The response is
     * deliberately IDENTICAL whether the address is new, already pending or already confirmed, so the
     * endpoint can never be used to enumerate who is on the list.
     *
     * @param  array $params `email`, `name`, `source`, `_hp` (honeypot), `_t` (render timestamp)
     * @return void
     */
    public function subscribe(array $params): void
    {
        // Bots fill the invisible field and submit instantly.
        if (trim((string) ($params['_hp'] ?? '')) !== '') { $this->_error('newsletter.error.rejected'); return; }
        if ($this->_tooFast($params)) { $this->_error('newsletter.error.too_fast'); return; }

        $form = new Newsletter_Form_Subscribe();
        if (!$form->isValid($params)) { $this->_formErrors($form); return; }
        $values = $form->getValues();

        $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
        if ($this->_rateLimited($ip)) { $this->_error('newsletter.error.rate_limited'); return; }

        $email  = strtolower(trim((string) $values['email']));
        $name   = trim((string) ($values['name'] ?? '')) ?: null;
        $orgId  = (string) (Tiger_Model_Table::org() ?? '');
        $source = preg_replace('/[^a-z0-9_.\-]/i', '', (string) ($params['source'] ?? 'form')) ?: 'form';

        $model = new Newsletter_Model_Subscriber();

        try {
            $token = $this->_transaction(function () use ($model, $orgId, $email, $name, $source, $ip) {
                $existing = $model->forEmail($orgId, $email);

                // Never resurrect consent silently: a previously-unsubscribed address goes back to
                // `pending` and must confirm again. An already-confirmed address is left confirmed
                // (re-subscribing is a no-op that still returns the same friendly "check your inbox").
                if ($existing && $existing['status'] === Newsletter_Model_Subscriber::STATUS_CONFIRMED) {
                    return (string) $existing['token'];
                }

                $token = Tiger_Uuid::v4();   // opaque handle for the confirm/unsubscribe links
                $data  = [
                    'email'          => $email,
                    'name'           => $name,
                    'status'         => Newsletter_Model_Subscriber::STATUS_PENDING,
                    'consent_source' => $source,
                    'consent_at'     => null,   // consent is recorded at CONFIRM, not here
                    'token'          => $token,
                    'source'         => mb_substr((string) $source, 0, 191),
                    'ip'             => $ip,
                    'user_agent'     => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
                    'user_id'        => ($this->_user_id ?? null) ?: null,
                    'unsubscribed_at'=> null,
                ];

                if ($existing) {
                    $model->update($data, $model->getAdapter()->quoteInto('newsletter_subscriber_id = ?', $existing['newsletter_subscriber_id']));
                } else {
                    $data['org_id'] = $orgId;
                    $model->insert($data);
                }
                return $token;
            });

            // Mail is best-effort I/O AFTER commit — the row exists, so a mail hiccup must never
            // surface as failure (the confirm link can always be re-sent from the admin later).
            $this->_sendConfirmation($email, $name, $token);

            $this->_success(['pending' => true], 'newsletter.subscribe.pending');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Confirm a pending subscription by its token — this is where consent is recorded.
     *
     * @param  array $params `token`
     * @return void
     */
    public function confirm(array $params): void
    {
        $model = new Newsletter_Model_Subscriber();
        $row   = $model->byToken((string) ($params['token'] ?? ''));
        if (!$row) { $this->_error('newsletter.error.token'); return; }

        try {
            if ($row['status'] !== Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED) {
                $model->update(
                    ['status' => Newsletter_Model_Subscriber::STATUS_CONFIRMED, 'consent_at' => gmdate('Y-m-d H:i:s')],
                    $model->getAdapter()->quoteInto('newsletter_subscriber_id = ?', $row['newsletter_subscriber_id'])
                );
            }
            $this->_success(['confirmed' => true], 'newsletter.confirm.done');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * Unsubscribe by token — one click, no confirmation needed (opting out must always be easy).
     *
     * @param  array $params `token`
     * @return void
     */
    public function unsubscribe(array $params): void
    {
        $model = new Newsletter_Model_Subscriber();
        $row   = $model->byToken((string) ($params['token'] ?? ''));
        if (!$row) { $this->_error('newsletter.error.token'); return; }

        try {
            $model->update(
                ['status' => Newsletter_Model_Subscriber::STATUS_UNSUBSCRIBED, 'unsubscribed_at' => gmdate('Y-m-d H:i:s')],
                $model->getAdapter()->quoteInto('newsletter_subscriber_id = ?', $row['newsletter_subscriber_id'])
            );
            $this->_success(['unsubscribed' => true], 'newsletter.unsubscribe.done');
        } catch (Throwable $e) {
            $this->_error(APPLICATION_ENV !== 'production' ? $e->getMessage() : 'core.api.error.general');
        }
    }

    /**
     * The subscriber grid (DataTables server-side). Admin only.
     *
     * @param  array $params the DataTables request + an optional `status` filter
     * @return void
     */
    public function datatable(array $params): void
    {
        if (!$this->_isAtLeastAdmin()) { $this->_error('core.api.error.not_allowed'); return; }

        $dt     = $this->_dtParams($params);
        $status = (string) ($params['status'] ?? '');
        if ($status !== '' && !in_array($status, Newsletter_Model_Subscriber::STATUSES, true)) { $status = ''; }

        $rows = (new Newsletter_Model_Subscriber())->forOrg((string) ($this->_org_id ?? ''), $status, 1000);

        $data = [];
        foreach (array_slice($rows, (int) $dt['start'], max(1, (int) $dt['length'])) as $row) {
            $data[] = [
                'newsletter_subscriber_id' => $row['newsletter_subscriber_id'],
                'email'      => (string) $row['email'],
                'name'       => (string) ($row['name'] ?? ''),
                'status'     => (string) $row['status'],
                'source'     => (string) ($row['source'] ?? ''),
                'consent_at' => $row['consent_at'],
                'created_at' => $row['created_at'],
            ];
        }

        $this->_dtResponse((int) $dt['draw'], count($rows), count($rows), $data);
    }

    // ---- internals ---------------------------------------------------------

    /** Was this submitted implausibly fast for a human? */
    protected function _tooFast(array $params)
    {
        $rendered = (int) ($params['_t'] ?? 0);
        return $rendered > 0 && (time() - $rendered) < self::MIN_FILL_SECONDS;
    }

    /** Has this address (IP) tried to subscribe too much, too fast? */
    protected function _rateLimited($ip)
    {
        return (string) $ip !== '' && (new Newsletter_Model_Subscriber())->recentCountByIp($ip, self::RATE_WINDOW) >= self::RATE_LIMIT;
    }

    /**
     * Email the double-opt-in confirmation link (best-effort — swallows its own send errors, exactly
     * like the signup verification mail).
     */
    protected function _sendConfirmation($email, $name, $token): void
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $url    = $scheme . '://' . $host . '/newsletter/confirm/token/' . rawurlencode((string) $token);

        try {
            $subject = $this->_t('newsletter.email.confirm_subject');
            $intro   = $this->_t('newsletter.email.confirm_intro');
            $cta     = $this->_t('newsletter.email.confirm_cta');
            $html    = '<p>' . htmlspecialchars($intro, ENT_QUOTES, 'UTF-8') . '</p>'
                     . '<p><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">'
                     . htmlspecialchars($cta, ENT_QUOTES, 'UTF-8') . '</a></p>';

            (new Tiger_Mail())
                ->to((string) $email, (string) ($name ?? ''))
                ->subject($subject)
                ->html($html)
                ->text($intro . "\n\n" . $url)
                ->send();
        } catch (Throwable $e) {
            error_log('Tiger newsletter confirmation mail failed: ' . $e->getMessage());
        }
    }

    /** Translate a key, falling back to the key itself (so a missing string is never a fatal). */
    protected function _t($key)
    {
        if (!$this->_translate) { return $key; }
        return $this->_translate->isTranslated($key) ? $this->_translate->translate($key) : $key;
    }
}
