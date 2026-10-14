<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * Модель роли системы ERP100.
 * Расширяет стандартную модель Auth_Role.
 */
class Model_Erp100_Role extends Model_Auth_Role {

    /**
     * ВРУЧНУЮ прописываем колонки.
     * Теперь ORM вообще не будет опрашивать базу о структуре.
     */
    protected $_table_columns = [
        'id'          => ['type' => 'int'],
        'name'        => ['type' => 'string'],
        'description' => ['type' => 'string'],
    ];

    /**
     * @var array Связи типа "многие ко многим"
     */
    protected $_has_many = array(
        // Модель пользователя проекта — Model_User (таблица users). Имя
        // 'Erp100_User' здесь давало бы таблицу erp100_users, которой нет:
        // ORM выводит таблицу из имени класса, а Erp100_User наследует
        // Model_Auth_User, не переопределяя _table_name.
        'users' => array('model' => 'User', 'through' => 'roles_users'),
    );

}