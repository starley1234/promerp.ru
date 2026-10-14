<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Профиль поставщика в закупках (erp403_procurement).
 *
 * Сам поставщик живёт в справочнике контрагентов (erp301), а здесь лежат
 * закупочные условия: срок поставки, валюта, минимальная партия, отсрочка,
 * пометка «предпочтительный». Эти данные — то, чем закупщик пользуется каждый
 * день, и то, по чему `Service_Erp403_Suppliers::choose()` выбирает поставщика,
 * когда заявку собирают автоматически.
 *
 * Профиль заводится по требованию: если условий ещё нет, сервис создаёт
 * запись с настройками по умолчанию — так у любого поставщика есть один и тот
 * же набор полей, и интерфейсу не нужны проверки на NULL.
 *
 * ВАЖНО: `is_preferred` и `is_active` — SMALLINT (0/1), а не BOOLEAN.
 * PostgreSQL отдаёт boolean строками `t`/`f`, и `(bool) 'f'` — это TRUE;
 * чтобы не повторять грабли, модуль держит флаги числами (см. AGENTS.md).
 *
 * @package    Erp403
 * @category   Models
 */
class Model_Erp403_Supplier extends ORM
{
    protected $_table_name = 'erp_403_suppliers';

    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    /** @var array Оценки поставщика: ключ — балл, значение — подпись */
    public static $ratings = array(
        5 => 'Отличный',
        4 => 'Хороший',
        3 => 'Обычный',
        2 => 'Есть нарекания',
        1 => 'Плохой',
    );

    /**
     * @return  array
     */
    public function rules()
    {
        return array(
            'lead_time_days' => array(
                array('numeric'),
            ),
        );
    }

    /**
     * Подпись оценки поставщика.
     *
     * @return  string
     */
    public function rating_label()
    {
        $rating = (int) $this->rating;

        return isset(self::$ratings[$rating]) ? self::$ratings[$rating] : 'Не оценён';
    }

    /**
     * Поставщик помечен предпочтительным.
     *
     * @return  bool
     */
    public function is_preferred()
    {
        return ((int) $this->is_preferred === 1);
    }

    /**
     * Условия действуют (поставщик не выключен).
     *
     * @return  bool
     */
    public function is_active()
    {
        return ((int) $this->is_active === 1);
    }

    /**
     * Срок поставки: значение из профиля или 7 дней по умолчанию.
     *
     * @return  int
     */
    public function lead_time()
    {
        $days = (int) $this->lead_time_days;

        return $days > 0 ? $days : 7;
    }
}
