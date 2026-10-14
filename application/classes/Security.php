<?php

defined('SYSPATH') or die('No direct script access.');

class Security extends Kohana_Security
{
    /**
     * Очистка данных от XSS атак.
     * Используется как фильтр в ORM моделях.
     *
     * @param   mixed   $str    строка или массив строк
     * @return  mixed
     */
    public static function xss_clean($str)
    {
        if (is_array($str)) {
            foreach ($str as $key => $val) {
                $str[$key] = self::xss_clean($val);
            }

            return $str;
        }

        // Удаляем невидимые символы
        $str = self::remove_invisible_characters($str);

        // Экранируем HTML сущности
        return HTML::chars($str, TRUE);
    }

    /**
     * Удаление невидимых символов (предотвращение атак с использованием управляющих символов)
     *
     * @param   string  $str
     * @param   boolean $url_encoded
     * @return  string
     */
    public static function remove_invisible_characters($str, $url_encoded = TRUE)
    {
        $non_displayables = array();

        if ($url_encoded) {
            $non_displayables[] = '/%0[0-8bcef]/'; // url encoded 00-08, 11, 12, 14, 15
            $non_displayables[] = '/%1[0-9a-f]/';  // url encoded 16-31
        }

        $non_displayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S'; // 00-08, 11, 12, 14-31, 127

        do {
            $str = preg_replace($non_displayables, '', $str, -1, $count);
        } while ($count);

        return $str;
    }
}