<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Message_Bootstrap — the inbox, and the bell in the header that points at it (TIGER-114).
 *
 * The account-menu item is declared in configs/navigation-account.ini (zero code). The header icon
 * is registered here because it needs LOGIC: a badge callable that counts the current user's unread
 * messages at render time.
 */
class Message_Bootstrap extends Zend_Application_Module_Bootstrap
{
    /**
     * The header bell.
     *
     * The badge is a callable, not a number: the header renders on every admin page, so the count is
     * computed at render, for the signed-in user, only if the item passed the ACL filter. It is one
     * indexed COUNT on message_recipient (user_id, read_at, deleted) — cheap enough for every page,
     * and fail-soft: no identity or no table renders no badge, never a broken header.
     */
    protected function _initMessageHeader()
    {
        if (!class_exists('Tiger_Admin_Header')) { return; }

        Tiger_Admin_Header::register([
            'key'      => 'messages',
            'label'    => 'message.header.label',
            'icon'     => 'fa-bell',
            'href'     => '/message',
            'resource' => 'Message_IndexController',
            'order'    => 10,
            'badge'    => static function () {
                $identity = Zend_Auth::getInstance()->getIdentity();
                $userId   = (string) ($identity->user_id ?? '');
                return $userId === '' ? 0 : (new Tiger_Model_MessageRecipient())->countUnread($userId);
            },
            // Clicking the bell opens a QUICK-VIEW fly-out of the latest 20 messages before the full
            // management screen (TIGER-129): each row is a title + a short preview + an archive action,
            // with a "view all" footer link to /message. Declarative and module-agnostic — the theme
            // fills the panel over /api and translates the label KEYS below in the active locale.
            'flyout'   => [
                'endpoint' => ['module' => 'message', 'service' => 'message', 'method' => 'recent'],
                'action'   => ['module' => 'message', 'service' => 'message', 'method' => 'archive'],
                'view_all' => '/message',
                'labels'   => [
                    'title'        => 'message.flyout.title',
                    'empty'        => 'message.flyout.empty',
                    'view_all'     => 'message.flyout.view_all',
                    'from'         => 'message.list.from',
                    'system'       => 'message.list.system',
                    'archive'      => 'message.action.archive',
                    'archived'     => 'message.archived',
                    'load_failed'  => 'message.error.load_failed',
                    'action_failed'=> 'message.error.action_failed',
                ],
            ],
        ]);
    }
}
