<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Выгрузка журнала в CSV (erp119_audit).
 *
 * Выгрузка журнала — само по себе событие аудита: кто и какой срез данных
 * вынес из системы. Поэтому каждый экспорт записывается в журнал
 * (действие `export`, важность notice).
 *
 * @package    Erp119
 * @category   Services
 */
class Service_Erp119_Export
{
    /** @var int Предохранитель: 100 000 записей в одной выгрузке */
    const LIMIT = 100000;

    /** @var Service_Erp119_Export */
    protected static $_instance;

    /**
     * @return  Service_Erp119_Export
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    protected function __construct()
    {
    }

    /**
     * Собрать CSV по тем же фильтрам, что и лента.
     *
     * @param   array   $filters
     * @param   bool    $audit    писать ли событие выгрузки в журнал
     * @return  string  содержимое CSV
     */
    public function csv(array $filters = array(), $audit = TRUE)
    {
        $columns = array(
            'id'           => 'ID',
            'occurred_at'  => 'Время',
            'module'       => 'Модуль',
            'entity_type'  => 'Сущность',
            'entity_id'    => 'ID сущности',
            'entity_label' => 'Объект',
            'action'       => 'Действие',
            'severity'     => 'Важность',
            'actor_name'   => 'Пользователь',
            'actor_ip'     => 'IP',
            'source'       => 'Источник',
            'message'      => 'Сообщение',
            'changes'      => 'Изменения',
        );

        $handle = fopen('php://temp', 'r+');

        // BOM: без него Excel открывает UTF-8 как «кракозябры».
        // $escape задаём явно — в PHP 8.4 значение по умолчанию устарело.
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, array_values($columns), ';', '"', "\\");

        $written = 0;
        $offset  = 0;
        $batch   = 500;

        do
        {
            $page = Service_Erp119_Query::instance()->search($filters, $batch, $offset);
            $rows = $page['rows'];

            foreach ($rows as $row)
            {
                $line = array();

                foreach (array_keys($columns) as $key)
                {
                    $value = isset($row[$key]) ? $row[$key] : '';

                    if ($key === 'changes')
                    {
                        $value = $this->changes_to_text(isset($row['changes']) ? $row['changes'] : array());
                    }

                    // Excel и «;» в значении: переводы строк ломают строку CSV
                    if (is_string($value))
                    {
                        $value = str_replace(array("\r\n", "\n", "\r"), ' ', $value);
                    }

                    $line[] = $value;
                }

                fputcsv($handle, $line, ';', '"', "\\");
                $written++;
            }

            $offset += $batch;
        }
        while (count($rows) === $batch AND $written < self::LIMIT);

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        if ($audit)
        {
            Service_Erp119_Journal::instance()->record(array(
                'module'      => 'erp119',
                'entity_type' => 'audit_log',
                'entity_id'   => 'export',
                'action'      => 'export',
                'severity'    => 'notice',
                'entity_label' => 'Журнал действий',
                'message'     => 'Выгрузка журнала в CSV: '.$written.' записей',
                'context'     => array('filters' => $this->clean_filters($filters), 'rows' => $written),
            ));
        }

        return $content;
    }

    /**
     * Имя файла выгрузки.
     *
     * @return  string
     */
    public function filename()
    {
        return 'audit-'.date('Y-m-d-Hi').'.csv';
    }

    /**
     * Изменения — одной читаемой строкой: «status: draft → ready».
     *
     * @param   array   $changes
     * @return  string
     */
    protected function changes_to_text(array $changes)
    {
        $parts = array();

        foreach ($changes as $change)
        {
            if ( ! isset($change['field']))
            {
                continue;
            }

            $parts[] = $change['field'].': '.$this->value($change['old'] ?? NULL).' → '.$this->value($change['new'] ?? NULL);
        }

        return implode('; ', $parts);
    }

    /**
     * Значение для текстового представления.
     *
     * @param   mixed   $value
     * @return  string
     */
    protected function value($value)
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
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        return (string) $value;
    }

    /**
     * Фильтры без сущностей и объектов — только скаляры (для контекста записи).
     *
     * @param   array   $filters
     * @return  array
     */
    protected function clean_filters(array $filters)
    {
        $clean = array();

        foreach ($filters as $key => $value)
        {
            if ($value === NULL OR $value === '' OR is_array($value))
            {
                continue;
            }

            $clean[$key] = $value;
        }

        return $clean;
    }
}
