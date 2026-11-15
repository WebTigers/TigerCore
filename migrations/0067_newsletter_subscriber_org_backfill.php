<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Migration 0067 — give legacy newsletter subscribers an owning org (TIGER-511).
 *
 * Guest sign-ups used to be stamped `org_id = ''` (a guest has no authenticated org) and every org's
 * reads added the '' tier, so every org's audience included every '' subscriber. Subscribe now stamps
 * the SITE's org and reads use the org alone — so the existing '' rows must be attributed or they
 * would vanish from every list.
 *
 * They move to the INSTALL org (`Tiger_Model_Org::installOrgId()` — the configured `tiger.site.org_id`,
 * else the founding org). On a single-site install that is exactly right: there was only ever one list.
 * On a MULTI-SITE install a pre-existing '' row cannot be attributed to the site it came from (that
 * was never recorded), so it also lands in the install org; an operator who needs otherwise moves
 * those rows by hand.
 *
 * `email` is unique per org. Where the install org already holds the same address, the '' row is NOT
 * moved: if it carried confirmed consent and the org's row is still pending, that consent is copied
 * onto the org's row (an unsubscribe on the org's row always wins), then the '' row is soft-deleted.
 *
 * DATA migration (a PHP callable — see Tiger_Db_Migrator). Idempotent: a re-run finds no live '' rows.
 * One-way: `down` is a no-op.
 */
return [
    'up' => [
        function ($db) {
            $configured = '';
            if (Zend_Registry::isRegistered('Zend_Config')) {
                $t = Zend_Registry::get('Zend_Config')->get('tiger');
                $s = $t ? $t->get('site') : null;
                $configured = ($s && $s->get('org_id')) ? (string) $s->get('org_id') : '';
            }
            $org = Tiger_Model_Org::installOrgId($configured);
            if ($org === '') {
                return;   // a pre-install database: no org to own anything yet
            }

            $legacy = $db->fetchAll(
                $db->select()->from('newsletter_subscriber')
                    ->where('org_id = ?', '')
                    ->where('deleted = ?', 0)
            );
            if (!$legacy) {
                return;
            }

            $owned = [];
            foreach ($db->fetchAll(
                $db->select()->from('newsletter_subscriber', ['newsletter_subscriber_id', 'email', 'status'])
                    ->where('org_id = ?', $org)
            ) as $r) {
                $owned[strtolower((string) $r['email'])] = $r;
            }

            $now = gmdate('Y-m-d H:i:s');
            foreach ($legacy as $row) {
                $id    = (string) $row['newsletter_subscriber_id'];
                $email = strtolower((string) $row['email']);

                if (!isset($owned[$email])) {
                    $db->update('newsletter_subscriber', ['org_id' => $org, 'updated_at' => $now],
                        $db->quoteInto('newsletter_subscriber_id = ?', $id));
                    continue;
                }

                // A collision: keep the org's row, carrying confirmed consent onto a still-pending one.
                $mine = $owned[$email];
                if ((string) $row['status'] === 'confirmed' && (string) $mine['status'] === 'pending') {
                    $db->update('newsletter_subscriber', [
                        'status'         => 'confirmed',
                        'consent_at'     => $row['consent_at'],
                        'consent_source' => $row['consent_source'],
                        'updated_at'     => $now,
                    ], $db->quoteInto('newsletter_subscriber_id = ?', (string) $mine['newsletter_subscriber_id']));
                }
                $db->update('newsletter_subscriber', ['deleted' => 1, 'updated_at' => $now],
                    $db->quoteInto('newsletter_subscriber_id = ?', $id));
            }
        },
    ],
    'down' => [],
];
