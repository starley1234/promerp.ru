<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Приведение типов из PostgreSQL (erp199_sqr).
 *
 * Драйвер отдаёт boolean строкой `t`/`f`, а `'f'` в PHP — непустая строка, то
 * есть «истина»: `(bool) 'f'` даёт TRUE. Ошибка незаметна на записи, которую
 * только что сохранили (там настоящий bool), и вылезает на живых данных:
 * «сценарий отключён», «тип неактивен», «запись удалена» — и всё это наоборот.
 *
 * Поэтому у модуля один способ читать boolean, и им пользуются все сервисы.
 *
 * @package    Erp199
 * @category   Services
 */
class Service_Erp199_Cast
{
    /**
     * Boolean из базы: bool, 't'/'f', 1/0, 'on'/'yes' → TRUE/FALSE.
     *
     * @param   mixed  $value
     * @return  boolean
     */
    public static function boolean($value)
    {
        if (is_bool($value))
        {
            return $value;
        }

        if ($value === NULL)
        {
            return FALSE;
        }

        return in_array(strtolower(trim((string) $value)), array('t', 'true', '1', 'y', 'yes', 'on'), TRUE);
    }

    /**
     * JSON-поле (jsonb) → массив; мусор и NULL дают значение по умолчанию.
     *
     * @param   mixed  $value
     * @param   array  $default
     * @return  array
     */
    public static function json($value, array $default = array())
    {
        if (is_array($value))
        {
            return $value;
        }

        $decoded = json_decode((string) $value, TRUE);

        return is_array($decoded) ? $decoded : $default;
    }
}
