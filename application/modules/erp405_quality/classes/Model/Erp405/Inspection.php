<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Контроль качества (erp405_quality).
 *
 * Один документ контроля — это одна партия и один вопрос: «годится или нет».
 * Откуда пришла партия, записано в `source`:
 *
 *   manual          — контроль завели руками (например, разбор рекламации);
 *   erp403_receipt  — входной контроль позиции приёмки закупки;
 *   erp402_order    — контроль партии производственного заказа;
 *   erp408_operation— контроль после операции цеха (брак количеством);
 *   erp407_proposal — контроль предложения планирования (пока не используется,
 *                     зацепка на будущее).
 *
 * Состояния: `draft` — контроль заведён, но не начат, `registered` — проверка
 * идёт (или ждёт решения), `decided` — решение принято и исполнено,
 * `closed` — контроль закрыт, `cancelled` — отменён.
 *
 * Итог (`result`) отделён от решения (`decision`) намеренно: «партия не прошла»
 * и «что с ней делать» — разные вопросы, и второй может решаться позже, чем
 * первый. Поэтому `failed` без решения — нормальное состояние: партия лежит на
 * карантине, а решение принимает человек.
 *
 * @package    Erp405
 * @category   Models
 */
class Model_Erp405_Inspection extends ORM
{
    protected $_table_name = 'erp_405_inspections';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    protected $_has_many = array(
        'defects'   => array('model' => 'Erp405_Defect', 'foreign_key' => 'inspection_id'),
        'decisions' => array('model' => 'Erp405_Decision', 'foreign_key' => 'inspection_id'),
    );

    /** @var array Состояния контроля */
    public static $statuses = array(
        'draft'      => 'Черновик',
        'registered' => 'Идёт контроль',
        'decided'    => 'Решение принято',
        'closed'     => 'Закрыт',
        'cancelled'  => 'Отменён',
    );

    /** @var array Цветовая маркировка состояния */
    public static $status_classes = array(
        'draft'      => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        'registered' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        'decided'    => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
        'closed'     => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300',
        'cancelled'  => 'bg-gray-200 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
    );

    /** @var array Виды контроля */
    public static $kinds = array(
        'incoming'    => 'Входной (приёмка)',
        'operational' => 'Операционный (цех)',
        'final'       => 'Итоговый (выпуск)',
    );

    /** @var array Откуда пришла партия */
    public static $sources = array(
        'manual'           => 'Заведён вручную',
        'erp403_receipt'   => 'Из приёмки закупки',
        'erp402_order'     => 'Из производственного заказа',
        'erp408_operation' => 'Из операции цеха',
        'erp407_proposal'  => 'Из предложения планирования',
    );

    /** @var array Итоги контроля */
    public static $results = array(
        'passed' => 'Годная',
        'failed' => 'Брак',
    );

    /** @var array Решения по браку */
    public static $decisions = array(
        'use_as_is' => 'Использовать как есть',
        'rework'    => 'В переделку',
        'return'    => 'Вернуть поставщику',
        'scrap'     => 'В утиль',
        'sort'      => 'Сортировка',
    );

    /** @var array Цвета решений */
    public static $decision_classes = array(
        'use_as_is' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        'rework'    => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300',
        'return'    => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        'scrap'     => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
        'sort'      => 'bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300',
    );

    /**
     * @return  array
     */
    public function rules()
    {
        return array(
            'kind'   => array(array('not_empty')),
            'source' => array(array('not_empty')),
            'qty'    => array(array('numeric')),
        );
    }

    /**
     * @return  string
     */
    public function status_label()
    {
        return isset(self::$statuses[$this->status]) ? self::$statuses[$this->status] : (string) $this->status;
    }

    /**
     * @return  string
     */
    public function status_class()
    {
        return isset(self::$status_classes[$this->status]) ? self::$status_classes[$this->status] : '';
    }

    /**
     * @return  string
     */
    public function kind_label()
    {
        return isset(self::$kinds[$this->kind]) ? self::$kinds[$this->kind] : (string) $this->kind;
    }

    /**
     * @return  string
     */
    public function source_label()
    {
        return isset(self::$sources[$this->source]) ? self::$sources[$this->source] : (string) $this->source;
    }

    /**
     * @return  string
     */
    public function result_label()
    {
        return ($this->result === NULL OR $this->result === '')
            ? 'Не проверено'
            : (isset(self::$results[$this->result]) ? self::$results[$this->result] : (string) $this->result);
    }

    /**
     * @return  string
     */
    public function decision_label()
    {
        return ($this->decision === NULL OR $this->decision === '')
            ? 'Решения нет'
            : (isset(self::$decisions[$this->decision]) ? self::$decisions[$this->decision] : (string) $this->decision);
    }

    /**
     * @return  string
     */
    public function decision_class()
    {
        return isset(self::$decision_classes[$this->decision]) ? self::$decision_classes[$this->decision] : '';
    }

    /**
     * Партия ещё правится (можно добавлять дефекты и менять количество).
     *
     * @return  bool
     */
    public function is_editable()
    {
        return (in_array($this->status, array('draft', 'registered'), TRUE));
    }

    /**
     * Контроль ждёт решения человека: брак выявлен, решения ещё нет.
     *
     * @return  bool
     */
    public function is_pending_decision()
    {
        return ((string) $this->result === 'failed' AND ($this->decision === NULL OR $this->decision === ''));
    }

    /**
     * Годное количество партии.
     *
     * @return  float
     */
    public function good_qty()
    {
        return max(0, (float) $this->qty - (float) $this->defect_qty);
    }

    /**
     * Доля брака, % (0, если партия пустая).
     *
     * @return  float
     */
    public function defect_share()
    {
        $qty = (float) $this->qty;

        return ($qty > 0) ? round((float) $this->defect_qty / $qty * 100, 1) : 0.0;
    }

    /**
     * Есть ли партия на карантинном складе.
     *
     * @return  bool
     */
    public function is_quarantined()
    {
        return ((float) $this->quarantine_qty > 0 AND (int) $this->quarantine_warehouse_id > 0);
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
