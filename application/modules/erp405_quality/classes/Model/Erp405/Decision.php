<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Решение по партии (erp405_quality).
 *
 * Таблица — журнал: она объясняет, почему остаток оказался там, где оказался.
 * «В утиль» без записи решения выглядит как пропавшие со склада детали, а с
 * записью — как работа контролёра: `from_warehouse_id` → `to_warehouse_id`,
 * количество и ссылка на движение склада (`movement_ref`).
 *
 * Решения делятся на две семьи:
 *   * судьба брака — `scrap` (утиль), `return` (поставщику), `rework`
 *     (в переделку), `use_as_is` (годен как есть), `sort` (сортировка);
 *   * склад — `quarantine` (партия изолирована), `release` (выпущена из
 *     карантина обратно в оборот).
 *
 * @package    Erp405
 * @category   Models
 */
class Model_Erp405_Decision extends ORM
{
    protected $_table_name = 'erp_405_decisions';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');

    protected $_belongs_to = array(
        'inspection' => array('model' => 'Erp405_Inspection', 'foreign_key' => 'inspection_id'),
    );

    /** @var array Что можно решить (ключи совпадают с CHECK в БД) */
    public static $kinds = array(
        'use_as_is' => 'Использовать как есть',
        'rework'    => 'В переделку',
        'return'    => 'Вернуть поставщику',
        'scrap'     => 'В утиль',
        'sort'      => 'Сортировка',
        'quarantine'=> 'Изолировать на карантин',
        'release'   => 'Выпустить из карантина',
    );

    /** @var array Решения, которые принимают по браку (остальные — склад) */
    public static $dispositions = array('use_as_is', 'rework', 'return', 'scrap', 'sort');

    /**
     * @return  string
     */
    public function kind_label()
    {
        if (isset(self::$kinds[$this->decision]))
        {
            return self::$kinds[$this->decision];
        }

        return isset(Model_Erp405_Inspection::$decisions[$this->decision])
            ? Model_Erp405_Inspection::$decisions[$this->decision]
            : (string) $this->decision;
    }

    /**
     * Решение о судьбе брака (а не о складе).
     *
     * @return  bool
     */
    public function is_disposition()
    {
        return in_array((string) $this->decision, self::$dispositions, TRUE);
    }
}
