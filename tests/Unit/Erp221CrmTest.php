<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Тесты модуля продаж (erp221_crm).
 *
 * Проверяем обещания, которые модуль даёт людям: сделка ведётся от обращения
 * до решения, стадия не пропускает недозаполненную сделку, обещания менеджера
 * (звонок, письмо, задача) видны в ленте, клиент остаётся клиентом системы, а
 * не «клиентом CRM», а обмен и аналитика считают по одним и тем же правилам.
 */
class Erp221CrmTest extends TestCase
{
    /** @var array Контрагенты, созданные тестом */
    protected $clients = array();

    /** @var array Контактные лица, созданные тестом */
    protected $contacts = array();

    /** @var array Сделки, созданные тестом */
    protected $deals = array();

    /** @var array Статьи базы знаний, созданные тестом */
    protected $articles = array();

    /** @var array Файлы хранилища, созданные тестом */
    protected $files = array();

    protected function setUp(): void
    {
        try
        {
            \DB::select('id')->from('erp_221_deals')->limit(1)->execute();
        }
        catch (\Throwable $e)
        {
            $this->markTestSkipped('Выполните миграции erp221_crm');
        }
    }

    protected function tearDown(): void
    {
        try
        {
            foreach ($this->deals as $id)
            {
                \DB::delete('erp_221_activities_history')->where('activity_id', 'IN',
                    \DB::select('id')->from('erp_221_activities')->where('deal_id', '=', $id))->execute();
                \DB::delete('erp_221_ai_analysis')->where('entity_type', '=', 'deal')->where('entity_id', '=', $id)->execute();
                \DB::delete('erp_221_deal_contacts')->where('deal_id', '=', $id)->execute();
                \DB::delete('erp_221_activities')->where('deal_id', '=', $id)->execute();
                \DB::delete('erp_221_deals')->where('id', '=', $id)->execute();
            }

            if ($this->articles)
            {
                \DB::delete('erp_221_knowledge_base')->where('id', 'IN', $this->articles)->execute();
            }

            if ($this->clients)
            {
                \DB::delete('erp_221_activities')->where('counterparty_id', 'IN', $this->clients)->execute();
                \DB::delete('erp_221_client_profiles')->where('counterparty_id', 'IN', $this->clients)->execute();
                \DB::delete('erp_221_contact_profiles')->where('contact_id', 'IN',
                    \DB::select('id')->from('erp_301_contacts')->where('counterparty_id', 'IN', $this->clients))->execute();
                \DB::delete('erp_301_contacts')->where('counterparty_id', 'IN', $this->clients)->execute();
                \DB::delete('erp_301_counterparties')->where('id', 'IN', $this->clients)->execute();
            }

            if ($this->files)
            {
                \DB::delete('erp_212_files')->where('id', 'IN', $this->files)->execute();
            }
        }
        catch (\Throwable $e)
        {
            // Уборка не должна маскировать результат теста.
        }
    }

    /**
     * Завести клиента (контрагента с ролью покупателя) и запомнить для уборки.
     *
     * @param   string  $suffix
     * @return  integer
     */
    protected function client($suffix = '')
    {
        $counterparty = \Service_Erp301_Counterparties::instance()->create(array(
            'name'  => 'CRM-тест '.$suffix.' '.uniqid(),
            'roles' => array('customer'),
            'kind'  => 'legal',
            // ИНН нужен импорту: клиент ищется по нему, а не по названию.
            'inn'   => (string) mt_rand(1000000000, 9999999999),
        ));

        $this->clients[] = (int) $counterparty->id;

        return (int) $counterparty->id;
    }

    /**
     * Контактное лицо клиента.
     *
     * @param   integer $client_id
     * @param   string  $name
     * @return  integer
     */
    protected function contact($client_id, $name = 'Иван Петров')
    {
        $contact = \Service_Erp301_Counterparties::instance()->save_contact($client_id, array(
            'name'       => $name,
            'position'   => 'Директор',
            'phone'      => '+7 900 000-00-00',
            'is_primary' => TRUE,
        ));

        $this->contacts[] = (int) $contact->id;

        return (int) $contact->id;
    }

    /**
     * Сделка и её запись в списке уборки.
     *
     * @param   integer $client_id
     * @param   array   $data
     * @return  array
     */
    protected function deal($client_id, array $data = array())
    {
        $deal = \Service_Erp221_Deals::instance()->create($data + array(
            'title'           => 'Тестовая сделка',
            'counterparty_id' => $client_id,
            'amount'          => 100000,
        ));

        $this->deals[] = (int) $deal['id'];

        return $deal;
    }

    /**
     * Номер сделки читается человеком и не повторяется.
     */
    public function test_deal_number_is_readable_and_unique()
    {
        $client = $this->client('номер');

        $first  = $this->deal($client, array('title' => 'Первая'));
        $second = $this->deal($client, array('title' => 'Вторая'));

        $this->assertTrue((bool) preg_match('/^DEAL-\d{4}-\d{3,}$/', $first['number']), 'Номер вида DEAL-ГГММ-NNN');
        $this->assertTrue($first['number'] !== $second['number'], 'Номера сделок не повторяются');
    }

    /**
     * Сделку нельзя завести без названия и без существующего клиента.
     */
    public function test_deal_requires_title_and_real_client()
    {
        $client = $this->client('обязательный');

        $failed = FALSE;

        try
        {
            \Service_Erp221_Deals::instance()->create(array('title' => '  ', 'counterparty_id' => $client));
        }
        catch (\Exception $e)
        {
            $failed = TRUE;
        }

        $this->assertTrue($failed, 'Пустое название отклонено');

        $failed = FALSE;

        try
        {
            \Service_Erp221_Deals::instance()->create(array('title' => 'Сделка', 'counterparty_id' => 99999999));
        }
        catch (\Exception $e)
        {
            $failed = TRUE;
        }

        $this->assertTrue($failed, 'Несуществующий клиент отклонён');
    }

    /**
     * Стадия, требующая заполнить поле, не пропускает сделку с пустой суммой.
     */
    public function test_stage_transition_enforces_required_fields()
    {
        $client = $this->client('стадии');
        $deal   = $this->deal($client, array('title' => 'Сделка со стадией', 'amount' => 0));

        $failed = FALSE;

        try
        {
            \Service_Erp221_Deals::instance()->change_stage($deal['id'], 'proposal');
        }
        catch (\Exception $e)
        {
            $failed = TRUE;
        }

        $this->assertTrue($failed, 'Переход без суммы отклонён');

        // Та же стадия, но сумма заполняется прямо при переходе — это штатный путь.
        $moved = \Service_Erp221_Deals::instance()->change_stage($deal['id'], 'proposal', array('amount' => 250000));

        $this->assertSame('proposal', $moved['stage']['code']);
        $this->assertSame(250000.0, (float) $moved['amount']);
        $this->assertSame(50, (int) $moved['probability'], 'Вероятность взята у стадии');
    }

    /**
     * Отказ без причины не принимается: «проиграли» должно быть объяснением.
     */
    public function test_lost_stage_requires_loss_reason()
    {
        $client = $this->client('отказ');
        $deal   = $this->deal($client, array('title' => 'Сделка с отказом'));

        $failed = FALSE;

        try
        {
            \Service_Erp221_Deals::instance()->change_stage($deal['id'], 'lost');
        }
        catch (\Exception $e)
        {
            $failed = TRUE;
        }

        $this->assertTrue($failed, 'Отказ без причины отклонён');

        $reasons = \Service_Erp221_Stages::instance()->loss_reasons();
        $this->assertTrue(count($reasons) > 0, 'Причины отказа заведены миграцией');

        $lost = \Service_Erp221_Deals::instance()->change_stage($deal['id'], 'lost', array(
            'loss_reason_id' => $reasons[0]['id'],
            'comment'        => 'Выбрали другого поставщика',
        ));

        $this->assertSame('lost', $lost['stage']['code']);
        $this->assertSame((int) $reasons[0]['id'], (int) $lost['loss_reason_id']);
        $this->assertTrue((bool) $lost['closed_at'], 'У закрытой сделки есть дата решения');
    }

    /**
     * Смена стадии оставляет след в ленте — историю сделки нельзя «переписать».
     */
    public function test_stage_change_is_recorded_in_timeline()
    {
        $client = $this->client('лента стадий');
        $deal   = $this->deal($client, array('title' => 'Сделка с историей'));

        \Service_Erp221_Deals::instance()->change_stage($deal['id'], 'qualify', array('comment' => 'Позвонили, интерес есть'));

        $timeline = \Service_Erp221_Timeline::instance()->timeline($deal['id']);

        $this->assertTrue($timeline['total'] > 0, 'В ленте есть записи');

        $found = FALSE;

        foreach ($timeline['rows'] as $row)
        {
            if ($row['type'] === 'stage' AND mb_strpos((string) $row['content'], 'Квалификация') !== FALSE)
            {
                $found = TRUE;
            }
        }

        $this->assertTrue($found, 'Переход стадии виден в ленте');
    }

    /**
     * Поиск находит сделку по клиенту, стадии и просрочке.
     */
    public function test_deal_search_filters_by_client_stage_and_overdue()
    {
        $client = $this->client('поиск');
        $deal   = $this->deal($client, array(
            'title'    => 'Поиск по фильтрам',
            'amount'   => 50000,
            'deadline' => date('Y-m-d', strtotime('-3 days')),
        ));

        $deals = \Service_Erp221_Deals::instance();

        $found = $deals->search(array('counterparty' => $client));
        $this->assertSame(1, (int) $found['total']);
        $this->assertSame($deal['number'], $found['rows'][0]['number']);

        $by_term = $deals->search(array('q' => 'Поиск по фильтрам'));
        $this->assertSame(1, (int) $by_term['total'], 'Поиск по названию');

        $overdue = $deals->search(array('overdue' => TRUE, 'counterparty' => $client));
        $this->assertSame(1, (int) $overdue['total'], 'Просроченная сделка видна в отборе');
        $this->assertTrue($overdue['rows'][0]['is_overdue'], 'Признак просрочки посчитан');

        $open = $deals->search(array('kind' => 'open', 'counterparty' => $client));
        $this->assertSame(1, (int) $open['total']);
    }

    /**
     * Доска группирует сделки по стадиям и считает деньги колонки.
     */
    public function test_board_groups_deals_by_stage_with_amounts()
    {
        $client = $this->client('доска');
        $deal   = $this->deal($client, array('title' => 'Сделка на доске', 'amount' => 200000));

        $columns = \Service_Erp221_Deals::instance()->board(array('counterparty' => $client));

        $total = 0;
        $amount = 0.0;

        foreach ($columns as $column)
        {
            $total += (int) $column['count'];
            $amount += (float) $column['amount'];
        }

        $this->assertSame(1, $total, 'На доске ровно одна сделка');
        $this->assertSame(200000.0, $amount);
        $this->assertSame($deal['client']['id'], $client);
    }

    /**
     * Прогноз — это сумма, взвешенная по вероятности, а не обещание выручки.
     */
    public function test_forecast_weights_amount_by_probability()
    {
        $client = $this->client('прогноз');
        $deal   = $this->deal($client, array('title' => 'Сделка прогноза', 'amount' => 100000));

        \Service_Erp221_Deals::instance()->change_stage($deal['id'], 'proposal', array('amount' => 100000));

        $deal = \Service_Erp221_Deals::instance()->get($deal['id']);

        $this->assertSame(50, (int) $deal['probability']);
        $this->assertSame(50000.0, (float) $deal['forecast'], 'Прогноз = сумма × вероятность стадии');

        $stats = \Service_Erp221_Deals::instance()->stats();
        $this->assertTrue($stats['open_count'] >= 1);
        $this->assertTrue($stats['forecast'] >= $deal['forecast']);
    }

    /**
     * Лента: правка и закрытие записи сохраняют историю изменений.
     */
    public function test_timeline_keeps_history_of_activity_changes()
    {
        $client = $this->client('история');
        $deal   = $this->deal($client, array('title' => 'Сделка с задачей'));

        $activity = \Service_Erp221_Timeline::instance()->add($deal['id'], array(
            'type'         => 'task',
            'subject'      => 'Позвонить клиенту',
            'content'      => 'Уточнить бюджет',
            'status'       => 'pending',
            'scheduled_at' => date('Y-m-d H:i:s', strtotime('+2 days')),
        ));

        $this->assertSame('pending', $activity['status']);

        \Service_Erp221_Timeline::instance()->update($activity['id'], array('subject' => 'Позвонить и написать'));
        \Service_Erp221_Timeline::instance()->complete($activity['id']);

        $history = \Service_Erp221_Timeline::instance()->history($activity['id']);

        $this->assertTrue(count($history) >= 3, 'История накапливает снимки записи');

        $done = \Service_Erp221_Timeline::instance()->get($activity['id']);
        $this->assertSame('completed', $done['status']);
        $this->assertTrue((bool) $done['completed_at']);
    }

    /**
     * Незакрытая задача со сроком попадает в повестку и в отбор просроченных.
     */
    public function test_tasks_are_selected_by_user_and_overdue()
    {
        $client = $this->client('задачи');
        $deal   = $this->deal($client, array('title' => 'Сделка с напоминанием'));

        $activity = \Service_Erp221_Timeline::instance()->add($deal['id'], array(
            'type'         => 'task',
            'subject'      => 'Просроченный звонок',
            'status'       => 'pending',
            'assigned_to'  => 1,
            'scheduled_at' => date('Y-m-d H:i:s', strtotime('-1 day')),
        ));

        $tasks = \Service_Erp221_Timeline::instance()->tasks(1, array('overdue' => TRUE));

        $found = FALSE;

        foreach ($tasks as $task)
        {
            if ((int) $task['id'] === (int) $activity['id'])
            {
                $found = TRUE;
                $this->assertTrue($task['is_overdue'], 'Просрочка посчитана');
            }
        }

        $this->assertTrue($found, 'Задача видна в списке задач сотрудника');

        $agenda = \Service_Erp221_Reminders::instance()->agenda(1);
        $this->assertTrue(isset($agenda['tasks'], $agenda['overdue_deals'], $agenda['stale_deals']), 'Повестка собрана');
    }

    /**
     * Вложение хранится штатным хранилищем, а в ленте остаётся ссылка на файл.
     */
    public function test_timeline_upload_attaches_stored_file()
    {
        if ( ! class_exists('Storage'))
        {
            $this->markTestSkipped('Хранилище erp212 недоступно');
        }

        $client = $this->client('файл');
        $deal   = $this->deal($client, array('title' => 'Сделка с файлом'));

        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        $tmp = tempnam(sys_get_temp_dir(), 'crm');

        file_put_contents($tmp, $png);

        $file = array(
            'name'     => 'КП.png',
            'type'     => 'image/png',
            'tmp_name' => $tmp,
            'error'    => UPLOAD_ERR_OK,
            'size'     => strlen($png),
        );

        try
        {
            $activity = \Service_Erp221_Timeline::instance()->upload($deal['id'], $file, NULL, 'Отправили КП');
        }
        catch (\Exception $e)
        {
            @unlink($tmp);
            $this->markTestSkipped('Хранилище не принимает файлы в этом окружении: '.$e->getMessage());
        }

        @unlink($tmp);

        $this->assertSame('file', $activity['type']);
        $this->assertTrue((int) $activity['storage_file_id'] > 0, 'Файл привязан к записи');

        $this->files[] = (int) $activity['storage_file_id'];
    }

    /**
     * Профиль продаж — дополнение к контрагенту, а не второй справочник.
     */
    public function test_client_profile_stays_next_to_counterparty()
    {
        $client = $this->client('профиль');

        $before = (int) \DB::select(array(\DB::expr('COUNT(*)'), 'total'))->from('erp_301_counterparties')
            ->where('is_deleted', '=', 0)->execute()->get('total');

        $profile = \Service_Erp221_Clients::instance()->save_profile($client, array(
            'segment'        => 'enterprise',
            'industry'       => 'Металлообработка',
            'source'         => 'выставка',
            'annual_revenue' => 150000000,
        ));

        $after = (int) \DB::select(array(\DB::expr('COUNT(*)'), 'total'))->from('erp_301_counterparties')
            ->where('is_deleted', '=', 0)->execute()->get('total');

        $this->assertSame($before, $after, 'Новый клиент в справочник не добавлен');
        $this->assertSame('enterprise', $profile['segment']);
        $this->assertSame('Металлообработка', $profile['industry']);

        $client_card = \Service_Erp221_Clients::instance()->get($client);

        $this->assertSame('enterprise', $client_card['profile']['segment']);
        $this->assertTrue(in_array('customer', $client_card['roles'], TRUE), 'Клиент — контрагент с ролью покупателя');
    }

    /**
     * Уровень ЛПР хранится у контактного лица, а не в заметке текстом.
     */
    public function test_contact_profile_keeps_decision_maker_level()
    {
        $client  = $this->client('контакт');
        $contact = $this->contact($client, 'Мария Смирнова');

        $profile = \Service_Erp221_Clients::instance()->save_contact_profile($contact, array(
            'decision_maker_level' => 'lpr',
            'preferred_channel'    => 'telegram',
            'telegram_id'          => '@maria',
        ));

        $this->assertSame('lpr', $profile['decision_maker_level']);
        $this->assertSame('telegram', $profile['preferred_channel']);

        $contacts = \Service_Erp221_Clients::instance()->contacts($client);
        $found = FALSE;

        foreach ($contacts as $card)
        {
            if ((int) $card['id'] === $contact AND $card['decision_maker_level'] === 'lpr')
            {
                $found = TRUE;
            }
        }

        $this->assertTrue($found, 'Профиль виден в карточке клиента');
    }

    /**
     * Сделка привязывает несколько контактов, основной — только один.
     */
    public function test_deal_contacts_keep_one_primary()
    {
        $client  = $this->client('участники');
        $first   = $this->contact($client, 'Первый контакт');
        $second  = $this->contact($client, 'Второй контакт');
        $deal    = $this->deal($client, array('title' => 'Сделка с участниками'));

        $deals = \Service_Erp221_Deals::instance();
        $deals->add_contact($deal['id'], $first, TRUE);
        $deals->add_contact($deal['id'], $second, TRUE);

        $contacts = $deals->contacts($deal['id']);
        $primary  = 0;

        foreach ($contacts as $contact)
        {
            if ($contact['is_primary'])
            {
                $primary++;
            }
        }

        $this->assertSame(2, count($contacts), 'Оба контакта привязаны');
        $this->assertSame(1, $primary, 'Основной контакт ровно один');

        $deal = $deals->get($deal['id']);
        $this->assertSame($second, (int) $deal['contact_id'], 'Основной контакт перенесён в сделку');
    }

    /**
     * База знаний ищет по вопросу и ответу, удаление — мягкое.
     */
    public function test_knowledge_base_search_and_soft_delete()
    {
        $service = \Service_Erp221_Knowledge::instance();

        $id = $service->save(array(
            'category'         => 'цены',
            'question_pattern' => 'Сколько стоит доставка?',
            'answer_text'      => 'Доставка по городу входит в стоимость от 100 000 рублей.',
            'tags'             => 'доставка, цена',
        ));

        $this->articles[] = (int) $id;

        $found = $service->search('доставка');
        $this->assertTrue(count($found) >= 1, 'Статья найдена по тексту');
        $this->assertTrue(in_array('доставка', $found[0]['tags'], TRUE), 'Метки разобраны в массив');

        $service->delete($id);
        $this->assertSame(NULL, $service->get($id), 'Убранная статья не отдаётся');
    }

    /**
     * Выгрузка открывается в Excel: BOM, точка с запятой, заголовки колонок.
     */
    public function test_export_is_excel_friendly_csv()
    {
        $client = $this->client('экспорт');
        $deal   = $this->deal($client, array('title' => 'Сделка на выгрузку', 'amount' => 12345.67));

        $csv = \Service_Erp221_Exchange::instance()->export_csv(array('counterparty' => $client));

        $this->assertSame(substr($csv, 0, 3), "\xEF\xBB\xBF", 'Файл начинается с BOM');
        $this->assertTrue(strpos($csv, ';') !== FALSE, 'Разделитель — точка с запятой');
        $this->assertTrue(strpos($csv, $deal['number']) !== FALSE, 'Сделка попала в выгрузку');
        $this->assertTrue(strpos($csv, 'Номер') !== FALSE, 'Заголовки колонок на месте');
    }

    /**
     * Импорт сначала показывает отчёт и ничего не пишет.
     */
    public function test_import_dry_run_reports_without_writing()
    {
        $client = $this->client('импорт');
        $info   = \Service_Erp301_Counterparties::instance()->get($client);

        $csv = "Клиент (ИНН);Название;Стадия;Сумма;Срок;Приоритет\n"
            .$info->inn.";Сделка из файла;Новая заявка;777000;".date('Y-m-d', strtotime('+10 days')).";high\n";

        $before = (int) \DB::select(array(\DB::expr('COUNT(*)'), 'total'))->from('erp_221_deals')
            ->where('counterparty_id', '=', $client)->execute()->get('total');

        $report = \Service_Erp221_Exchange::instance()->import_csv($csv, NULL, TRUE);

        $after = (int) \DB::select(array(\DB::expr('COUNT(*)'), 'total'))->from('erp_221_deals')
            ->where('counterparty_id', '=', $client)->execute()->get('total');

        $this->assertSame($before, $after, 'Черновой прогон ничего не записал');
        $this->assertSame(1, (int) $report['created'], 'Отчёт обещает создать одну сделку');

        $real = \Service_Erp221_Exchange::instance()->import_csv($csv, NULL, FALSE);

        $created = \Service_Erp221_Deals::instance()->search(array('counterparty' => $client));

        $this->assertSame(1, (int) $real['created']);
        $this->assertSame($before + 1, (int) $created['total'], 'Сделка действительно создана');

        foreach ($created['rows'] as $row)
        {
            $this->deals[] = (int) $row['id'];
        }
    }

    /**
     * Импорт не заводит клиента «на лету»: неизвестный ИНН — это ошибка с подсказкой.
     */
    public function test_import_refuses_unknown_client()
    {
        $csv = "Клиент (ИНН);Название;Стадия;Сумма\n999999999999;Сделка без клиента;Новая заявка;1000\n";

        $report = \Service_Erp221_Exchange::instance()->import_csv($csv, NULL, TRUE);

        $this->assertSame(0, (int) $report['created']);
        $this->assertTrue(count($report['errors']) > 0, 'Импорт объяснил, чего не хватает');
    }

    /**
     * Разбор ИИ читается из истории и не подменяет данные сделки сам.
     */
    public function test_ai_history_is_scoped_to_entity()
    {
        $client = $this->client('ии');
        $deal   = $this->deal($client, array('title' => 'Сделка с разбором'));

        \DB::insert('erp_221_ai_analysis', array('entity_type', 'entity_id', 'insight_type', 'content', 'model'))
            ->values(array('deal', (int) $deal['id'], 'summary', 'Клиент готов к обсуждению условий.', 'test-model'))
            ->execute();

        $history = \Service_Erp221_Ai::instance()->history('deal', $deal['id']);

        $this->assertTrue(count($history) >= 1, 'Разбор найден');
        $this->assertSame('summary', $history[0]['insight_type']);

        $other = \Service_Erp221_Ai::instance()->history('deal', (int) $deal['id'] + 100000);
        $this->assertSame(0, count($other), 'Чужой сделке разбор не отдаётся');

        $this->assertTrue(is_bool(\Service_Erp221_Ai::instance()->available()), 'Признак доступности ИИ — логический');
    }

    /**
     * PG-логика читается как логика: `'f'` — это FALSE, а не «непустая строка».
     */
    public function test_pg_booleans_are_cast_not_truthy()
    {
        $this->assertFalse(\Service_Erp221_Cast::boolean('f'));
        $this->assertTrue(\Service_Erp221_Cast::boolean('t'));
        $this->assertFalse(\Service_Erp221_Cast::boolean('0'));
        $this->assertSame(array(), \Service_Erp221_Cast::json('не json'));
    }

    /**
     * Права, маршруты и MCP-инструменты модуля объявлены и видны системе.
     */
    public function test_module_rights_routes_and_mcp_tools_are_declared()
    {
        $acl = \Kohana::$config->load('acl')->as_array();

        $this->assertTrue(isset($acl['erp221.view']), 'Право просмотра объявлено');
        $this->assertTrue(isset($acl['erp221.deals.manage']), 'Право на сделки объявлено');
        $this->assertTrue(isset($acl['erp221.analytics.view']), 'Право на аналитику объявлено');

        foreach (array('erp221_api', 'erp221_deal', 'erp221_index') as $route)
        {
            $this->assertTrue(\Route::get($route) !== NULL, 'Маршрут '.$route.' зарегистрирован');
        }

        $mcp = include APPPATH.'modules/erp221_crm/mcp.php';

        $this->assertTrue(isset($mcp['tools']['crm.deals'], $mcp['tools']['crm.deal']), 'MCP-инструменты объявлены');

        foreach ($mcp['tools'] as $name => $tool)
        {
            $this->assertFalse($tool['mutating'], 'Инструмент '.$name.' только читает');
        }

        $read_only = \Kohana::$config->load('api')->get('read_only_actions');

        $this->assertTrue(in_array('deal', $read_only, TRUE), 'Карточка сделки объявлена read-only');
        $this->assertFalse(in_array('deal_save', $read_only, TRUE), 'Запись сделки не объявлена read-only');
    }

    /**
     * Старые адреса модуля не уводят в никуда: каждый ведёт на живую страницу.
     */
    public function test_old_crm_addresses_lead_to_live_pages()
    {
        $route = \Route::get('erp221_legacy');

        $this->assertTrue($route !== NULL, 'Маршрут старых адресов зарегистрирован');

        $params = $route->matches(\Request::factory('/erp221/deal/view/12'));

        $this->assertTrue(is_array($params), 'Старый адрес карточки сделки разбирается маршрутом');
        $this->assertSame('Legacy', $params['controller'], 'Старые адреса ведёт контроллер-переводчик');
        $this->assertSame('deal', $params['section'], 'Раздел старого адреса виден контроллеру');
        $this->assertSame('view', $params['step'], 'Действие старого адреса видно контроллеру');
        $this->assertSame('12', $params['id'], 'Номер карточки виден контроллеру');

        $reflection = new \ReflectionClass('Controller_Erp221_Legacy');
        $legacy     = $reflection->newInstanceWithoutConstructor();

        $map_property = $reflection->getProperty('_map');
        $map_property->setAccessible(TRUE);
        $map = $map_property->getValue($legacy);

        $this->assertTrue(isset($map['deal'], $map['client'], $map['activity']), 'Основные старые разделы переведены');

        foreach ($map as $section => $actions)
        {
            foreach ($actions as $alias => $target)
            {
                $this->assertTrue(
                    method_exists('Controller_Erp221_Index', 'action_'.$target),
                    'Старый адрес '.$section.'/'.$alias.' ведёт на существующую страницу '.$target
                );
            }
        }

        $cards_property = $reflection->getProperty('_cards');
        $cards_property->setAccessible(TRUE);

        foreach ($cards_property->getValue($legacy) as $target)
        {
            $this->assertTrue(
                method_exists('Controller_Erp221_Index', 'action_'.$target),
                'Короткая ссылка на карточку ведёт на существующую страницу '.$target
            );
        }

        // Живая проверка: вошедший сотрудник получает постоянную переадресацию
        // на новую страницу, а не 404 и не пустой экран.
        if (class_exists('Service_Dev_Smoke'))
        {
            $smoke = new \Service_Dev_Smoke;
            $smoke->login_as('admin');

            $response = \Request::factory('/erp221/deal/view/12')->execute();

            $this->assertSame(301, $response->status(), 'Старый адрес отвечает постоянной переадресацией');
            $this->assertStringEndsWith('/crm/deal/12', (string) $response->headers('Location'), 'Переадресация ведёт на карточку в новом адресе');
        }
    }

    /**
     * Виды используют общие компоненты, а не собственные копии разметки.
     */
    public function test_views_use_shared_components()
    {
        foreach (array('index', 'deals', 'deal', 'clients', 'client', 'stages', 'analytics', 'kb', 'journal', 'reminders') as $view)
        {
            $path = APPPATH.'modules/erp221_crm/views/erp221/'.$view.'.php';

            $this->assertTrue(is_file($path), 'Вид '.$view.' существует');

            $source = file_get_contents($path);

            $this->assertTrue(strpos($source, 'Component::render') !== FALSE, 'Вид '.$view.' использует общие компоненты');
        }
    }
}
