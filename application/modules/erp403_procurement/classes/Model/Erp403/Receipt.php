<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Приёмка товара от поставщика (erp403_procurement).
 *
 * Приёмка — точка, где закупка становится складом: при проведении каждая
 * строка превращается в приход `erp401` и в запись истории цен, а строка
 * заказа получает принятое количество. Поэтому у приёмки есть состояние:
 * черновик можно править, проведённую приёмку — только отменить (отмена
 * не трогает склад: расход оформляется отдельным документом).
 *
 * Состояния: `draft` — черновик, `posted` — проведена (приход на складе),
 * `cancelled` — отменена.
 *
 * @package    Erp403
 * @category   Models
 */
class Model_Erp403_Receipt extends ORM
{
    protected $_table_name = 'erp_403_receipts';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    protected $_has_many = array(
        'lines' => array('model' => 'Erp403_ReceiptLine', 'foreign_key' => 'receipt_id'),
    );

    /** @var array Состояния приёмки: ключи совпадают с CHECK в БД */
    public static $statuses = array(
        'draft'     => 'Черновик',
        'posted'    => 'Проведена',
        'cancelled' => 'Отменена',
    );

    /** @var array Цветовая маркировка состояния для интерфейса */
    public static $status_classes = array(
        'draft'     => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        'posted'    => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
        'cancelled' => 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
    );

    /**
     * @return  array
     */
    public function rules()
    {
        return array(
            'number' => array(
                array('not_empty'),
                array('max_length', array(':value', 32)),
            ),
            'status' => array(
                array('not_empty'),
            ),
        );
    }

    /**
     * Подпись состояния.
     *
     * @return  string
     */
    public function status_label()
    {
        return isset(self::$statuses[$this->status]) ? self::$statuses[$this->status] : (string) $this->status;
    }

    /**
     * CSS-классы состояния для интерфейса.
     *
     * @return  string
     */
    public function status_class()
    {
        return isset(self::$status_classes[$this->status]) ? self::$status_classes[$this->status] : '';
    }

    /**
     * Приёмку можно править (только черновик).
     *
     * @return  bool
     */
    public function is_editable()
    {
        return ((string) $this->status === 'draft');
    }

    /**
     * Приёмку можно провести.
     *
     * @return  bool
     */
    public function can_post()
    {
        return ((string) $this->status === 'draft');
    }

    /**
     * Документ поставщика (накладная) заполнен.
     *
     * @return  bool
     */
    public function has_document()
    {
        return (trim((string) $this->doc_number) !== '');
    }

    /**
     * Новая запись получает uuid до вставки.
     *
     * Колонку `uuid` заполняет и база (`gen_random_uuid()`), но выданное
     * значение модель не знает до перечитывания строки — а оно нужно тут же:
     * в журнале действий (`erp119`), событиях и ответе JSON API. Пустой uuid в
     * этих местах означает «ссылку на объект потеряли»: запись журнала
     * оказывается ни к чему не привязанной. Поэтому идентификатор выдаётся
     * помощником `Erp::uuid()` до сохранения, а серверный default остаётся
     * подстраховкой для вставок в обход ORM.
     *
     * @param   Validation|null $validation
     * @return  ORM
     */
    public function save(?Validation $validation = NULL)
    {
        if ( ! $this->loaded() AND (string) $this->uuid === '')
        {
            $this->uuid = Erp::uuid();
        }

        return parent::save($validation);
    }

}
