<?php
// SPDX-License-Identifier: BSD-3-Clause
// Copyright (c) 2026 WebTigers. Tiger™ and WebTigers™ are trademarks of WebTigers.
/**
 * Code_IndexController — the Tiger Code authoring surface, in the PUMA admin shell.
 *
 * A DataTables list (index) + the snippet editor (edit) on the CodeMirror component. Thin:
 * it only READS/RENDERS — every mutation goes through Code_Service_Code. Gated to
 * `superadmin`+ (configs/acl.ini): this screen writes server-executed code.
 */
class Code_IndexController extends Tiger_Controller_Admin_Action
{
    /** @var Tiger_Model_Code */
    protected $_code;

    /**
     * Initialize the controller and its Code model.
     *
     * @return void
     */
    public function init()
    {
        parent::init();
        $this->_code = new Tiger_Model_Code();
    }

    /**
     * Render the code snippet list screen.
     *
     * @return void
     */
    public function indexAction()
    {
        $this->view->title         = 'Code — Tiger Admin';
        $this->view->useDataTables = true;
    }

    /**
     * Render the snippet editor, prefilled from the requested row when editing.
     *
     * @return void
     */
    public function editAction()
    {
        $id  = (string) $this->getParam('id', '');
        $row = $id !== '' ? $this->_code->findById($id) : null;

        $form = new Code_Form_Code();
        if ($row) {
            $form->populate([
                'code_id'     => $row->code_id,
                'name'        => $row->name,
                'description' => $row->description,
                'language'    => $row->language,
                'auto_insert' => $row->auto_insert ?: 'head',
                'priority'    => $row->priority,
                'active'      => (int) $row->active === 1,
                'code'        => $row->code,
            ]);
        }

        $this->view->title    = ($row ? 'Edit' : 'New') . ' Snippet — Tiger Admin';
        $this->view->form     = $form;
        $this->view->row      = $row;
        $this->view->versions = $row ? (new Tiger_Model_CodeVersion())->recentFor($id) : [];
    }
}
