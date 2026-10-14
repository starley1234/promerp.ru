<?php defined('SYSPATH') or die('No direct script access.');

/** Shared shell and top navigation for lot/serial traceability. */
class Controller_Erp412_Main extends Controller_Base
{
    public function before()
    {
        parent::before();

        $this->_meta['title']       = 'Прослеживаемость партий';
        $this->_meta['description'] = 'Партии и серийные номера, складские allocations, genealogy и отзыв.';
        $this->_meta['keywords']    = 'партия, serial, genealogy, прослеживаемость, отзыв';

        $this->set_menu(array(
            array('title'=>'Все партии','href'=>'/traceability','icon'=>'fas fa-code-branch','acl'=>'erp412.view'),
            array('title'=>'Новая партия','href'=>'/traceability#new-lot','icon'=>'fas fa-plus-circle','acl'=>'erp412.manage'),
            array('title'=>'Склад','href'=>'/warehouse/balances','icon'=>'fas fa-boxes-stacked','acl'=>'erp401_wms.view_stock'),
            array('title'=>'Качество','href'=>'/quality','icon'=>'fas fa-clipboard-check','acl'=>'erp405.view'),
            array('title'=>'Документы','href'=>'/documents','icon'=>'fas fa-file-lines','acl'=>'erp404.view'),
        ));
    }
}
