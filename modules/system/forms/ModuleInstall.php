<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * System_Form_ModuleInstall — every install path on the Add Module screen: from a GitHub URL
 * (install), an uploaded .zip (upload), and a paid vendor's authority (connectVendor → pinVendor →
 * installLicensed) (System_Service_Modules, TIGER-528).
 *
 * install reads `url`, `ref`, `force`; upload reads `force` (the archive rides as the multipart file
 * `archive`, which the service reads from $_FILES — the token rides in the same FormData);
 * connectVendor / pinVendor read `vendor`; installLicensed reads `vendor`, `product` (or its alias
 * `slug`), `key`, `repository`, `force`. The service keeps its refusals and error keys.
 *
 * @api
 */
class System_Form_ModuleInstall extends System_Form_ModuleWrite
{
    /**
     * The declared schema.
     *
     * @return array<int,array> [type, name, options] triples
     */
    protected function elements(): array
    {
        return [
            $this->_field('url'),
            $this->_field('ref'),
            $this->_field('force'),
            $this->_field('vendor'),
            $this->_field('product'),
            $this->_field('slug'),
            $this->_field('key'),
            $this->_field('repository'),
        ];
    }
}
