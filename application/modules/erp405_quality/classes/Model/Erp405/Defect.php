<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Несоответствие (erp405_quality).
 *
 * Одна запись — это «столько-то штук с таким-то дефектом». Дефектов у партии
 * может быть несколько: царапина на трёх деталях и зазор на одной — это две
 * записи с разными кодами, а не одна общая «претензия». Так статистика по
 * кодам и причинам считается без разбора текста.
 *
 * Причина (`cause`) отделена от виновника-справочника: `supplier` —
 * несоответствие пришло с покупкой (есть `supplier_id`), `production` — из
 * цеха (есть `order_id`/`operation_id`), `warehouse` — повреждено на складе,
 * `equipment` — станок, `design` — конструкция/техпроцесс, `unknown` — пока не
 * установлена. Это то, по чему потом строится «где теряем качество».
 *
 * @package    Erp405
 * @category   Models
 */
class Model_Erp405_Defect extends ORM
{
    protected $_table_name = 'erp_405_defects';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');

    protected $_belongs_to = array(
        'inspection' => array('model' => 'Erp405_Inspection', 'foreign_key' => 'inspection_id'),
    );

    /** @var array Причины дефекта */
    public static $causes = array(
        'supplier'   => 'Поставщик',
        'production' => 'Производство',
        'warehouse'  => 'Склад',
        'equipment'  => 'Оборудование',
        'design'     => 'Конструкция/техпроцесс',
        'unknown'    => 'Не установлена',
    );

    /** @var array Цветовая маркировка причин */
    public static $cause_classes = array(
        'supplier'   => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        'production' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
        'warehouse'  => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
        'equipment'  => 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300',
        'design'     => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
        'unknown'    => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    );

    /**
     * @return  array
     */
    public function rules()
    {
        return array(
            'qty'      => array(array('not_empty'), array('numeric')),
            'severity' => array(array('numeric')),
            'cause'    => array(array('not_empty')),
        );
    }

    /**
     * @return  string
     */
    public function cause_label()
    {
        return isset(self::$causes[$this->cause]) ? self::$causes[$this->cause] : (string) $this->cause;
    }

    /**
     * @return  string
     */
    public function cause_class()
    {
        return isset(self::$cause_classes[$this->cause]) ? self::$cause_classes[$this->cause] : '';
    }

    /**
     * @return  string
     */
    public function severity_label()
    {
        return isset(Model_Erp405_DefectCode::$severities[(int) $this->severity])
            ? Model_Erp405_DefectCode::$severities[(int) $this->severity]
            : 'Важность '.(int) $this->severity;
    }
}
