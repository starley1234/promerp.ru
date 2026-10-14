<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Подписи и цвета для интерфейса журнала (erp119_audit).
 *
 * Вынесено из представлений, чтобы одна и та же запись выглядела одинаково
 * в ленте, в карточке записи, в истории объекта и в виджетах.
 *
 * @package    Erp119
 * @category   Helpers
 */
class Erp119_Ui
{
    /**
     * Действие → подпись.
     *
     * @param   string  $action
     * @return  string
     */
    public static function action_label($action)
    {
        $labels = array(
            'create'      => 'Создание',
            'update'      => 'Изменение',
            'delete'      => 'Удаление',
            'restore'     => 'Восстановление',
            'status'      => 'Статус',
            'read'        => 'Просмотр',
            'export'      => 'Выгрузка',
            'login'       => 'Вход',
            'logout'      => 'Выход',
            'send'        => 'Отправка',
            'fail'        => 'Сбой',
            'approve'     => 'Утверждение',
            'execute'     => 'Запуск',
            'retry'       => 'Повтор',
            'subscribe'   => 'Подписка',
            'unsubscribe' => 'Отписка',
            'complete'    => 'Завершение',
            'scan'        => 'Сканирование',
            'maintenance' => 'Обслуживание',
            'event'       => 'Событие',
        );

        return isset($labels[$action]) ? $labels[$action] : $action;
    }

    /**
     * Действие → цвет бейджа (Tailwind).
     *
     * @param   string  $action
     * @return  string
     */
    public static function action_tone($action)
    {
        $tones = array(
            'create'  => 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-200',
            'update'  => 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-200',
            'delete'  => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
            'restore' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
            'status'  => 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-200',
            'read'    => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
            'export'  => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200',
            'fail'    => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
            'send'    => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/40 dark:text-cyan-200',
        );

        return isset($tones[$action])
            ? $tones[$action]
            : 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-200';
    }

    /**
     * Важность → подпись.
     *
     * @param   string  $severity
     * @return  string
     */
    public static function severity_label($severity)
    {
        $labels = array(
            'info'     => 'Обычное',
            'notice'   => 'Заметное',
            'warning'  => 'Важное',
            'critical' => 'Критичное',
        );

        return isset($labels[$severity]) ? $labels[$severity] : $severity;
    }

    /**
     * Важность → цвет бейджа.
     *
     * @param   string  $severity
     * @return  string
     */
    public static function severity_tone($severity)
    {
        $tones = array(
            'info'     => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
            'notice'   => 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-200',
            'warning'  => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200',
            'critical' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-200',
        );

        return isset($tones[$severity]) ? $tones[$severity] : $tones['info'];
    }

    /**
     * Источник записи → подпись.
     *
     * @param   string  $source
     * @return  string
     */
    public static function source_label($source)
    {
        $labels = array(
            'web'    => 'Интерфейс',
            'api'    => 'API',
            'cli'    => 'Консоль',
            'cron'   => 'Расписание',
            'event'  => 'Событие',
            'system' => 'Система',
        );

        return isset($labels[$source]) ? $labels[$source] : $source;
    }

    /**
     * Значение для таблицы изменений.
     *
     * @param   mixed   $value
     * @param   int     $limit
     * @return  string
     */
    public static function value($value, $limit = 120)
    {
        if ($value === NULL)
        {
            return '—';
        }

        if (is_bool($value))
        {
            return $value ? 'да' : 'нет';
        }

        if (is_array($value))
        {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        $value = (string) $value;

        return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit).'…' : $value;
    }

    /**
     * Как давно (человекочитаемо) плюс точное время.
     *
     * @param   string  $timestamp
     * @return  string
     */
    public static function ago($timestamp)
    {
        if ( ! $timestamp)
        {
            return '—';
        }

        $diff = time() - strtotime($timestamp);

        if ($diff < 0)
        {
            return date('d.m.Y H:i', strtotime($timestamp));
        }

        if ($diff < 60)
        {
            return 'только что';
        }

        $map = array(
            3600        => array(60, 'мин'),
            86400       => array(3600, 'ч'),
            2592000     => array(86400, 'д'),
            31536000    => array(2592000, 'мес'),
            PHP_INT_MAX => array(31536000, 'г'),
        );

        foreach ($map as $limit => $pair)
        {
            if ($diff < $limit)
            {
                return floor($diff / $pair[0]).' '.$pair[1].' назад';
            }
        }

        return date('d.m.Y H:i', strtotime($timestamp));
    }
}
