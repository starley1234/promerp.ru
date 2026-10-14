<?php

/**
 * Расширение задачи Minion на уровне приложения.
 *
 * В каскадной файловой системе Kohana класс, найденный в application/classes,
 * подменяет модульный. Реализация ядра лежит в classes/Kohana/Minion/Task.php
 * (modules/minion), а этот файл — точка расширения для проекта.
 *
 * ВАЖНО: раньше здесь лежала копия ядровой реализации (abstract class Kohana_Minion_Task),
 * из-за чего класс Minion_Task не находился и CLI-задачи (`make console`, `make migrate`)
 * не работали вовсе: «Please enable the Minion module for CLI support».
 */
abstract class Minion_Task extends Kohana_Minion_Task {

    /**
     * Значение флага командной строки.
     *
     * Minion передаёт флаг без значения (`--status`) как ключ со значением NULL,
     * поэтому Arr::get($options, 'status', FALSE) возвращает NULL, а не FALSE.
     *
     * @param   array   $options
     * @param   string  $name
     * @param   mixed   $default  значение, если флаг не передан
     * @return  mixed
     */
    protected function _flag(array $options, $name, $default = FALSE)
    {
        if ( ! array_key_exists($name, $options))
        {
            return $default;
        }

        $value = $options[$name];

        if ($value === NULL OR $value === TRUE)
        {
            return TRUE;
        }

        return ! in_array(strtolower((string) $value), ['', '0', 'false', 'no', 'off'], TRUE);
    }

    /**
     * Значение параметра (NULL, если не передан).
     *
     * @param   array   $options
     * @param   string  $name
     * @param   mixed   $default
     * @return  mixed
     */
    protected function _value(array $options, $name, $default = NULL)
    {
        if ( ! array_key_exists($name, $options) OR $options[$name] === NULL)
        {
            return $default;
        }

        return $options[$name];
    }

    /**
     * Корень проекта (каталог с application, modules, system, vendor).
     *
     * @return  string
     */
    protected function _root()
    {
        return dirname(APPPATH);
    }
}
