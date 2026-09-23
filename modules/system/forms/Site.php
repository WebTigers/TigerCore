<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * System_Form_Site — map a request host to an org (multi-site).
 *
 * A hostname (e.g. `shop.example.com`) + the org whose public site that host should serve. The org
 * dropdown is built from the live org list. Host normalization + uniqueness live in
 * System_Service_Sites::save (a host is unique across the install), so the form only checks shape.
 *
 * @api
 */
class System_Form_Site extends Tiger_Form
{
    protected function elements(): array
    {
        $control = ['class' => 'form-control'];
        $select  = ['class' => 'form-select'];

        $orgOpts  = [];
        $orgModel = new Tiger_Model_Org();
        foreach ($orgModel->fetchAll($orgModel->activeSelect()->order('name ASC')) as $o) {
            $orgOpts[$o->org_id] = $o->name;
        }

        return [
            ['hidden', 'site_domain_id', []],

            ['text', 'domain', [
                'required'   => true,
                'filters'    => ['StringTrim', 'StringToLower'],
                'validators' => [
                    ['Regex', false, ['pattern' => '/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/']],
                ],
                'attribs'    => array_merge($control, ['id' => 'system-site-domain', 'maxlength' => 191,
                                                       'placeholder' => 'shop.example.com']),
            ]],

            ['select', 'org_id', [
                'required'     => true,
                'multiOptions' => $orgOpts,
                'attribs'      => array_merge($select, ['id' => 'system-site-org']),
            ]],
        ];
    }
}
