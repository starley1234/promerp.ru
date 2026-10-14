<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Приведение типов из PostgreSQL (erp804_mcp).
 *
 * Драйвер отдаёт BOOLEAN строками `t`/`f`, а PHP считает непустую строку
 * истиной: `! empty('f')` → FALSE. Из-за этого «отозванный» ключ продолжал
 * работать, а любой ключ выглядел «только для чтения». Поэтому булевы поля
 * строк из БД проходят через `Cast::boolean()`.
 *
 * @package    Erp804
 * @category   Services
 */
class Service_Erp804_Cast
{
    /**
     * Привести значение из БД к bool.
     *
     * @param   mixed   $value
     * @return  bool
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
}
