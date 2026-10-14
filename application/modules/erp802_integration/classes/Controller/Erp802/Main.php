<?php defined('SYSPATH') or die('No direct script access.');

/** Shared shell and top navigation for controlled 1C/file exchanges. */
class Controller_Erp802_Main extends Controller_Base
{
    public function before()
    {
        parent::before();

        $this->_meta['title']       = 'Интеграции';
        $this->_meta['description'] = 'Контролируемый обмен с 1С и файлами: dry-run, протокол и идемпотентность.';
        $this->_meta['keywords']    = '1С, интеграция, импорт, CSV, JSON, протокол';

        $this->set_menu(array(
            array('title'=>'Пакеты','href'=>'/integration','icon'=>'fas fa-right-left','acl'=>'erp802.view'),
            array('title'=>'Загрузить пакет','href'=>'/integration#new-package','icon'=>'fas fa-file-import','acl'=>'erp802.import'),
            array('title'=>'Номенклатура','href'=>'/erp227_objects','icon'=>'fas fa-box','acl'=>'core_registry.view'),
            array('title'=>'Журнал событий','href'=>'/erp208_events','icon'=>'fas fa-wave-square','acl'=>'erp208_events.view_dashboard'),
        ));
    }
}
