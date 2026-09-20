<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Newsletter_Service_Audience — the newsletter subscribers exposed as an email AUDIENCE segment.
 *
 * This is the PROVIDER side of the Tiger_Audience registry (like Member_Service_Audience for paid
 * members). It answers "who are the confirmed newsletter subscribers?" as a segment descriptor + a
 * resolved contact list. Newsletter_Bootstrap::_initAudience registers the static methods so an email
 * tool (TigerList) lists the segment and resolves it to recipients.
 *
 * BOUNDARY: this provider conveys OPT-IN membership (confirmed subscribers) only — never a licence to
 * email. TigerList (the consumer) still applies its own consent / opt-out / suppression before a send.
 * The registry contract is explicit that a resolved audience is entitlement, not consent.
 *
 * @api
 */
class Newsletter_Service_Audience extends Tiger_Service_Service
{
    /** The one segment this module offers: every confirmed subscriber. */
    const SEGMENT = 'newsletter:confirmed';

    /** /api (admin): the newsletter segments an email tool / export can target. */
    public function segments(array $params): void
    {
        if (!$this->_isAtLeastAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $this->_success(['segments' => self::listSegments((string) ($this->_org_id ?? ''))]);
    }

    /** /api (admin): resolve a segment to its contacts. @param array $params segment */
    public function members(array $params): void
    {
        if (!$this->_isAtLeastAdmin()) { $this->_error('core.api.error.not_allowed'); return; }
        $this->_success(['members' => self::resolveMembers((string) ($this->_org_id ?? ''), (string) ($params['segment'] ?? ''))]);
    }

    /**
     * The segment descriptors for an org — reusable in-process by a consumer (TigerList) via the
     * Tiger_Audience registry.
     *
     * @param  string $orgId
     * @return array<int,array{key:string,label:string,count:int}>
     */
    public static function listSegments($orgId): array
    {
        return [[
            'key'   => self::SEGMENT,
            'label' => 'Newsletter subscribers',
            'count' => (new Newsletter_Model_Subscriber())->confirmedCount((string) $orgId),
        ]];
    }

    /**
     * Resolve a segment key to its contacts: [{user_id, email, name}]. Only the one known key returns
     * anything; anything else is an empty list.
     *
     * @param  string $orgId
     * @param  string $segmentKey
     * @return array<int,array{user_id:string,email:string,name:string}>
     */
    public static function resolveMembers($orgId, $segmentKey): array
    {
        if ((string) $segmentKey !== self::SEGMENT) { return []; }

        $out = [];
        foreach ((new Newsletter_Model_Subscriber())->confirmed((string) $orgId) as $row) {
            $out[] = [
                'user_id' => (string) ($row['user_id'] ?? ''),
                'email'   => (string) $row['email'],
                'name'    => (string) ($row['name'] ?? ''),
            ];
        }
        return $out;
    }
}
