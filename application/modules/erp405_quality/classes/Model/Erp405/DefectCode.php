<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Код дефекта (erp405_quality).
 *
 * Справочник отвечает на вопрос «что именно не так» одинаково для всех: и
 * приёмочный контроль, и цех, и разбор рекламаций называют дефект одним кодом,
 * поэтому статистика складывается без расшифровки свободного текста.
 *
 * Категория — из чего дефект вырос (`size`, `surface`, `material`, `assembly`,
 * `document`, `packaging`, `other`), важность — 1 (критичный, партию нельзя
 * выпускать) … 3 (незначительный).
 *
 * @package    Erp405
 * @category   Models
 */
class Model_Erp405_DefectCode extends ORM
{
    protected $_table_name = 'erp_405_defect_codes';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    /** @var array Категории дефекта */
    public static $categories = array(
        'size'      => 'Размер и геометрия',
        'surface'   => 'Поверхность и покрытие',
        'material'  => 'Материал',
        'assembly'  => 'Сборка',
        'document'  => 'Документы',
        'packaging' => 'Упаковка',
        'other'     => 'Прочее',
    );

    /** @var array Важность: 1 — критично, 3 — незначительно */
    public static $severities = array(
        1 => 'Критичный',
        2 => 'Значительный',
        3 => 'Незначительный',
    );

    /** @var array Цветовая маркировка важности */
    public static $severity_classes = array(
        1 => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
        2 => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        3 => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
    );

    /**
     * @return  array
     */
    public function rules()
    {
        return array(
            'code'     => array(array('not_empty'), array('max_length', array(':value', 32))),
            'name'     => array(array('not_empty'), array('max_length', array(':value', 255))),
            'category' => array(array('not_empty')),
            'severity' => array(array('not_empty'), array('numeric')),
        );
    }

    /**
     * @return  array
     */
    public function filters()
    {
        return array(
            'code'     => array(array('trim'), array('strtoupper')),
            'name'     => array(array('trim')),
            'category' => array(array('trim')),
        );
    }

    /**
     * @return  string
     */
    public function category_label()
    {
        return isset(self::$categories[$this->category]) ? self::$categories[$this->category] : (string) $this->category;
    }

    /**
     * @return  string
     */
    public function severity_label()
    {
        return isset(self::$severities[(int) $this->severity])
            ? self::$severities[(int) $this->severity]
            : 'Важность '.(int) $this->severity;
    }

    /**
     * @return  bool
     */
    public function is_active()
    {
        return ((int) $this->is_active === 1);
    }

    /**
     * @return  bool
     */
    public function is_critical()
    {
        return ((int) $this->severity === 1);
    }
}
