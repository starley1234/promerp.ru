<?php

/**
 * Общие преобразования для всех модулей проекта.
 *
 * Место для маленьких функций, которые нужны каждому модулю и не заслуживают
 * своего класса. Здесь только преобразования значений — никакой бизнес-логики:
 * она живёт в сервисах модулей.
 *
 * Первый жилец — `Erp::bool()`, потому что PostgreSQL отдаёт `BOOLEAN`
 * строками `t`/`f`, а непустая строка в PHP истинна: `! empty('f')` даёт
 * FALSE, то есть «выключено» читается как «включено». На этих граблях уже
 * стояли ключи MCP (`erp804_mcp`) и склад (`erp401_wms`) — теперь приведение
 * централизовано.
 *
 * Второй жилец — `Erp::id_or_uuid()`: сервисы модулей умеют искать документ и
 * по числовому id, и по uuid, а «умный» поиск без проверки формата доводит
 * мусор из адреса до СУБД, и она отвечает ошибкой приведения к uuid вместо
 * пустого результата. Проверка формата теперь в одном месте.
 *
 * @package    Erp
 * @category   Helpers
 */
class Erp
{
    /**
     * Привести значение из БД или формы к bool.
     *
     * Понимает `t`/`f`, `true`/`false`, `1`/`0`, `on`/`off`, `yes`/`no`
     * и, конечно, настоящие bool.
     *
     * @param   mixed   $value
     * @return  bool
     */
    public static function bool($value)
    {
        if (is_bool($value))
        {
            return $value;
        }

        if ($value === NULL)
        {
            return FALSE;
        }

        return in_array(strtolower(trim((string) $value)), array('t', 'true', '1', 'y', 'yes', 'on', 'да'), TRUE);
    }

    /**
     * Число из строки БД или формы (запятая как разделитель тоже принимается:
     * мастер в терминале может ввести «12,5»).
     *
     * @param   mixed       $value
     * @param   float       $default
     * @return  float
     */
    public static function num($value, $default = 0.0)
    {
        if (is_int($value) OR is_float($value))
        {
            return (float) $value;
        }

        $value = trim((string) $value);

        if ($value === '')
        {
            return (float) $default;
        }

        $value = str_replace(array(' ', "\xc2\xa0", ','), array('', '', '.'), $value);

        return is_numeric($value) ? (float) $value : (float) $default;
    }

    /**
     * Дата в виде ГГГГ-ММ-ДД: принимает и полную отметку времени, и пустое
     * значение (тогда — `$default`, по умолчанию сегодня).
     *
     * @param   mixed       $value
     * @param   string|null $default
     * @return  string|null
     */
    public static function day($value, $default = NULL)
    {
        $value = trim((string) $value);

        if ($value === '')
        {
            return $default === NULL ? date('Y-m-d') : $default;
        }

        $stamp = strtotime($value);

        return $stamp === FALSE ? ($default === NULL ? date('Y-m-d') : $default) : date('Y-m-d', $stamp);
    }

    /**
     * Часы из секунд: 5400 → 1.5.
     *
     * @param   int|float   $seconds
     * @param   int         $precision
     * @return  float
     */
    public static function hours($seconds, $precision = 2)
    {
        return round((float) $seconds / 3600, $precision);
    }

    /**
     * Формат uuid.
     *
     * PostgreSQL принимает uuid только в каноническом виде: восемь-четыре-
     * четыре-четыре-двенадцать шестнадцатеричных цифр. Всё остальное — не
     * «почти uuid», а повод не идти в базу.
     *
     * @param   mixed   $value
     * @return  bool
     */
    public static function is_uuid($value)
    {
        return is_string($value)
            AND preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) === 1;
    }

    /**
     * Новый uuid версии 4.
     *
     * Идентификаторы выдают и база (`gen_random_uuid()`), и этот помощник.
     * Разница видна сразу после вставки: значение из базы модель не знает до
     * перечитывания строки, а журнал действий, события и ответ API
     * спрашивают uuid **тут же**. Модели проектных модулей выдают uuid этим
     * помощником до сохранения (см. `save()` в моделях), серверный default
     * остаётся подстраховкой для вставок в обход ORM.
     *
     * @return  string
     */
    public static function uuid()
    {
        $bytes = random_bytes(16);

        // Версия 4 и вариант RFC 4122 — иначе это не uuid, а 16 случайных байт.
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * Разобрать идентификатор из адреса или формы: «2» → `array('id', 2)`,
     * uuid → `array('uuid', '...')`, мусор → NULL.
     *
     * Общий случай поиска «по id или uuid»: сервис берёт готовую пару
     * «колонка — значение» и не думает о приведении типов. Мусор в адресе
     * (`/orders/view/abc`) даёт NULL, то есть обычный «не найдено», а не
     * ошибку СУБД «invalid input syntax for type uuid». Ноль тоже не
     * идентификатор: в serial-колонке такого значения не бывает.
     *
     * @param   mixed   $value
     * @return  array|null  array(колонка, значение)
     */
    public static function id_or_uuid($value)
    {
        if (is_int($value) AND $value > 0)
        {
            return array('id', $value);
        }

        if (is_string($value) AND ctype_digit($value) AND (int) $value > 0)
        {
            return array('id', (int) $value);
        }

        if (self::is_uuid($value))
        {
            return array('uuid', strtolower($value));
        }

        return NULL;
    }

    /**
     * Секунды из часов: 1.5 → 5400.
     *
     * @param   int|float   $hours
     * @return  int
     */
    public static function seconds($hours)
    {
        return (int) round((float) $hours * 3600);
    }
}
