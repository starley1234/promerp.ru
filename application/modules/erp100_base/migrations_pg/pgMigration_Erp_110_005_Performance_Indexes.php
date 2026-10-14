<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Ядро: индексы входа в систему.
 *
 * Токены «запомнить меня» ищутся и удаляются по user_id (выход, блокировка, отзыв сессий), а внешний ключ ON DELETE CASCADE при удалении пользователя читает обе таблицы целиком.
 *
 * Миграция идемпотентна (IF NOT EXISTS); схема данных не меняется, только индексы.
 */
class pgMigration_Erp_110_005_Performance_Indexes extends pgMigration
{
    /** @var array  обычные индексы: таблица => определения */
    protected $_indexes = array(
        'user_tokens' => array('user_id'),
        'users_oauth' => array('user_id'),
    );

    /** @var array  триграммные индексы поиска: таблица => колонки */
    protected $_trigram = array();

    public function up()
    {
        $this->add_indexes($this->_indexes);
    }

    public function down()
    {
        $this->drop_added_indexes($this->_indexes, $this->_trigram);
    }
}
