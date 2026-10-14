<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Приведение значений PostgreSQL к типам PHP (erp221_crm).
 *
 * Драйвер отдаёт `boolean`, `jsonb` и числа строками: `(bool) 'f'` в PHP —
 * это `TRUE`, поэтому «признак выключен» из базы читался бы как «включён».
 * Один такой каст уже приводил к дефекту в QR-маркировке (erp199), поэтому в
 * CRM все чтения из базы идут через эти методы, а не через сырые приведения.
 *
 * @package    Erp221
 * @category   Services
 */
class Service_Erp221_Cast
{
    /**
     * Признак из PostgreSQL: t/true/1/y/yes/on — истина, всё остальное — ложь.
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
     * Id вставленной строки из результата `DB::insert(...)->execute()`.
     *
     * Драйвер PostgreSQL возвращает `array($id, $rows)` (запрос дописывает
     * `RETURNING <pk>`), поэтому `(int)` от результата — это всегда 1. Ошибка
     * тихая: следующий запрос «по id» вернёт чужую или несуществующую запись.
     *
     * @param   mixed  $result
     * @return  integer
     */
    public static function insert_id($result)
    {
        if (is_array($result))
        {
            $id = reset($result);

            return $id === FALSE ? 0 : (int) $id;
        }

        if ($result instanceof Database_Result)
        {
            $row = $result->current();

            return $row ? (int) reset($row) : 0;
        }

        return (int) $result;
    }

    /**
     * JSON-поле (jsonb) как массив.
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

        if ($value === NULL OR $value === '')
        {
            return $default;
        }

        $decoded = json_decode((string) $value, TRUE);

        return is_array($decoded) ? $decoded : $default;
    }

    /**
     * Число с плавающей точкой: пустая строка и NULL — это NULL, а не ноль.
     *
     * @param   mixed  $value
     * @return  float|null
     */
    public static function num($value)
    {
        if ($value === NULL OR $value === '')
        {
            return NULL;
        }

        return (float) $value;
    }

    /**
     * Текст: NULL остаётся NULL, строка обрезается по краям.
     *
     * @param   mixed  $value
     * @return  string|null
     */
    public static function text($value)
    {
        if ($value === NULL)
        {
            return NULL;
        }

        $value = trim((string) $value);

        return $value === '' ? NULL : $value;
    }

    /**
     * Дата-время в формате, который понимает представление: без секунд.
     *
     * @param   mixed  $value
     * @return  string|null
     */
    public static function stamp($value)
    {
        if (empty($value))
        {
            return NULL;
        }

        $time = strtotime((string) $value);

        return $time ? date('Y-m-d H:i', $time) : NULL;
    }
}
