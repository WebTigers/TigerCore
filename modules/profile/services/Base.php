<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Profile_Service_Base — shared plumbing for the AJAX-reused profile collection services
 * (Contacts, Addresses). Two concerns those tabs both have:
 *
 *  - CSRF rotation. A Zend hash token hop-expires after ~1 use, but these tabs add/edit/delete
 *    many times from one page load. So every response hands back a FRESH token for the tab's form
 *    (`_freshToken`) and the client swaps it in for the next call — the same rotation that keeps the
 *    agent aside alive.
 *    (Deletes and the SMS steps validate through a Tiger_Form sharing the tab's salt — e.g.
 *    Profile_Form_AddressDelete — never a hand-rolled token check; TIGER-528 removed `_validCsrf()`,
 *    which also failed OPEN on any exception.)
 *  - Solo primary. `is_primary` is single-per-collection: setting one clears it on the user's other
 *    links (`_soloPrimary`), so there's never more than one primary contact / primary address.
 *
 * @api
 */
abstract class Profile_Service_Base extends Tiger_Service_Service
{
    /**
     * Mint (and store in the session) a fresh CSRF token for the given Tiger_Form subclass, so an
     * AJAX-reused form's token doesn't hop-expire mid-session.
     *
     * @param  string $formClass a Tiger_Form subclass name
     * @return string the fresh token, or '' if it couldn't be minted
     */
    protected function _freshToken($formClass)
    {
        try {
            $el = (new $formClass())->getElement('_csrf');
            $el->setView(new Zend_View());
            $el->render();
            return (string) $el->getValue();
        } catch (Throwable $e) {
            return '';
        }
    }

    /**
     * Enforce a single primary within an owner's collection: clear is_primary on every link but
     * $keepId. Owner-agnostic — pass the owner column so the same rule serves a user's contacts
     * ($ownerCol = 'user_id') and an org's ($ownerCol = 'org_id').
     *
     * @param  Tiger_Model_Table $linkModel the user_/org_ contact/address link model
     * @param  string            $pkColumn  its primary-key column
     * @param  string            $ownerCol  the owning-scope column ('user_id' | 'org_id')
     * @param  string            $ownerId   the owner id
     * @param  string            $keepId    the link that stays primary
     * @return void
     */
    protected function _soloPrimary($linkModel, $pkColumn, $ownerCol, $ownerId, $keepId)
    {
        $db = $linkModel->getAdapter();
        $linkModel->update(
            ['is_primary' => 0],
            $db->quoteInto($ownerCol . ' = ?', (string) $ownerId) . ' AND ' . $db->quoteInto($pkColumn . ' <> ?', (string) $keepId)
        );
    }
}
