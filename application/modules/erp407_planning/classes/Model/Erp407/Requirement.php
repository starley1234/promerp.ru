<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Строка расчёта потребности (erp407_planning).
 *
 * Это снимок: расчёт запускается заново — появляется новый `run_uuid`, старые
 * строки остаются как история («почему вчера заказали именно столько»).
 * Кратко, что означает каждое число:
 *
 *   demand_qty    — сколько нужно по программе (или по заказам производства);
 *   stock_qty     — свободный остаток на складе (минус резервы);
 *   reserved_qty  — что уже зарезервировано под другие заказы;
 *   wip_qty       — незавершёнка: операции цеха, которые ещё не закрыты;
 *   on_order_qty  — что уже заказано (утверждённые предложения, прошлые прогоны);
 *   covered_qty   — итог покрытия: сколько спроса закрыто;
 *   deficit_qty   — чего не хватает;
 *   order_qty     — сколько заказать с учётом минимальной партии и кратности;
 *   order_date    — когда заказать, чтобы успеть к сроку (срок − срок поставки);
 *   late          — дата заказа уже в прошлом: без ускорения срок будет сорван.
 *
 * @package    Erp407
 * @category   Models
 */
class Model_Erp407_Requirement extends ORM
{
    protected $_table_name = 'erp_407_requirements';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');

    protected $_belongs_to = array(
        'program' => array('model' => 'Erp407_Program', 'foreign_key' => 'program_id'),
    );

    /** @var array Чем закрываем потребность */
    public static $supply_types = array(
        'purchase' => 'Закупка',
        'produce'  => 'Собственное производство',
    );

    /**
     * Подпись способа обеспечения.
     *
     * @return  string
     */
    public function supply_label()
    {
        return isset(self::$supply_types[$this->supply_type]) ? self::$supply_types[$this->supply_type] : (string) $this->supply_type;
    }

    /**
     * Процент покрытия спроса (0…100).
     *
     * @return  float
     */
    public function coverage()
    {
        $demand = (float) $this->demand_qty;

        if ($demand <= 0)
        {
            return 100.0;
        }

        return round(min(100, (float) $this->covered_qty / $demand * 100), 1);
    }

    /**
     * Есть ли дефицит.
     *
     * @return  bool
     */
    public function has_deficit()
    {
        return ((float) $this->deficit_qty > 0);
    }

    /**
     * Дата заказа уже прошла.
     *
     * PostgreSQL отдаёт boolean строками `t`/`f`, и `(bool) 'f'` — это `true`.
     * Поэтому приводим значение помощником проекта: иначе «просрочено» горит
     * у всех позиций сразу, а приоритет предложения всегда «срочно».
     *
     * @return  bool
     */
    public function is_late()
    {
        return Erp::bool($this->late);
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
