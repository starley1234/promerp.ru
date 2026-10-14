<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Демо-данные «контура людей и учёта»: сотрудники, CRM, составы изделий, QR.
 *
 * Зачем отдельный класс. `Service_Erp811_Seeder` собирает производственную
 * цепочку (склад → цех → качество → закупки) и уже перевалил за две с половиной
 * тысячи строк. Четыре новых слоя — оргструктура, продажи, составы и метки —
 * не продолжают эту цепочку, а опираются на её результат: у сделки есть клиент
 * из `parties`, у состава — материал и техкарта, у наклейки — заказ и склад.
 * Поэтому Seeder передаёт сюда готовый контекст (идентификаторы), а здесь
 * живёт только то, что относится к этим четырём модулям.
 *
 * Правила те же, что у всего стенда:
 *  - данные создаются через сервисы модулей-владельцев, а не прямыми вставками,
 *    поэтому проходят их проверки (иерархия руководителей, стадии сделки,
 *    согласование ревизии, привязка наклейки);
 *  - сбой подшага не останавливает остальные: причина попадает в `problems`,
 *    и сборка честно показывает, что не доехало;
 *  - своего `random` нет: сценарий и порядок шагов фиксированы, а даты
 *    считаются от «сегодня» — стенд всегда выглядит живым (случайны только
 *    сами коды наклеек: их выдаёт erp199).
 *
 * Прямые обновления есть в одном месте — `backdate_*`. Сервисы ставят в журналы
 * `CURRENT_TIMESTAMP`, а воронка продаж и журнал сканирований без истории
 * выглядят как «всё случилось сегодня за секунду». Правятся только метки
 * времени записей, которые только что создал сам сборщик.
 *
 * @package    Erp811
 * @category   Services
 */
class Service_Erp811_Contour
{
    /** @var Service_Erp811_Contour|null */
    protected static $_instance;

    /** @var Service_Erp811_Scenario */
    protected $_scenario;

    /** @var array Счётчики текущего слоя */
    protected $_counts = array();

    /** @var array Замечания текущего слоя */
    protected $_problems = array();

    /** @var array Сотрудники: табельный номер => id */
    protected $_employees = array();

    /** @var array Контакты клиентов: код клиента => список id в порядке сценария */
    protected $_contacts = array();

    /**
     * @return  Service_Erp811_Contour
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
        $this->_scenario = Service_Erp811_Scenario::instance();
    }

    // =========================================================================
    //  Сотрудники и оргструктура (erp310)
    // =========================================================================

    /**
     * Подразделения, должности, штатное расписание и сотрудники.
     *
     * Подразделения и должности из миграции модуля находятся по коду и
     * дополняются: дубли по `upper(code)` база не пропустит, а переименование
     * «Предприятие» в «Печатню 3D» — это и есть настройка под сценарий.
     *
     * @param   array   $ctx    actor, users (ключ => id пользователя)
     * @return  array   counts, problems
     */
    public function workforce(array $ctx)
    {
        $this->begin();

        $service = Service_Erp310_Workforce::instance();
        $config  = $this->_scenario->workforce();
        $actor   = $this->actor($ctx);

        $departments = array();

        foreach ($service->departments(FALSE) as $row)
        {
            $departments[strtoupper((string) $row->code)] = (int) $row->id;
        }

        foreach ((array) Arr::get($config, 'departments', array()) as $item)
        {
            $code = strtoupper($item['code']);
            $data = array(
                'code'       => $code,
                'name'       => $item['name'],
                'kind'       => $item['kind'],
                'status'     => 'active',
                'sort_order' => (int) $item['sort'],
                'parent_id'  => $item['parent'] !== '' && isset($departments[$item['parent']]) ? $departments[$item['parent']] : NULL,
            );

            if (isset($departments[$code]))
            {
                $data['id'] = $departments[$code];
            }

            try
            {
                $row = $service->save_department($data, $actor);
                $departments[$code] = (int) $row->id;
                $this->add('departments');
            }
            catch (Throwable $e)
            {
                $this->problem('Подразделение '.$code, $e);
            }
        }

        $positions = array();

        foreach ($service->positions(NULL, FALSE) as $row)
        {
            $positions[strtoupper((string) $row->code)] = (int) $row->id;
        }

        foreach ((array) Arr::get($config, 'positions', array()) as $item)
        {
            $code = strtoupper($item['code']);

            if ( ! isset($departments[$item['department']]))
            {
                $this->problem('Должность '.$code, new Kohana_Exception('нет подразделения :code', array(':code' => $item['department'])));
                continue;
            }

            $data = array(
                'code'          => $code,
                'name'          => $item['name'],
                'department_id' => $departments[$item['department']],
                'category'      => $item['category'],
                'status'        => 'active',
            );

            if (isset($positions[$code]))
            {
                $data['id'] = $positions[$code];
            }

            try
            {
                $row = $service->save_position($data, $actor);
                $positions[$code] = (int) $row->id;
                $this->add('positions');
            }
            catch (Throwable $e)
            {
                $this->problem('Должность '.$code, $e);
            }
        }

        foreach ((array) Arr::get($config, 'staff_units', array()) as $item)
        {
            $label = 'Штатная единица '.$item['department'].' / '.$item['position'];

            if ( ! isset($departments[$item['department']]) OR ! isset($positions[$item['position']]))
            {
                $this->problem($label, new Kohana_Exception('нет подразделения или должности'));
                continue;
            }

            try
            {
                $service->save_staff_unit(array(
                    'department_id' => $departments[$item['department']],
                    'position_id'   => $positions[$item['position']],
                    'planned_fte'   => $item['fte'],
                    'valid_from'    => $this->day(1500),
                    'status'        => 'active',
                    'note'          => $item['note'],
                ), $actor);

                $this->add('staff_units');
            }
            catch (Throwable $e)
            {
                $this->problem($label, $e);
            }
        }

        $users = isset($ctx['users']) ? (array) $ctx['users'] : array();

        foreach ((array) Arr::get($config, 'employees', array()) as $item)
        {
            $label = 'Сотрудник '.$item['no'];

            try
            {
                $this->save_employee($service, $item, $departments, $positions, $users, $actor);
                $this->add('employees');
            }
            catch (Throwable $e)
            {
                $this->problem($label, $e);
            }
        }

        return $this->finish();
    }

    /**
     * Один сотрудник: приём, при необходимости перевод и увольнение.
     *
     * @param   Service_Erp310_Workforce    $service
     * @param   array                       $item
     * @param   array                       $departments    код => id
     * @param   array                       $positions      код => id
     * @param   array                       $users          ключ => id пользователя
     * @param   integer                     $actor
     * @return  void
     */
    protected function save_employee($service, array $item, array $departments, array $positions, array $users, $actor)
    {
        $manager = isset($item['manager']) && $item['manager'] !== '' && isset($this->_employees[$item['manager']])
            ? $this->_employees[$item['manager']]
            : NULL;

        $user_key = isset($item['user']) ? $item['user'] : '';

        $data = array(
            'personnel_no'    => $item['no'],
            'last_name'       => $item['last'],
            'first_name'      => $item['first'],
            'middle_name'     => $item['middle'],
            'email'           => $item['email'],
            'phone'           => $item['phone'],
            'hired_at'        => $this->day($item['hired_days']),
            'status'          => isset($item['status']) ? $item['status'] : 'active',
            'user_id'         => ($user_key !== '' AND ! empty($users[$user_key])) ? $users[$user_key] : NULL,
            'department_id'   => Arr::get($departments, $item['dept']),
            'position_id'     => Arr::get($positions, $item['pos']),
            'manager_id'      => $manager,
            'rate'            => 1,
            'assignment_from' => $this->day($item['hired_days']),
            'note'            => isset($item['note']) ? $item['note'] : '',
        );

        // Прошлое назначение: сотрудник начинал на другом участке. История
        // назначений в карточке строится из двух записей, а не из подмены.
        if ( ! empty($item['prior']))
        {
            $prior = $item['prior'];
            $first = $data;

            $first['department_id'] = Arr::get($departments, $prior['dept']);
            $first['position_id']   = Arr::get($positions, $prior['pos']);

            $employee = $service->save_employee($first, $actor);

            $data['id']              = (int) $employee->id;
            $data['assignment_from'] = $this->day($prior['moved_days']);
        }

        $employee = $service->save_employee($data, $actor);

        $this->_employees[$item['no']] = (int) $employee->id;

        if ( ! empty($item['dismissed_days']))
        {
            $service->dismiss_employee((int) $employee->id, $this->day($item['dismissed_days']), $actor);
        }
    }

    // =========================================================================
    //  CRM (erp221)
    // =========================================================================

    /**
     * Профили клиентов, контакты, сделки с историей, задачи и база знаний.
     *
     * @param   array   $ctx    actor, users, parties (код клиента => id)
     * @return  array   counts, problems
     */
    public function crm(array $ctx)
    {
        $this->begin();

        $config  = $this->_scenario->crm();
        $actor   = $this->actor($ctx);
        $users   = isset($ctx['users']) ? (array) $ctx['users'] : array();
        $parties = isset($ctx['parties']) ? (array) $ctx['parties'] : array();

        $clients  = Service_Erp221_Clients::instance();
        $deals    = Service_Erp221_Deals::instance();
        $timeline = Service_Erp221_Timeline::instance();

        $this->_contacts = array();

        foreach ((array) Arr::get($config, 'profiles', array()) as $code => $profile)
        {
            if (empty($parties[$code]))
            {
                $this->problem('Профиль '.$code, new Kohana_Exception('клиента нет среди контрагентов стенда'));
                continue;
            }

            try
            {
                $clients->save_profile($parties[$code], array(
                    'segment'          => $profile['segment'],
                    'industry'         => $profile['industry'],
                    'annual_revenue'   => $profile['annual_revenue'],
                    'source'           => $profile['source'],
                    'owner_id'         => Arr::get($users, $profile['owner'], $actor),
                    'ai_profile_notes' => $profile['notes'],
                ), $actor);

                $this->add('crm_profiles');
            }
            catch (Throwable $e)
            {
                $this->problem('Профиль '.$code, $e);
            }
        }

        foreach ((array) Arr::get($config, 'contacts', array()) as $code => $list)
        {
            if (empty($parties[$code]))
            {
                continue;
            }

            $ids = array();

            foreach ($list as $contact)
            {
                try
                {
                    $clients->save_contact($parties[$code], array(
                        'name'                 => $contact['name'],
                        'position'             => $contact['position'],
                        'phone'                => $contact['phone'],
                        'email'                => $contact['email'],
                        'is_primary'           => $contact['primary'] ? 1 : 0,
                        'decision_maker_level' => $contact['level'],
                        'preferred_channel'    => $contact['channel'],
                    ), NULL, $actor);

                    $this->add('crm_contacts');
                }
                catch (Throwable $e)
                {
                    $this->problem('Контакт '.$contact['name'], $e);
                }
            }

            // Сервис не отдаёт id нового контакта: находим по имени из карточки
            try
            {
                $by_name = array();

                foreach ($clients->contacts($parties[$code]) as $row)
                {
                    $by_name[$row['name']] = (int) $row['id'];
                }

                foreach ($list as $contact)
                {
                    $ids[] = Arr::get($by_name, $contact['name']);
                }
            }
            catch (Throwable $e)
            {
                $this->problem('Контакты '.$code, $e);
            }

            $this->_contacts[$code] = $ids;
        }

        $loss = array();

        try
        {
            foreach (Service_Erp221_Stages::instance()->loss_reasons(FALSE) as $reason)
            {
                $loss[$reason['code']] = (int) $reason['id'];
            }
        }
        catch (Throwable $e)
        {
            $this->problem('Причины отказов', $e);
        }

        foreach ((array) Arr::get($config, 'deals', array()) as $deal)
        {
            try
            {
                $this->save_deal($deal, $parties, $users, $loss, $actor);
            }
            catch (Throwable $e)
            {
                $this->problem('Сделка «'.$deal['title'].'»', $e);
            }
        }

        foreach ((array) Arr::get($config, 'knowledge', array()) as $article)
        {
            try
            {
                Service_Erp221_Knowledge::instance()->save(array(
                    'category'         => $article['category'],
                    'question_pattern' => $article['question'],
                    'answer_text'      => $article['answer'],
                    'tags'             => $article['tags'],
                ), $actor);

                $this->add('crm_knowledge');
            }
            catch (Throwable $e)
            {
                $this->problem('База знаний «'.$article['question'].'»', $e);
            }
        }

        return $this->finish();
    }

    /**
     * Одна сделка: создание, проход по стадиям, события ленты, история дат.
     *
     * Стадии проходятся по очереди, как это делает менеджер: каждый переход
     * проверяет обязательные поля стадии и пишет событие в ленту.
     *
     * @param   array   $deal
     * @param   array   $parties
     * @param   array   $users
     * @param   array   $loss       код причины отказа => id
     * @param   integer $actor
     * @return  void
     */
    protected function save_deal(array $deal, array $parties, array $users, array $loss, $actor)
    {
        if (empty($parties[$deal['client']]))
        {
            throw new Kohana_Exception('клиента :code нет среди контрагентов стенда', array(':code' => $deal['client']));
        }

        $client_id = (int) $parties[$deal['client']];
        $owner     = Arr::get($users, 'manager', $actor);
        $amount    = $this->deal_amount($deal);
        $path      = $deal['path'];
        $contacts  = Arr::get($this->_contacts, $deal['client'], array());
        $contact   = isset($deal['contact']) ? Arr::get($contacts, $deal['contact']) : NULL;

        // Срок решения: либо задан явно, либо «через неделю после последнего
        // события» — для закрытых сделок это уже прошедшая дата
        $deadline = $this->day(isset($deal['deadline_days']) ? $deal['deadline_days'] : ($deal['last_days'] - 7));

        $created = Service_Erp221_Deals::instance()->create(array(
            'title'           => $deal['title'],
            'description'     => $deal['description'],
            'counterparty_id' => $client_id,
            'contact_id'      => $contact,
            'stage'           => $path[0],
            'amount'          => $amount,
            'currency'        => 'RUB',
            'priority_level'  => $deal['priority'],
            'source'          => $deal['source'],
            'assigned_to'     => $owner,
        ), $actor);

        $id = (int) $created['id'];

        $this->add('crm_deals');

        $steps = array_slice($path, 1);

        foreach ($steps as $stage)
        {
            $data = array('amount' => $amount, 'deadline' => $deadline);

            if ($stage === 'lost')
            {
                $code = isset($deal['loss']) ? $deal['loss'] : 'other';

                if ( ! isset($loss[$code]))
                {
                    throw new Kohana_Exception('нет причины отказа «:code»', array(':code' => $code));
                }

                $data['loss_reason_id'] = $loss[$code];
                $data['comment']        = 'Клиент отказался';
            }
            elseif ($stage === 'won')
            {
                $data['comment'] = 'Договорились об условиях';
            }

            Service_Erp221_Deals::instance()->change_stage($id, $stage, $data, $actor);
        }

        foreach ((array) Arr::get($deal, 'acts', array()) as $act)
        {
            $row = array(
                'type'        => $act['type'],
                'status'      => $act['status'],
                'subject'     => $act['subject'],
                'content'     => $act['content'],
                'assigned_to' => $owner,
            );

            if ($act['status'] === 'pending')
            {
                $row['scheduled_at'] = $this->at($act['days']);
            }

            Service_Erp221_Timeline::instance()->add($id, $row, $actor);
            $this->add('crm_activities');
        }

        $this->backdate_deal($id, $deal['created_days'], $deal['last_days']);
    }

    /**
     * Сумма сделки: явная либо «цена × партия × число партий» из каталога деталей.
     *
     * @param   array   $deal
     * @return  float
     */
    protected function deal_amount(array $deal)
    {
        if (isset($deal['amount']))
        {
            return (float) $deal['amount'];
        }

        foreach ($this->_scenario->parts() as $part)
        {
            if ($part['code'] === $deal['part'])
            {
                return round($part['price'] * $part['batch'] * (isset($deal['lots']) ? $deal['lots'] : 1), 2);
            }
        }

        throw new Kohana_Exception('в каталоге нет детали :code', array(':code' => $deal['part']));
    }

    /**
     * Растянуть записи сделки по времени: от создания до последнего события.
     *
     * Сервис ставит всем записям одну секунду «сейчас», а воронка и ритм работы
     * менеджера читаются только на разнесённых во времени событиях.
     *
     * @param   integer $deal_id
     * @param   integer $from_days  сколько дней назад создана
     * @param   integer $to_days    сколько дней назад было последнее событие
     * @return  void
     */
    protected function backdate_deal($deal_id, $from_days, $to_days)
    {
        $rows = DB::select('id', 'status')->from('erp_221_activities')
            ->where('deal_id', '=', (int) $deal_id)->order_by('id')
            ->execute()->as_array();

        $stamps = $this->timestamps(count($rows), $from_days, $to_days);

        foreach ($rows as $index => $row)
        {
            DB::update('erp_221_activities')->set(array(
                'created_at'   => $stamps[$index],
                'updated_at'   => $stamps[$index],
                'completed_at' => $row['status'] === 'completed' ? $stamps[$index] : NULL,
            ))->where('id', '=', (int) $row['id'])->execute();
        }

        DB::update('erp_221_deals')->set(array(
            'created_at' => $this->at($from_days),
            'updated_at' => $this->at($to_days),
        ))->where('id', '=', (int) $deal_id)->execute();

        DB::update('erp_221_deals')->set(array('closed_at' => $this->at($to_days)))
            ->where('id', '=', (int) $deal_id)->where('closed_at', 'IS NOT', NULL)->execute();
    }

    // =========================================================================
    //  Составы изделий (erp231)
    // =========================================================================

    /**
     * EBOM → согласование → MBOM для каждой детали, вторые ревизии в работе и
     * комплект из нескольких деталей.
     *
     * @param   array   $ctx    actor, items (код => id), cards (код детали => id техкарты)
     * @return  array   counts, problems
     */
    public function product_structure(array $ctx)
    {
        $this->begin();

        $service = Service_Erp231_ProductStructure::instance();
        $config  = $this->_scenario->structures();
        $actor   = $this->actor($ctx);
        $items   = isset($ctx['items']) ? (array) $ctx['items'] : array();
        $cards   = isset($ctx['cards']) ? (array) $ctx['cards'] : array();

        $scrap       = (array) Arr::get($config, 'scrap', array());
        $consumable  = (string) Arr::get($config, 'consumable', '');
        $revisions   = (array) Arr::get($config, 'revisions', array());

        foreach ($this->_scenario->parts() as $part)
        {
            $code = $part['code'];

            try
            {
                if (empty($items[$code]) OR empty($items[$part['material']]))
                {
                    throw new Kohana_Exception('в номенклатуре нет детали или её материала');
                }

                $lines = array(array(
                    'component_item_id' => $items[$part['material']],
                    'qty'               => round($part['weight_g'] / 1000, 4),
                    'uom'               => 'kg',
                    'scrap_pct'         => (float) Arr::get($scrap, $code, 3),
                    'supply_type'       => 'buy',
                    'operation_no'      => 10,
                    'note'              => 'Норма на деталь с учётом поддержек',
                ));

                // Сопло изнашивается только на FDM: на фотополимере расходника нет
                if ($consumable !== '' AND ! empty($items[$consumable]) AND strpos($part['material'], 'MAT-RES') !== 0)
                {
                    $lines[] = array(
                        'component_item_id' => $items[$consumable],
                        'qty'               => (float) Arr::get($config, 'consumable_qty', 0.0002),
                        'uom'               => 'pcs',
                        'scrap_pct'         => 0,
                        'supply_type'       => 'consumable',
                        'operation_no'      => 10,
                        'note'              => 'Износ сопла на печать',
                    );
                }

                $ebom = $this->approved_bom($service, (int) $items[$code], $lines, $actor);

                $mbom = $service->copy_ebom_to_mbom($ebom['id'], array(
                    'site_code'    => 'KZN',
                    'tech_card_id' => Arr::get($cards, $code),
                ), $actor);

                $service->submit_review($mbom['id'], $actor);
                $service->approve($mbom['id'], $actor);

                $this->add('structures', 2);
                $this->add('structure_lines', count($lines) * 2);

                // Вторая ревизия состава, которая ещё в работе
                if (isset($revisions[$code]))
                {
                    $draft = $service->create(array(
                        'product_item_id' => (int) $items[$code],
                        'kind'            => 'ebom',
                        'site_code'       => '',
                    ), $actor);

                    foreach ($lines as $line)
                    {
                        $line['qty'] = round($line['qty'] * 0.95, 6);
                        $line['note'] = 'Ревизия: облегчённая модель';
                        $service->add_line($draft['id'], $line, $actor);
                    }

                    if ($revisions[$code] === 'review')
                    {
                        $service->submit_review($draft['id'], $actor);
                    }

                    $this->add('structures');
                    $this->add('structure_lines', count($lines));
                }
            }
            catch (Throwable $e)
            {
                $this->problem('Состав '.$code, $e);
            }
        }

        $this->kit($service, $config, $items, $actor);

        return $this->finish();
    }

    /**
     * Создать, проверить и утвердить состав.
     *
     * @param   Service_Erp231_ProductStructure $service
     * @param   integer                         $product_id
     * @param   array                           $lines
     * @param   integer                         $actor
     * @return  array
     */
    protected function approved_bom($service, $product_id, array $lines, $actor)
    {
        $bom = $service->create(array(
            'product_item_id' => $product_id,
            'kind'            => 'ebom',
            'site_code'       => '',
        ), $actor);

        foreach ($lines as $line)
        {
            $service->add_line($bom['id'], $line, $actor);
        }

        $service->submit_review($bom['id'], $actor);

        return $service->approve($bom['id'], $actor);
    }

    /**
     * Комплект «изделие из деталей»: многоуровневый состав в EBOM и MBOM.
     *
     * Комплект — позиция реестра, но не деталь сценария: в счётчик номенклатуры
     * стенда она не входит, чтобы цифры лендинга и диагностики не плавали.
     *
     * @param   Service_Erp231_ProductStructure $service
     * @param   array                           $config
     * @param   array                           $items
     * @param   integer                         $actor
     * @return  void
     */
    protected function kit($service, array $config, array $items, $actor)
    {
        $kit = (array) Arr::get($config, 'kit', array());

        if ( ! $kit)
        {
            return;
        }

        try
        {
            $object = Service_Erp227_Registry::instance()->create_object(array(
                'type'       => 'part',
                'code'       => $kit['code'],
                'name'       => $kit['name'],
                'kind'       => 'product',
                'attributes' => array('client' => 'CL-4105'),
                'metadata'   => array('scenario' => 'print3d', 'role' => 'комплект'),
            ));

            $lines = array();

            foreach ($kit['lines'] as $line)
            {
                if (empty($items[$line['part']]))
                {
                    throw new Kohana_Exception('в комплекте деталь :code, которой нет в номенклатуре', array(':code' => $line['part']));
                }

                $lines[] = array(
                    'component_item_id' => $items[$line['part']],
                    'qty'               => $line['qty'],
                    'uom'               => 'pcs',
                    'supply_type'       => 'make',
                    'note'              => 'Собственное производство',
                );
            }

            $ebom = $this->approved_bom($service, (int) $object->id, $lines, $actor);
            $mbom = $service->copy_ebom_to_mbom($ebom['id'], array('site_code' => 'KZN'), $actor);

            $service->submit_review($mbom['id'], $actor);
            $service->approve($mbom['id'], $actor);

            $this->add('structures', 2);
            $this->add('structure_lines', count($lines) * 2);
        }
        catch (Throwable $e)
        {
            $this->problem('Комплект '.$kit['code'], $e);
        }
    }

    // =========================================================================
    //  QR-метки (erp199)
    // =========================================================================

    /**
     * Свободные объекты, наклейки, сканирования и инвентаризации.
     *
     * Порядок событий — как в жизни: объекты заведены → партия наклеек
     * напечатана и расклеена → закрытая инвентаризация прошлого месяца →
     * рабочие сканирования → текущая, ещё открытая инвентаризация.
     *
     * @param   array   $ctx    actor, users, items (код => id), orders (список id)
     * @return  array   counts, problems
     */
    public function qr(array $ctx)
    {
        $this->begin();

        $config = $this->_scenario->qr();
        $actor  = $this->actor($ctx);
        $users  = isset($ctx['users']) ? (array) $ctx['users'] : array();
        $worker = Arr::get($users, 'worker', $actor);
        $items  = isset($ctx['items']) ? (array) $ctx['items'] : array();
        $orders = isset($ctx['orders']) ? array_values((array) $ctx['orders']) : array();

        $objects = $this->qr_objects($config, $actor, $users);
        $plan    = $this->qr_plan($config, $objects, $items, $orders);

        // Фаза 1. Печать партии и расклейка
        $marker = $this->activity_marker();
        $bound  = $this->qr_bind($plan, $actor);
        $this->backdate_tokens(30);
        $this->spread_activity($marker, 30, 29);

        // Фаза 2. Закрытая инвентаризация номенклатуры
        $marker = $this->activity_marker();
        $this->qr_inventory(array(
            'label'   => 'Инвентаризация склада филамента и деталей',
            'kinds'   => array('item'),
            'comment' => 'Ежемесячная сверка остатков на складе',
        ), $bound, 'item:', array('found', 'found', 'found', 'found', 'damaged'), TRUE, $users, 26);
        $this->spread_activity($marker, 26, 25.8);

        // Фаза 3. Рабочие сканирования
        $marker = $this->activity_marker();
        $this->qr_scans($bound, $orders, $worker);
        $this->spread_activity($marker, 20, 0.2);

        // Фаза 4. Текущая инвентаризация инструмента и мест хранения
        $marker = $this->activity_marker();
        $this->qr_inventory(array(
            'label'   => 'Инвентаризация инструмента и мест хранения',
            'kinds'   => array('local'),
            'comment' => 'Проверка оснастки перед поверкой',
        ), $bound, 'local:', array('found', 'found', 'found', 'found'), FALSE, $users, 2);
        $this->spread_activity($marker, 2, 0.1);

        // Пустые наклейки в запасе: напечатаны, ни к чему не привязаны
        try
        {
            $spare = (int) Arr::get($config, 'spare', 0);

            if ($spare > 0)
            {
                Service_Erp199_Tokens::instance()->issue($spare, 'DEMO-SPARE', $actor, 'Запас: напечатаны, но ещё не наклеены');
                $this->add('qr_tokens', $spare);
            }
        }
        catch (Throwable $e)
        {
            $this->problem('Запас наклеек', $e);
        }

        return $this->finish();
    }

    /**
     * Свободные объекты: создаются черновиком, затем проходят модерацию.
     *
     * Склад как вид цели в erp199 не предусмотрен, поэтому места хранения —
     * свободные объекты типа «location».
     *
     * @param   array   $config
     * @param   integer $actor
     * @param   array   $users
     * @return  array   ключ сценария => id объекта
     */
    protected function qr_objects(array $config, $actor, array $users)
    {
        $service   = Service_Erp199_Objects::instance();
        $moderator = Arr::get($users, 'manager', $actor);
        $result    = array();

        foreach ((array) Arr::get($config, 'objects', array()) as $item)
        {
            try
            {
                $object = $service->create(array(
                    'type_code'     => $item['type'],
                    'name'          => $item['name'],
                    'brand'         => Arr::get($item, 'brand'),
                    'model'         => Arr::get($item, 'model'),
                    'serial_number' => Arr::get($item, 'serial'),
                    'description'   => Arr::get($item, 'description'),
                    'attributes'    => (array) Arr::get($item, 'attributes', array()),
                ), $actor, Arr::get($item, 'source', 'manual'));

                $final = Arr::get($item, 'final', 'active');

                if ($final !== 'draft')
                {
                    $service->moderate($object['id'], $final, $moderator, Arr::get($item, 'comment', 'Проверено при вводе в эксплуатацию'));
                }

                $result[$item['key']] = array('id' => (int) $object['id'], 'status' => $final);
                $this->add('qr_objects');
            }
            catch (Throwable $e)
            {
                $this->problem('Объект «'.$item['name'].'»', $e);
            }
        }

        return $result;
    }

    /**
     * План наклеек: ключ → вид цели и ссылка. Только то, что есть в базе.
     *
     * @param   array   $config
     * @param   array   $objects
     * @param   array   $items
     * @param   array   $orders
     * @return  array   список [ключ, вид, ссылка]
     */
    protected function qr_plan(array $config, array $objects, array $items, array $orders)
    {
        $plan = array();

        foreach ((array) Arr::get($config, 'items', array()) as $code)
        {
            if ( ! empty($items[$code]))
            {
                $plan[] = array('item:'.$code, 'item', (int) $items[$code]);
            }
        }

        foreach ($objects as $key => $object)
        {
            if ($object['status'] === 'active')
            {
                $plan[] = array('local:'.$key, 'local', $object['id']);
            }
        }

        foreach (array_slice($orders, 0, (int) Arr::get($config, 'orders', 0)) as $order_id)
        {
            $plan[] = array('order:'.$order_id, 'order', (int) $order_id);
        }

        foreach ((array) Arr::get($config, 'employees', array()) as $number)
        {
            $id = $this->employee_id($number);

            if ($id)
            {
                $plan[] = array('employee:'.$number, 'employee', $id);
            }
        }

        return $plan;
    }

    /**
     * Выпустить партию наклеек и привязать их к целям плана.
     *
     * @param   array   $plan
     * @param   integer $actor
     * @return  array   ключ плана => строка наклейки
     */
    protected function qr_bind(array $plan, $actor)
    {
        $bound = array();

        if ( ! $plan)
        {
            return $bound;
        }

        $tokens = Service_Erp199_Tokens::instance();

        try
        {
            $batch = $tokens->issue(count($plan), 'DEMO-01', $actor, 'Первая партия наклеек стенда');
        }
        catch (Throwable $e)
        {
            $this->problem('Выпуск наклеек', $e);

            return $bound;
        }

        $this->add('qr_tokens', count($batch['tokens']));

        try
        {
            $tokens->mark_printed($batch['ids'], $actor);
        }
        catch (Throwable $e)
        {
            $this->problem('Отметка печати', $e);
        }

        foreach ($plan as $index => $target)
        {
            try
            {
                $bound[$target[0]] = $tokens->bind($batch['tokens'][$index], $target[1], $target[2], $actor, 'Наклейка установлена при вводе в эксплуатацию');
            }
            catch (Throwable $e)
            {
                $this->problem('Привязка '.$target[0], $e);
            }
        }

        // Повреждённая наклейка: сканер её видит, но принимать отметки отказывается
        if (isset($bound['local:dry-box']))
        {
            try
            {
                $bound['local:dry-box'] = $tokens->set_status($bound['local:dry-box'], 'damaged', $actor, 'Угол наклейки оторван, нужна новая');
            }
            catch (Throwable $e)
            {
                $this->problem('Состояние наклейки', $e);
            }
        }

        return $bound;
    }

    /**
     * Инвентаризация: открыть, отметить объекты и (при необходимости) закрыть.
     *
     * @param   array   $session    label, kinds, comment
     * @param   array   $bound      наклейки по ключам плана
     * @param   string  $prefix     какие ключи участвуют: «item:», «local:»
     * @param   array   $marks      отметки по порядку; остальные остаются неотмеченными
     * @param   boolean $close
     * @param   array   $users
     * @param   integer $days_ago   когда началась
     * @return  void
     */
    protected function qr_inventory(array $session, array $bound, $prefix, array $marks, $close, array $users, $days_ago)
    {
        $service = Service_Erp199_Inventories::instance();
        $actor   = Arr::get($users, 'quality', Arr::get($users, 'admin'));

        try
        {
            $inventory = $service->open(array(
                'label'   => $session['label'],
                'scope'   => array('kinds' => $session['kinds']),
                'comment' => $session['comment'],
            ), $actor);

            $index = 0;

            foreach ($bound as $key => $token)
            {
                if (strpos($key, $prefix) !== 0 OR $token['status'] !== 'active' OR ! isset($marks[$index]))
                {
                    continue;
                }

                $meta = $marks[$index] === 'damaged' ? array('comment' => 'Упаковка помята, содержимое целое') : array();

                $service->mark($inventory['id'], $token, $marks[$index], $actor, $meta);
                $index++;
            }

            if ($close)
            {
                $service->close($inventory['id'], $actor, 'Одна позиция не найдена на месте, одна с повреждённой упаковкой');
            }

            $this->add('qr_inventories');

            // История: сессия началась и закончилась в прошлом
            DB::update('erp_199_inventories')->set(array(
                'started_at' => $this->at($days_ago),
            ))->where('id', '=', (int) $inventory['id'])->execute();

            if ($close)
            {
                DB::update('erp_199_inventories')->set(array(
                    'closed_at' => $this->at($days_ago - 0.2),
                ))->where('id', '=', (int) $inventory['id'])->execute();
            }

            DB::update('erp_199_inventory_items')->set(array(
                'scanned_at' => $this->at($days_ago - 0.1),
            ))->where('inventory_id', '=', (int) $inventory['id'])->execute();
        }
        catch (Throwable $e)
        {
            $this->problem('Инвентаризация «'.$session['label'].'»', $e);
        }
    }

    /**
     * Рабочие сканирования и сценарии терминала.
     *
     * @param   array   $bound
     * @param   array   $orders
     * @param   integer $worker
     * @return  void
     */
    protected function qr_scans(array $bound, array $orders, $worker)
    {
        $scan  = Service_Erp199_Scan::instance();
        $index = 0;

        foreach ($bound as $key => $token)
        {
            if ($token['status'] !== 'active')
            {
                continue;
            }

            // Ходовые метки сканируют чаще: 1–3 раза, без случайности
            for ($i = 0; $i <= $index % 3; $i++)
            {
                try
                {
                    $scan->resolve($token['code'], $worker);
                    $this->add('qr_scans');
                }
                catch (Throwable $e)
                {
                    $this->problem('Сканирование '.$key, $e);
                }
            }

            $index++;
        }

        $actions = array(
            array('local:caliper', 'location_mark', array('location' => 'Рабочее место ОТК, левый ящик', 'comment' => 'Вернули после замеров')),
            array('local:micrometer', 'location_mark', array('location' => 'Шкаф ОТК, полка 2')),
            array('local:rack-a1', 'note', array('comment' => 'Освободили нижнюю полку под катушки TPU')),
            array('local:qc-folder', 'status_mark', array('status' => 'active', 'comment' => 'Папка проверена, паспорта на месте')),
            array('item:MAT-TPU-FLX', 'note', array('comment' => 'Катушка почти закончилась: до закупки хватит на два заказа')),
            array('item:MAT-PLA-BLK', 'location_mark', array('location' => 'Стеллаж A1, верхняя полка')),
        );

        if (isset($orders[0]))
        {
            $actions[] = array('order:'.$orders[0], 'stage_mark', array('stage' => 'control', 'comment' => 'Партия передана на контроль ОТК'));
        }

        if (isset($orders[1]))
        {
            $actions[] = array('order:'.$orders[1], 'note', array('comment' => 'Упаковка готова, ждём забора клиентом'));
        }

        foreach ($actions as $action)
        {
            if ( ! isset($bound[$action[0]]) OR $bound[$action[0]]['status'] !== 'active')
            {
                continue;
            }

            try
            {
                $scan->execute($bound[$action[0]]['code'], $action[1], $action[2], array(), $worker);
                $this->add('qr_scans');
            }
            catch (Throwable $e)
            {
                $this->problem('Сценарий «'.$action[1].'» для '.$action[0], $e);
            }
        }
    }

    /**
     * Сотрудник по табельному номеру: из текущей сборки или через поиск.
     *
     * @param   string  $number
     * @return  integer|null
     */
    protected function employee_id($number)
    {
        if (isset($this->_employees[$number]))
        {
            return $this->_employees[$number];
        }

        try
        {
            $page = Service_Erp310_Workforce::instance()->search(array('term' => $number), 1);

            if ( ! empty($page['rows']))
            {
                return (int) $page['rows'][0]['id'];
            }
        }
        catch (Throwable $e)
        {
            $this->problem('Сотрудник '.$number, $e);
        }

        return NULL;
    }

    /**
     * Последняя запись журнала QR: точка отсчёта для «записей этой фазы».
     *
     * @return  integer
     */
    protected function activity_marker()
    {
        return (int) DB::select(array(DB::expr('COALESCE(MAX(id), 0)'), 'last_id'))
            ->from('erp_199_activity')->execute()->get('last_id', 0);
    }

    /**
     * Разнести записи журнала, появившиеся после точки отсчёта, по времени.
     *
     * @param   integer $after_id
     * @param   float   $from_days
     * @param   float   $to_days
     * @return  void
     */
    protected function spread_activity($after_id, $from_days, $to_days)
    {
        try
        {
            $rows   = DB::select('id')->from('erp_199_activity')->where('id', '>', (int) $after_id)
                ->order_by('id')->execute()->as_array();
            $stamps = $this->timestamps(count($rows), $from_days, $to_days);

            foreach ($rows as $index => $row)
            {
                DB::update('erp_199_activity')->set(array('created_at' => $stamps[$index]))
                    ->where('id', '=', (int) $row['id'])->execute();
            }
        }
        catch (Throwable $e)
        {
            $this->problem('История журнала QR', $e);
        }
    }

    /**
     * Наклейки первой партии: выпущены и расклеены в прошлом.
     *
     * @param   integer $days_ago
     * @return  void
     */
    protected function backdate_tokens($days_ago)
    {
        try
        {
            DB::update('erp_199_tokens')->set(array(
                'issued_at'  => $this->at($days_ago),
                'created_at' => $this->at($days_ago),
            ))->where('batch', '=', 'DEMO-01')->execute();

            DB::update('erp_199_tokens')->set(array('bound_at' => $this->at($days_ago - 1)))
                ->where('batch', '=', 'DEMO-01')->where('bound_at', 'IS NOT', NULL)->execute();

            DB::update('erp_199_tokens')->set(array('printed_at' => $this->at($days_ago - 0.5)))
                ->where('batch', '=', 'DEMO-01')->where('printed_at', 'IS NOT', NULL)->execute();
        }
        catch (Throwable $e)
        {
            $this->problem('История наклеек', $e);
        }
    }

    // =========================================================================
    //  Вспомогательное
    // =========================================================================

    /**
     * Пользователь, от имени которого пишутся данные.
     *
     * @param   array   $ctx
     * @return  integer|null
     */
    protected function actor(array $ctx)
    {
        return ! empty($ctx['actor']) ? (int) $ctx['actor'] : NULL;
    }

    /**
     * Дата «N дней назад» (отрицательное N — в будущем).
     *
     * @param   integer $days
     * @return  string  Y-m-d
     */
    protected function day($days)
    {
        $days = (int) $days;

        return date('Y-m-d', strtotime($days >= 0 ? '-'.$days.' days' : '+'.abs($days).' days'));
    }

    /**
     * Момент «N дней назад» (дробные дни допустимы, отрицательные — в будущем).
     *
     * @param   float   $days
     * @return  string  Y-m-d H:i:s
     */
    protected function at($days)
    {
        return date('Y-m-d H:i:s', time() - (int) round($days * 86400));
    }

    /**
     * Равномерно распределённые моменты от `$from_days` до `$to_days` назад.
     *
     * @param   integer $count
     * @param   float   $from_days
     * @param   float   $to_days
     * @return  array
     */
    protected function timestamps($count, $from_days, $to_days)
    {
        $stamps = array();

        for ($i = 0; $i < $count; $i++)
        {
            $share    = $count > 1 ? $i / ($count - 1) : 0;
            $stamps[] = $this->at($from_days - ($from_days - $to_days) * $share);
        }

        return $stamps;
    }

    /**
     * @return  void
     */
    protected function begin()
    {
        $this->_counts   = array();
        $this->_problems = array();
    }

    /**
     * @return  array
     */
    protected function finish()
    {
        return array('counts' => $this->_counts, 'problems' => $this->_problems);
    }

    /**
     * @param   string  $key
     * @param   integer $value
     * @return  void
     */
    protected function add($key, $value = 1)
    {
        $this->_counts[$key] = (isset($this->_counts[$key]) ? $this->_counts[$key] : 0) + (int) $value;
    }

    /**
     * Записать замечание: сбой подшага не должен останавливать слой.
     *
     * @param   string      $what
     * @param   Throwable   $e
     * @return  void
     */
    protected function problem($what, $e)
    {
        $message = $e->getMessage();

        if ($e instanceof ORM_Validation_Exception)
        {
            $parts = array();

            foreach ($e->errors('') as $field => $errors)
            {
                $parts[] = $field.': '.implode(', ', (array) $errors);
            }

            $message = $parts ? implode('; ', $parts) : $message;
        }

        $this->_problems[] = $what.': '.$message;
    }
}
