<?php
/**
 * Blog module bootstrap.
 *
 * First-party blog/articles feature. An article is a `page` row (type='article') whose
 * scalar metadata rides in page.meta; this module is the authoring surface + the two
 * relational tables it owns (taxonomy, page_taxonomy). The content engine itself stays
 * in the platform layer (Tiger_Model_Page, the renderer, the page dispatcher).
 *
 * Extending Zend_Application_Module_Bootstrap gives the module its resource autoloader,
 * so Blog_Model_* (models/), Blog_Service_* (services/) and Blog_Form_* (forms/) load by
 * convention; controllers load via the registered module dir; configs/acl.ini and
 * languages/ are picked up by the core globs.
 */
class Blog_Bootstrap extends Zend_Application_Module_Bootstrap
{
}
