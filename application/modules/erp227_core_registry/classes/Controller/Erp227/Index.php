<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Основной интерфейс универсального реестра объектов.
 * Реализует дерево ltree, фильтрацию и таблицу сущностей.
 * 
 * @package Erp227
 * @version 1.0.0
 */
class Controller_Erp227_Index extends Controller_Erp227_Main {

    /**
     * Главная страница реестра: Дерево + Таблица
     */
    public function action_index()
    {
        $this->redirect('/erp227_objects');
    }


}