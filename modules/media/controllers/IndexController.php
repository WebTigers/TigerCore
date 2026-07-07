<?php
/**
 * Media_IndexController — the Media Library screen (admin shell). Thin: renders the
 * shell (a drag-drop uploader + a DataTables grid); all data + mutations go through the
 * /api service Media_Service_Media. ACL-gated admin+ (configs/acl.ini).
 */
class Media_IndexController extends Tiger_Controller_Action
{
    public function init()
    {
        parent::init();
        $this->_helper->layout()->setLayout('admin');
    }

    public function indexAction()
    {
        $this->view->title         = 'Media Library — Tiger Admin';
        $this->view->useDataTables = true;
    }
}
