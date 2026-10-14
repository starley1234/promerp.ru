<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Обмен с таблицами (erp221_crm): выгрузка и загрузка сделок в CSV.
 *
 * Формат намеренно простой: одна строка — одна сделка, знакомые менеджеру
 * заголовки, разделитель — точка с запятой (так Excel в русской локали не
 * склеивает колонки). Импорт умеет работать «на черновик»: сначала отчёт о
 * том, что будет создано и изменено, и только потом запись.
 *
 * @package    Erp221
 * @category   Services
 */
class Service_Erp221_Exchange
{
    /** @var Service_Erp221_Exchange|null */
    protected static $_instance;

    /**
     * @return  Service_Erp221_Exchange
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self();
        }

        return self::$_instance;
    }

    /**
     * Заголовки выгрузки: ключ колонки => подпись для человека.
     *
     * @return  array
     */
    public function columns()
    {
        return array(
            'number'       => 'Номер',
            'title'        => 'Название',
            'client'       => 'Клиент',
            'inn'          => 'ИНН',
            'stage'        => 'Стадия',
            'amount'       => 'Сумма',
            'currency'     => 'Валюта',
            'probability'  => 'Вероятность, %',
            'deadline'     => 'Срок',
            'priority'     => 'Приоритет',
            'source'       => 'Источник',
            'assigned_to'  => 'Ответственный (id)',
            'created_at'   => 'Создана',
            'closed_at'    => 'Закрыта',
            'description'  => 'Описание',
        );
    }

    /**
     * Выгрузка сделок в CSV.
     *
     * @param   array   $filters  те же фильтры, что у поиска сделок
     * @param   integer $limit
     * @return  string  CSV с BOM — Excel откроет в UTF-8 без «кракозябр»
     */
    public function export_csv(array $filters = array(), $limit = 5000)
    {
        $columns = $this->columns();

        $page = Service_Erp221_Deals::instance()->search($filters, $limit, 0);

        $stream = fopen('php://temp', 'r+');

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, array_values($columns), ';');

        foreach ($page['rows'] as $deal)
        {
            fputcsv($stream, array(
                $deal['number'],
                $deal['title'],
                $deal['client']['name'],
                $deal['client']['inn'],
                $deal['stage']['label'],
                number_format($deal['amount'], 2, '.', ''),
                $deal['currency'],
                $deal['probability'],
                $deal['deadline'],
                $deal['priority_level'],
                $deal['source'],
                $deal['assigned_to'],
                $deal['created_at'],
                $deal['closed_at'],
                $deal['description'],
            ), ';');
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }

    /**
     * Шаблон для заполнения: заголовки и одна строка-пример.
     *
     * @return  string
     */
    public function template_csv()
    {
        $stream = fopen('php://temp', 'r+');

        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, array('Клиент (ИНН)', 'Название', 'Стадия', 'Сумма', 'Срок', 'Приоритет', 'Источник', 'Описание'), ';');
        fputcsv($stream, array('7701234567', 'Пример сделки', 'Новая заявка', '150000.00', date('Y-m-d', strtotime('+14 days')), 'medium', 'сайт', 'Строку-пример можно удалить'), ';');

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }

    /**
     * Импорт сделок из CSV.
     *
     * Колонки: «Клиент (ИНН)» или «Номер» (для обновления существующей сделки),
     * «Название», «Стадия», «Сумма», «Срок», «Приоритет», «Источник», «Описание».
     *
     * @param   string  $csv       содержимое файла
     * @param   integer $actor
     * @param   boolean $dry_run   только проверить и показать отчёт
     * @return  array   ['rows' => int, 'created' => int, 'updated' => int, 'errors' => array]
     */
    public function import_csv($csv, $actor = NULL, $dry_run = FALSE)
    {
        $csv = ltrim((string) $csv, "\xEF\xBB\xBF");
        $csv = str_replace(array("\r\n", "\r"), "\n", $csv);

        // Excel в русской локали сохраняет CSV через «;», но файл может прийти и
        // с запятыми: считаем фактический разделитель по первой строке.
        $delimiter = substr_count(strtok($csv, "\n"), ';') >= substr_count(strtok($csv, "\n"), ',') ? ';' : ',';

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $csv);
        rewind($stream);

        $header = fgetcsv($stream, 0, $delimiter);

        if ( ! $header)
        {
            fclose($stream);

            return array('rows' => 0, 'created' => 0, 'updated' => 0, 'errors' => array('Файл пуст — заголовок не найден'));
        }

        $map = array();

        foreach ($header as $index => $label)
        {
            $label = mb_strtolower(trim((string) $label));

            if (mb_strpos($label, 'инн') !== FALSE)
            {
                $map['inn'] = $index;
            }
            elseif (mb_strpos($label, 'номер') !== FALSE)
            {
                $map['number'] = $index;
            }
            elseif (mb_strpos($label, 'назван') !== FALSE)
            {
                $map['title'] = $index;
            }
            elseif (mb_strpos($label, 'стади') !== FALSE)
            {
                $map['stage'] = $index;
            }
            elseif (mb_strpos($label, 'сумм') !== FALSE)
            {
                $map['amount'] = $index;
            }
            elseif (mb_strpos($label, 'срок') !== FALSE)
            {
                $map['deadline'] = $index;
            }
            elseif (mb_strpos($label, 'приоритет') !== FALSE)
            {
                $map['priority'] = $index;
            }
            elseif (mb_strpos($label, 'источник') !== FALSE)
            {
                $map['source'] = $index;
            }
            elseif (mb_strpos($label, 'описан') !== FALSE)
            {
                $map['description'] = $index;
            }
        }

        $report = array('rows' => 0, 'created' => 0, 'updated' => 0, 'errors' => array());

        while (($row = fgetcsv($stream, 0, $delimiter)) !== FALSE)
        {
            if (count(array_filter($row, function ($value) { return trim((string) $value) !== ''; })) === 0)
            {
                continue; // пустая строка в конце файла
            }

            $report['rows']++;

            $get = function ($key) use ($row, $map) {
                return isset($map[$key], $row[$map[$key]]) ? trim((string) $row[$map[$key]]) : '';
            };

            try
            {
                $deal_data = array(
                    'title'       => $get('title'),
                    'stage'       => $get('stage'),
                    'amount'      => $get('amount'),
                    'deadline'    => $get('deadline'),
                    'priority_level' => $get('priority') ?: 'medium',
                    'source'      => $get('source'),
                    'description' => $get('description'),
                );

                $existing = $get('number') !== '' ? Service_Erp221_Deals::instance()->find_by_number($get('number')) : NULL;

                if ( ! $existing)
                {
                    if ($deal_data['title'] === '')
                    {
                        throw new Kohana_Exception('Пустое название сделки');
                    }

                    $client = $this->resolve_client($get('inn'), $get('title'));

                    $deal_data['counterparty_id'] = $client['id'];

                    if ( ! $dry_run)
                    {
                        Service_Erp221_Deals::instance()->create($deal_data, $actor);
                    }

                    $report['created']++;
                }
                else
                {
                    if ( ! $dry_run)
                    {
                        Service_Erp221_Deals::instance()->update($existing['id'], $deal_data, $actor);

                        if ( ! empty($deal_data['stage']))
                        {
                            $stage = Service_Erp221_Stages::instance()->find($deal_data['stage']);

                            if ($stage)
                            {
                                Service_Erp221_Deals::instance()->change_stage($existing['id'], $stage['id'], array(), $actor);
                            }
                        }
                    }

                    $report['updated']++;
                }
            }
            catch (Exception $e)
            {
                $report['errors'][] = 'Строка '.$report['rows'].': '.$e->getMessage();
            }
        }

        fclose($stream);

        return $report;
    }

    /**
     * Найти клиента по ИНН, а если такого нет — предложить создать (только при
     * явном согласии оператора; импорт сам записи в чужой справочник не вносит).
     *
     * @param   string  $inn
     * @param   string  $title
     * @return  array
     * @throws  Kohana_Exception
     */
    protected function resolve_client($inn, $title)
    {
        if ($inn !== '')
        {
            $counterparty = Service_Erp301_Counterparties::instance()->find_by_inn($inn);

            if ($counterparty)
            {
                return array('id' => (int) $counterparty->id, 'name' => (string) $counterparty->name);
            }
        }

        throw new Kohana_Exception(
            'Клиент с ИНН «:inn» не найден — сначала заведите контрагента в разделе «Контрагенты»',
            array(':inn' => $inn !== '' ? $inn : 'не указан')
        );
    }
}
