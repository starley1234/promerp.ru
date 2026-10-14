<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Склад: отбор резервов по складу и состоянию.
 *
 * Список резервов фильтруется по складу (Stock::reservations).
 *
 * Миграция идемпотентна (IF NOT EXISTS); схема данных не меняется, только индексы.
 */
class pgMigration_003_Erp401_Wms_PerformanceIndexes extends pgMigration
{
    /** @var array  обычные индексы: таблица => определения */
    protected $_indexes = array(
        'erp_401_reservations' => array('warehouse_id, status'),
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
