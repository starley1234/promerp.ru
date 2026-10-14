<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Базовая модель для модуля.
 * 
 * Определяет подключение к базе данных PostgreSQL и общие свойства
 * для всех моделей данного модуля.
 * 
 */
abstract class Model_Erp227_Base extends ORM {

    /**
     * @var string Имя конфигурации базы данных (PostgreSQL)
     */
    protected $_db_group = 'default';
    
    /**
     * Перехватываем получение данных для всех моделей
     */
    public function get($column)
    {
        $value = parent::get($column);

        // Если это строка 'f' или 't' (характерно для Postgres boolean)
        if ($value === 'f') return FALSE;
        if ($value === 't') return TRUE;

        return $value;
    }


}