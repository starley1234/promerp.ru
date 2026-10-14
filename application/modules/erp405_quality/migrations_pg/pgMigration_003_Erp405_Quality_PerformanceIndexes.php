<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Качество: поиск по кодам дефектов.
 *
 * Списки ищут по части строки (`LIKE '%текст%'`), а обычный индекс по `search_text`
 * работает только для «начинается с»: каждый запрос читал таблицу целиком. GIN по
 * триграммам (pg_trgm) ускоряет и LIKE, и ILIKE. Если расширения нет на сервере или нет
 * прав его создать, миграция не падает: поиск остаётся рабочим, но без ускорения.
 *
 * Миграция идемпотентна (IF NOT EXISTS); схема данных не меняется, только индексы.
 */
class pgMigration_003_Erp405_Quality_PerformanceIndexes extends pgMigration
{
    /** @var array  обычные индексы: таблица => определения */
    protected $_indexes = array();

    /** @var array  триграммные индексы поиска: таблица => колонки */
    protected $_trigram = array(
        'erp_405_defect_codes' => array('search_text'),
    );

    public function up()
    {
        $this->add_trigram_indexes($this->_trigram);
    }

    public function down()
    {
        $this->drop_added_indexes($this->_indexes, $this->_trigram);
    }
}
