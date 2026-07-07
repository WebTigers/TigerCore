<?php
/**
 * Media module bootstrap. First-party module — the admin Media Library on top of the
 * platform media engine (Tiger_Model_Media, Tiger_Media_Storage). The resource
 * autoloader loads Media_Service_* / Media_*Controller by convention; configs/acl.ini
 * and languages/ are picked up by the core globs.
 */
class Media_Bootstrap extends Zend_Application_Module_Bootstrap
{
}
