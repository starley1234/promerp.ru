<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Сделки: воронка от заявки до решения (erp221_crm).
 *
 * Сделка соединяет клиента (`erp_301_counterparties`), стадию воронки, деньги
 * и срок, а вся история работы — звонки, письма, встречи, задачи и файлы —
 * лежит в ленте (Service_Erp221_Timeline). Номенклатура, заказы и склад в
 * сделке не дублируются: когда продажа переходит в производство, предметные
 * данные остаются у своих модулей (erp227, erp402, erp403).
 *
 * @package    Erp221
 * @category   Services
 */
class Service_Erp221_Deals
{
    /** Префикс номера сделки. */
    const NUMBER_PREFIX = 'DEAL';

    /** @var Service_Erp221_Deals|null */
    protected static $_instance;

    /**
     * @return  Service_Erp221_Deals
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
     * Поиск сделок с фильтрами и пагинацией.
     *
     * Поддерживаемые фильтры:
     *   q              — подстрока в номере, названии, клиенте или описании;
     *   stage          — код или id стадии;
     *   kind           — open|won|lost (вид стадии);
     *   counterparty   — id клиента;
     *   assigned_to    — id ответственного;
     *   priority       — low|medium|high|critical;
     *   source         — источник обращения;
     *   deadline_from  — срок не раньше даты (YYYY-MM-DD);
     *   deadline_to    — срок не позже даты;
     *   overdue        — TRUE: срок прошёл, а сделка в работе;
     *   stale          — дней без активности (число);
     *   min_amount/max_amount — сумма сделки;
     *   include_deleted — показывать удалённые (для аудита).
     *
     * @param   array   $filters
     * @param   integer $limit
     * @param   integer $offset
     * @return  array   ['rows' => array, 'total' => int]
     */
    public function search(array $filters = array(), $limit = 25, $offset = 0)
    {
        $total = (int) $this->query($filters, TRUE)->execute()->get('total');

        $rows = $this->query($filters)
            ->order_by('d.deadline', 'ASC')
            ->order_by('d.id', 'DESC')
            ->limit((int) $limit)
            ->offset((int) $offset)
            ->execute()->as_array();

        $deals = array();

        foreach ($rows as $row)
        {
            $deals[] = $this->prepare($row);
        }

        return array('rows' => $deals, 'total' => $total);
    }

    /**
     * Сколько сделок попадает под фильтр.
     *
     * @param   array  $filters
     * @return  integer
     */
    public function count(array $filters = array())
    {
        return (int) $this->query($filters, TRUE)->execute()->get('total');
    }

    /**
     * Карточка сделки: сделка, клиент, контакты, лента и ИИ-разбор.
     *
     * @param   mixed  $ref  id или номер сделки
     * @return  array
     * @throws  Kohana_Exception
     */
    public function get($ref)
    {
        $row = $this->base_query()
            ->where(is_numeric($ref) ? 'd.id' : 'd.number', '=', is_numeric($ref) ? (int) $ref : (string) $ref)
            ->limit(1)
            ->execute()->current();

        if ( ! $row)
        {
            throw new Kohana_Exception('Сделка «:ref» не найдена', array(':ref' => (string) $ref));
        }

        $deal = $this->prepare($row);

        $deal['contacts']    = $this->contacts($deal['id']);
        $deal['timeline']    = Service_Erp221_Timeline::instance()->timeline($deal['id'], array(), 30);
        $deal['tasks']       = Service_Erp221_Timeline::instance()->tasks(NULL, array('deal_id' => $deal['id']));
        $deal['insights']    = Service_Erp221_Ai::instance()->history('deal', $deal['id'], 10);
        $deal['stage']       = Service_Erp221_Stages::instance()->get($deal['stage_id']);
        $deal['stale_days']  = $this->stale_days($deal['id']);

        return $deal;
    }

    /**
     * Найти сделку по номеру, не выбрасывая исключение (импорт, интеграции).
     *
     * @param   string  $number
     * @return  array|null
     */
    public function find_by_number($number)
    {
        try
        {
            return $this->get((string) $number);
        }
        catch (Exception $e)
        {
            return NULL;
        }
    }

    /**
     * Создать сделку.
     *
     * @param   array   $data  title, counterparty_id, amount, deadline, …
     * @param   integer $actor
     * @return  array
     * @throws  Kohana_Exception
     */
    public function create(array $data, $actor = NULL)
    {
        $title = trim((string) Arr::get($data, 'title', ''));

        if ($title === '')
        {
            throw new Kohana_Exception('Укажите название сделки');
        }

        $counterparty_id = (int) Arr::get($data, 'counterparty_id', Arr::get($data, 'client_id'));

        if ( ! $counterparty_id)
        {
            throw new Kohana_Exception('Укажите клиента сделки');
        }

        // Клиент должен существовать у владельца справочника (erp301).
        Service_Erp301_Counterparties::instance()->get($counterparty_id);

        $stage = $this->stage_from_data($data);

        $values = array(
            'number'          => $this->next_number(),
            'title'           => mb_substr($title, 0, 255),
            'description'     => Service_Erp221_Cast::text(Arr::get($data, 'description')),
            'counterparty_id' => $counterparty_id,
            'contact_id'      => $this->contact_id($counterparty_id, Arr::get($data, 'contact_id')),
            'stage_id'        => (int) $stage['id'],
            'loss_reason_id'  => NULL,
            'amount'          => $this->amount(Arr::get($data, 'amount', 0)),
            'currency'        => strtoupper(mb_substr((string) Arr::get($data, 'currency', 'RUB'), 0, 8)),
            'probability'     => $this->probability($data, $stage),
            'deadline'        => Service_Erp221_Cast::text(Arr::get($data, 'deadline')),
            'priority_level'  => $this->priority(Arr::get($data, 'priority_level', 'medium')),
            'source'          => Service_Erp221_Cast::text(Arr::get($data, 'source')),
            'assigned_to'     => (int) Arr::get($data, 'assigned_to') ?: NULL,
            'properties'      => json_encode(Service_Erp221_Cast::json(Arr::get($data, 'properties', array())), JSON_UNESCAPED_UNICODE),
            'is_deleted'      => FALSE,
            'created_by'      => $actor ? (int) $actor : NULL,
            'updated_by'      => $actor ? (int) $actor : NULL,
            'created_at'      => DB::expr('CURRENT_TIMESTAMP'),
            'updated_at'      => DB::expr('CURRENT_TIMESTAMP'),
        );

        $values['search_text'] = $this->search_text($values, $counterparty_id);

        $id = Service_Erp221_Cast::insert_id(
            DB::insert('erp_221_deals', array_keys($values))->values(array_values($values))->execute()
        );

        Service_Erp221_Timeline::instance()->add($id, array(
            'type'    => 'system',
            'content' => 'Сделка создана на стадии «'.$stage['label'].'»',
            'meta'    => array('stage_code' => $stage['code']),
        ), $actor);

        Service_Erp221_Timeline::instance()->event('deal.created', $actor, array(
            'deal_id' => (int) $id,
            'number'  => $values['number'],
        ));

        return $this->get($id);
    }

    /**
     * Изменить поля сделки.
     *
     * @param   integer $id
     * @param   array   $data
     * @param   integer $actor
     * @return  array
     * @throws  Kohana_Exception
     */
    public function update($id, array $data, $actor = NULL)
    {
        $deal = $this->get($id);

        $values = array(
            'updated_by' => $actor ? (int) $actor : NULL,
            'updated_at' => DB::expr('CURRENT_TIMESTAMP'),
        );

        if (array_key_exists('title', $data))
        {
            $title = trim((string) $data['title']);

            if ($title === '')
            {
                throw new Kohana_Exception('Название сделки не может быть пустым');
            }

            $values['title'] = mb_substr($title, 0, 255);
        }

        if (array_key_exists('description', $data))
        {
            $values['description'] = Service_Erp221_Cast::text($data['description']);
        }

        if (array_key_exists('amount', $data))
        {
            $values['amount'] = $this->amount($data['amount']);
        }

        if (array_key_exists('currency', $data))
        {
            $values['currency'] = strtoupper(mb_substr((string) $data['currency'], 0, 8));
        }

        if (array_key_exists('deadline', $data))
        {
            $values['deadline'] = Service_Erp221_Cast::text($data['deadline']);
        }

        if (array_key_exists('priority_level', $data))
        {
            $values['priority_level'] = $this->priority($data['priority_level']);
        }

        if (array_key_exists('source', $data))
        {
            $values['source'] = Service_Erp221_Cast::text($data['source']);
        }

        if (array_key_exists('assigned_to', $data))
        {
            $values['assigned_to'] = (int) $data['assigned_to'] ?: NULL;
        }

        if (array_key_exists('contact_id', $data))
        {
            $values['contact_id'] = $this->contact_id($deal['counterparty_id'], $data['contact_id']);
        }

        if (array_key_exists('probability', $data))
        {
            $values['probability'] = max(0, min(100, (int) $data['probability']));
        }

        if (array_key_exists('properties', $data))
        {
            $properties = Service_Erp221_Cast::json($data['properties']);

            if ($properties)
            {
                $values['properties'] = json_encode(array_merge($deal['properties'], $properties), JSON_UNESCAPED_UNICODE);
            }
        }

        $values['search_text'] = $this->search_text($values + $deal, $deal['counterparty_id']);

        DB::update('erp_221_deals')->set($values)->where('id', '=', (int) $deal['id'])->execute();

        return $this->get($deal['id']);
    }

    /**
     * Сменить стадию сделки: проверить требования и выполнить действия стадии.
     *
     * @param   integer $id
     * @param   mixed   $stage_ref   код или id новой стадии
     * @param   array   $data        loss_reason_id, comment, amount, deadline, …
     * @param   integer $actor
     * @return  array
     * @throws  Kohana_Exception
     */
    public function change_stage($id, $stage_ref, array $data = array(), $actor = NULL)
    {
        $deal  = $this->get($id);
        $stage = Service_Erp221_Stages::instance()->get($stage_ref);

        if ((int) $stage['id'] === (int) $deal['stage_id'])
        {
            return $deal;
        }

        $loss_reason_id = (int) Arr::get($data, 'loss_reason_id') ?: NULL;

        // Требования стадии проверяются до записи: недозаполненная сделка не
        // должна оказаться в «Выиграна» или «Проиграна» с пустой суммой.
        $this->validate_requirements($deal, $stage, $data, $loss_reason_id);

        $values = array(
            'stage_id'        => (int) $stage['id'],
            'loss_reason_id'  => $stage['requires_loss_reason'] ? $loss_reason_id : NULL,
            'probability'     => $this->probability(array('probability' => $stage['probability']), $stage),
            'updated_by'      => $actor ? (int) $actor : NULL,
            'updated_at'      => DB::expr('CURRENT_TIMESTAMP'),
        );

        if ($stage['kind'] !== 'open')
        {
            $values['closed_at'] = DB::expr('CURRENT_TIMESTAMP');
        }
        else
        {
            $values['closed_at'] = NULL;
        }

        // Поля, которых не хватало для перехода, заполняются тем же действием.
        foreach (array('amount', 'deadline', 'description', 'source') as $field)
        {
            if (array_key_exists($field, $data) AND $data[$field] !== NULL AND $data[$field] !== '')
            {
                $values[$field] = ($field === 'amount') ? $this->amount($data[$field]) : Service_Erp221_Cast::text($data[$field]);
            }
        }

        DB::update('erp_221_deals')->set($values)->where('id', '=', (int) $deal['id'])->execute();

        $this->execute_actions($deal, $stage, $data, $actor);

        Service_Erp221_Timeline::instance()->event('deal.stage_changed', $actor, array(
            'deal_id'    => (int) $deal['id'],
            'from'       => $deal['stage']['code'],
            'to'         => $stage['code'],
            'loss_reason'=> $loss_reason_id,
            'comment'    => Arr::get($data, 'comment'),
        ));

        return $this->get($deal['id']);
    }

    /**
     * Мягко удалить сделку.
     *
     * @param   integer $id
     * @param   integer $actor
     * @return  boolean
     */
    public function delete($id, $actor = NULL)
    {
        $deal = $this->get($id);

        DB::update('erp_221_deals')
            ->set(array(
                'is_deleted' => TRUE,
                'updated_by' => $actor ? (int) $actor : NULL,
                'updated_at' => DB::expr('CURRENT_TIMESTAMP'),
            ))
            ->where('id', '=', (int) $deal['id'])
            ->execute();

        Service_Erp221_Timeline::instance()->event('deal.deleted', $actor, array(
            'deal_id' => (int) $deal['id'],
            'number'  => $deal['number'],
        ));

        return TRUE;
    }

    /**
     * Сделки, сгруппированные по стадиям (доска Kanban).
     *
     * @param   array  $filters
     * @param   integer $limit_per_stage
     * @return  array
     */
    public function board(array $filters = array(), $limit_per_stage = 50)
    {
        $columns = array();

        foreach (Service_Erp221_Stages::instance()->all() as $stage)
        {
            $stage_filters = $filters + array('stage' => $stage['code']);

            $page = $this->search($stage_filters, $limit_per_stage, 0);

            $columns[] = array(
                'stage'      => $stage,
                'deals'      => $page['rows'],
                'count'      => $page['total'],
                'shown'      => count($page['rows']),
                'amount'     => array_sum(array_map(function ($deal) { return $deal['amount']; }, $page['rows'])),
                'forecast'   => array_sum(array_map(function ($deal) { return $deal['forecast']; }, $page['rows'])),
            );
        }

        return $columns;
    }

    /**
     * Сводка по воронке: деньги, прогноз и просрочка.
     *
     * Прогноз — это сумма, взвешенная по вероятности стадии. Это рабочая
     * оценка руководителя, а не бухгалтерская выручка.
     *
     * @return  array
     */
    public function stats()
    {
        $row = DB::select(
                array(DB::expr("COUNT(*) FILTER (WHERE s.kind = 'open')"), 'open_count'),
                array(DB::expr("COALESCE(SUM(d.amount) FILTER (WHERE s.kind = 'open'), 0)"), 'open_amount'),
                array(DB::expr("COALESCE(SUM(d.amount * COALESCE(d.probability, s.probability) / 100.0) FILTER (WHERE s.kind = 'open'), 0)"), 'forecast'),
                array(DB::expr("COUNT(*) FILTER (WHERE s.kind = 'won')"), 'won_count'),
                array(DB::expr("COALESCE(SUM(d.amount) FILTER (WHERE s.kind = 'won'), 0)"), 'won_amount'),
                array(DB::expr("COUNT(*) FILTER (WHERE s.kind = 'lost')"), 'lost_count'),
                array(DB::expr("COUNT(*) FILTER (WHERE s.kind = 'open' AND d.deadline IS NOT NULL AND d.deadline < CURRENT_DATE)"), 'overdue')
            )
            ->from(array('erp_221_deals', 'd'))
            ->join(array('erp_221_stages', 's'), 'LEFT')->on('s.id', '=', 'd.stage_id')
            ->where('d.is_deleted', '=', FALSE)
            ->execute()->current();

        $stats = array(
            'open_count'  => (int) $row['open_count'],
            'open_amount' => (float) $row['open_amount'],
            'forecast'    => (float) $row['forecast'],
            'won_count'   => (int) $row['won_count'],
            'won_amount'  => (float) $row['won_amount'],
            'lost_count'  => (int) $row['lost_count'],
            'overdue'     => (int) $row['overdue'],
        );

        $stats['conversion'] = ($stats['won_count'] + $stats['lost_count']) > 0
            ? round($stats['won_count'] * 100 / ($stats['won_count'] + $stats['lost_count']), 1)
            : NULL;

        $stats['average_deal'] = $stats['won_count'] > 0
            ? round($stats['won_amount'] / $stats['won_count'], 2)
            : 0;

        return $stats;
    }

    /**
     * Участники сделки (контактные лица клиента).
     *
     * @param   integer $deal_id
     * @return  array
     */
    public function contacts($deal_id)
    {
        $rows = DB::select(
                array('dc.id', 'link_id'),
                'dc.role',
                array('dc.is_primary', 'is_primary'),
                array('c.id', 'contact_id'),
                'c.name', 'c.position', 'c.phone', 'c.email'
            )
            ->from(array('erp_221_deal_contacts', 'dc'))
            ->join(array('erp_301_contacts', 'c'), 'INNER')->on('c.id', '=', 'dc.contact_id')
            ->where('dc.deal_id', '=', (int) $deal_id)
            ->where('c.is_deleted', '=', 0)
            ->order_by('dc.is_primary', 'DESC')
            ->order_by('c.name', 'ASC')
            ->execute()->as_array();

        $contacts = array();

        foreach ($rows as $row)
        {
            $contacts[] = array(
                'link_id'    => (int) $row['link_id'],
                'contact_id' => (int) $row['contact_id'],
                'role'       => $row['role'],
                'is_primary' => Service_Erp221_Cast::boolean($row['is_primary']),
                'name'       => $row['name'],
                'position'   => $row['position'],
                'phone'      => $row['phone'],
                'email'      => $row['email'],
            );
        }

        return $contacts;
    }

    /**
     * Привязать контактное лицо к сделке.
     *
     * @param   integer $deal_id
     * @param   integer $contact_id
     * @param   boolean $is_primary
     * @param   string  $role
     * @param   integer $actor
     * @return  array
     * @throws  Kohana_Exception
     */
    public function add_contact($deal_id, $contact_id, $is_primary = FALSE, $role = NULL, $actor = NULL)
    {
        $deal = $this->get($deal_id);

        $contact = DB::select()->from('erp_301_contacts')
            ->where('id', '=', (int) $contact_id)
            ->where('is_deleted', '=', 0)
            ->execute()->current();

        if ( ! $contact)
        {
            throw new Kohana_Exception('Контакт #:id не найден', array(':id' => (int) $contact_id));
        }

        if ((int) $contact['counterparty_id'] !== (int) $deal['counterparty_id'])
        {
            throw new Kohana_Exception(
                'Контакт «:name» принадлежит другому клиенту — сначала добавьте его в карточку клиента',
                array(':name' => $contact['name'])
            );
        }

        if ($is_primary)
        {
            DB::update('erp_221_deal_contacts')->set(array('is_primary' => FALSE))
                ->where('deal_id', '=', (int) $deal['id'])->execute();
        }

        $existing = DB::select('id')->from('erp_221_deal_contacts')
            ->where('deal_id', '=', (int) $deal['id'])
            ->where('contact_id', '=', (int) $contact_id)
            ->execute()->current();

        if ($existing)
        {
            DB::update('erp_221_deal_contacts')
                ->set(array('is_primary' => (bool) $is_primary, 'role' => Service_Erp221_Cast::text($role)))
                ->where('id', '=', (int) $existing['id'])->execute();
        }
        else
        {
            DB::insert('erp_221_deal_contacts', array('deal_id', 'contact_id', 'role', 'is_primary'))
                ->values(array((int) $deal['id'], (int) $contact_id, Service_Erp221_Cast::text($role), (bool) $is_primary))
                ->execute();
        }

        if ($is_primary)
        {
            DB::update('erp_221_deals')->set(array('contact_id' => (int) $contact_id))
                ->where('id', '=', (int) $deal['id'])->execute();
        }

        Service_Erp221_Timeline::instance()->add($deal['id'], array(
            'type'    => 'system',
            'content' => 'В сделку добавлен контакт: '.$contact['name'],
        ), $actor);

        return $this->get($deal['id']);
    }

    /**
     * Убрать контакт из сделки (само контактное лицо остаётся у клиента).
     *
     * @param   integer $deal_id
     * @param   integer $contact_id
     * @param   integer $actor
     * @return  array
     */
    public function remove_contact($deal_id, $contact_id, $actor = NULL)
    {
        $deal = $this->get($deal_id);

        DB::delete('erp_221_deal_contacts')
            ->where('deal_id', '=', (int) $deal['id'])
            ->where('contact_id', '=', (int) $contact_id)
            ->execute();

        if ((int) $deal['contact_id'] === (int) $contact_id)
        {
            DB::update('erp_221_deals')->set(array('contact_id' => NULL))
                ->where('id', '=', (int) $deal['id'])->execute();
        }

        Service_Erp221_Timeline::instance()->add($deal['id'], array(
            'type'    => 'system',
            'content' => 'Контакт убран из сделки',
        ), $actor);

        return $this->get($deal['id']);
    }

    /**
     * Сколько дней сделка без движения.
     *
     * @param   integer $deal_id
     * @return  integer
     */
    public function stale_days($deal_id)
    {
        $last = DB::select(array(DB::expr('MAX(created_at)'), 'last_at'))
            ->from('erp_221_activities')
            ->where('deal_id', '=', (int) $deal_id)
            ->where('is_deleted', '=', FALSE)
            ->execute()->get('last_at');

        $reference = $last ? $last : DB::select('updated_at')->from('erp_221_deals')
            ->where('id', '=', (int) $deal_id)->execute()->get('updated_at');

        if ( ! $reference)
        {
            return 0;
        }

        $days = (int) floor((time() - strtotime((string) $reference)) / 86400);

        return max(0, $days);
    }

    /**
     * Следующий номер сделки: DEAL-ГГММ-NNN.
     *
     * Номер читается человеком и уникален; конкурентные вставки не страшны —
     * UNIQUE на колонке заставит повторить попытку, а не создать дубль.
     *
     * @return  string
     */
    public function next_number()
    {
        $prefix = self::NUMBER_PREFIX.'-'.date('ym').'-';

        for ($attempt = 0; $attempt < 5; $attempt++)
        {
            $last = DB::select('number')->from('erp_221_deals')
                ->where('number', 'LIKE', $prefix.'%')
                ->order_by('number', 'DESC')
                ->limit(1)
                ->execute()->get('number');

            $next = $last ? ((int) substr($last, -3)) + 1 : 1;

            $number = $prefix.str_pad((string) $next, 3, '0', STR_PAD_LEFT);

            $exists = DB::select(array(DB::expr('COUNT(*)'), 'total'))->from('erp_221_deals')
                ->where('number', '=', $number)->execute()->get('total');

            if ( ! $exists)
            {
                return $number;
            }
        }

        // Редкий случай: параллельные заявки заняли номера — берём время.
        return self::NUMBER_PREFIX.'-'.date('ymd-His');
    }

    /**
     * Подготовить строку сделки к показу.
     *
     * @param   array  $row
     * @return  array
     */
    public function prepare(array $row)
    {
        $row['id']             = (int) $row['id'];
        $row['amount']         = (float) $row['amount'];
        $row['probability']    = $row['probability'] === NULL ? NULL : (int) $row['probability'];
        $row['properties']     = Service_Erp221_Cast::json($row['properties']);
        $row['created_at']     = Service_Erp221_Cast::stamp($row['created_at']);
        $row['updated_at']     = Service_Erp221_Cast::stamp($row['updated_at']);
        $row['closed_at']      = Service_Erp221_Cast::stamp($row['closed_at']);

        // Стадия и клиент — вложенными структурами: представлениям и API так
        // проще, а имена колонок «d.*» и «c.*» больше не конфликтуют.
        $row['stage'] = array(
            'id'          => (int) $row['stage_id'],
            'code'        => $row['stage_code'],
            'label'       => $row['stage_label'],
            'kind'        => $row['stage_kind'],
            'color'       => $row['stage_color'],
            'probability' => (int) $row['stage_probability'],
        );

        $row['client'] = array(
            'id'     => (int) $row['counterparty_id'],
            'name'   => $row['client_name'],
            'code'   => $row['client_code'],
            'inn'    => $row['client_inn'],
            'phone'  => $row['client_phone'],
            'email'  => $row['client_email'],
            'status' => $row['client_status'],
            'rating' => (int) $row['client_rating'],
        );

        $probability = $row['probability'] === NULL ? (int) $row['stage_probability'] : (int) $row['probability'];

        $row['probability']  = $probability;
        $row['forecast']     = round($row['amount'] * $probability / 100, 2);
        $row['is_overdue']   = $row['stage_kind'] === 'open' AND $row['deadline'] AND strtotime($row['deadline']) < strtotime('today');

        return $row;
    }

    /**
     * Базовый SELECT карточки: сделка, стадия и клиент одним запросом.
     *
     * @return  Database_Query_Builder_Select
     */
    protected function base_query($count = FALSE)
    {
        if ($count)
        {
            // Считаем строки теми же фильтрами, но без выборки колонок.
            return DB::select(array(DB::expr('COUNT(*)'), 'total'))
                ->from(array('erp_221_deals', 'd'))
                ->join(array('erp_221_stages', 's'), 'INNER')->on('s.id', '=', 'd.stage_id')
                ->join(array('erp_301_counterparties', 'c'), 'INNER')->on('c.id', '=', 'd.counterparty_id');
        }

        return DB::select(
                'd.*',
                array('s.label', 'stage_label'),
                array('s.code', 'stage_code'),
                array('s.kind', 'stage_kind'),
                array('s.color', 'stage_color'),
                array('s.probability', 'stage_probability'),
                array('c.name', 'client_name'),
                array('c.code', 'client_code'),
                array('c.inn', 'client_inn'),
                array('c.phone', 'client_phone'),
                array('c.email', 'client_email'),
                array('c.status', 'client_status'),
                array('c.rating', 'client_rating')
            )
            ->from(array('erp_221_deals', 'd'))
            ->join(array('erp_221_stages', 's'), 'INNER')->on('s.id', '=', 'd.stage_id')
            ->join(array('erp_301_counterparties', 'c'), 'INNER')->on('c.id', '=', 'd.counterparty_id');
    }

    /**
     * Запрос списка с фильтрами.
     *
     * @param   array  $filters
     * @return  Database_Query_Builder_Select
     */
    protected function query(array $filters, $count = FALSE)
    {
        $query = $this->base_query($count);

        // Удалённые сделки не показываются никому, кроме аудита: is_deleted —
        // это «убрано из работы», а не «стёрто».
        if (empty($filters['include_deleted']))
        {
            $query->where('d.is_deleted', '=', FALSE);
        }

        if ( ! empty($filters['q']))
        {
            $term = '%'.mb_strtolower(trim((string) $filters['q'])).'%';

            $query->where_open()
                ->where('d.search_text', 'LIKE', $term)
                ->or_where('c.name', 'LIKE', '%'.trim((string) $filters['q']).'%')
                ->or_where('d.number', 'ILIKE', '%'.trim((string) $filters['q']).'%')
                ->where_close();
        }

        if ( ! empty($filters['stage']))
        {
            $stage = Service_Erp221_Stages::instance()->find($filters['stage']);

            if ($stage)
            {
                $query->where('d.stage_id', '=', (int) $stage['id']);
            }
            else
            {
                // Неизвестная стадия — честный пустой результат вместо всех сделок.
                $query->where('d.id', '=', 0);
            }
        }

        if ( ! empty($filters['kind']))
        {
            $query->where('s.kind', '=', (string) $filters['kind']);
        }

        if ( ! empty($filters['counterparty']))
        {
            $query->where('d.counterparty_id', '=', (int) $filters['counterparty']);
        }

        if ( ! empty($filters['assigned_to']))
        {
            $query->where('d.assigned_to', '=', (int) $filters['assigned_to']);
        }

        if ( ! empty($filters['priority']))
        {
            $query->where('d.priority_level', '=', (string) $filters['priority']);
        }

        if ( ! empty($filters['source']))
        {
            $query->where('d.source', '=', (string) $filters['source']);
        }

        if ( ! empty($filters['deadline_from']))
        {
            $query->where('d.deadline', '>=', (string) $filters['deadline_from']);
        }

        if ( ! empty($filters['deadline_to']))
        {
            $query->where('d.deadline', '<=', (string) $filters['deadline_to']);
        }

        if ( ! empty($filters['overdue']))
        {
            $query->where('s.kind', '=', 'open')
                ->where('d.deadline', '<', DB::expr('CURRENT_DATE'));
        }

        if ( ! empty($filters['min_amount']))
        {
            $query->where('d.amount', '>=', (float) $filters['min_amount']);
        }

        if ( ! empty($filters['max_amount']))
        {
            $query->where('d.amount', '<=', (float) $filters['max_amount']);
        }

        if ( ! empty($filters['stale']))
        {
            // «Застоявшиеся»: в работе и без записей в ленте дольше N дней.
            $days = max(1, (int) $filters['stale']);

            $query->where('s.kind', '=', 'open')
                ->where('d.id', 'NOT IN', DB::select('deal_id')->from('erp_221_activities')
                    ->where('deal_id', 'IS NOT', NULL)
                    ->where('is_deleted', '=', FALSE)
                    ->where('created_at', '>', DB::expr("CURRENT_TIMESTAMP - INTERVAL '".$days." days'")));
        }

        return $query;
    }

    /**
     * Стадия для новой сделки: из данных или первая открытая.
     *
     * @param   array  $data
     * @return  array
     */
    protected function stage_from_data(array $data)
    {
        $ref = Arr::get($data, 'stage_id', Arr::get($data, 'stage'));

        if ($ref)
        {
            $stage = Service_Erp221_Stages::instance()->find($ref);

            if ($stage)
            {
                return $stage;
            }
        }

        return Service_Erp221_Stages::instance()->default_stage();
    }

    /**
     * Вероятность сделки: явная из данных или вероятность стадии.
     *
     * @param   array  $data
     * @param   array  $stage
     * @return  integer
     */
    protected function probability(array $data, array $stage)
    {
        if (isset($data['probability']) AND $data['probability'] !== '' AND $data['probability'] !== NULL)
        {
            return max(0, min(100, (int) $data['probability']));
        }

        return (int) $stage['probability'];
    }

    /**
     * Сумма сделки: пустая строка — это ноль, отрицательных сумм не бывает.
     *
     * @param   mixed  $value
     * @return  float
     */
    protected function amount($value)
    {
        return max(0, round((float) str_replace(array(' ', ','), array('', '.'), (string) $value), 2));
    }

    /**
     * Приоритет сделки.
     *
     * @param   mixed  $value
     * @return  string
     */
    protected function priority($value)
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, array('low', 'medium', 'high', 'critical'), TRUE) ? $value : 'medium';
    }

    /**
     * Контактное лицо сделки: проверяем, что оно принадлежит этому клиенту.
     *
     * @param   integer $counterparty_id
     * @param   mixed   $contact_id
     * @return  integer|null
     * @throws  Kohana_Exception
     */
    protected function contact_id($counterparty_id, $contact_id)
    {
        $contact_id = (int) $contact_id;

        if ( ! $contact_id)
        {
            return NULL;
        }

        $contact = DB::select('id', 'counterparty_id')->from('erp_301_contacts')
            ->where('id', '=', $contact_id)->where('is_deleted', '=', 0)
            ->execute()->current();

        if ( ! $contact OR (int) $contact['counterparty_id'] !== (int) $counterparty_id)
        {
            throw new Kohana_Exception('Контакт #:id не найден у клиента #:cp', array(
                ':id' => $contact_id,
                ':cp' => (int) $counterparty_id,
            ));
        }

        return $contact_id;
    }

    /**
     * Текст для поиска: то, что менеджер вводит в строку поиска.
     *
     * @param   array   $values
     * @param   integer $counterparty_id
     * @return  string
     */
    protected function search_text(array $values, $counterparty_id)
    {
        $counterparty = DB::select('name', 'code', 'inn')->from('erp_301_counterparties')
            ->where('id', '=', (int) $counterparty_id)->execute()->current();

        $parts = array(
            Arr::get($values, 'number'),
            Arr::get($values, 'title'),
            Arr::get($values, 'description'),
            $counterparty ? $counterparty['name'] : NULL,
            $counterparty ? $counterparty['code'] : NULL,
            $counterparty ? $counterparty['inn'] : NULL,
        );

        return mb_substr(mb_strtolower(implode(' ', array_filter($parts))), 0, 1024);
    }

    /**
     * Требования стадии: что должно быть заполнено до перехода.
     *
     * @param   array   $deal
     * @param   array   $stage
     * @param   array   $data
     * @param   integer $loss_reason_id
     * @return  void
     * @throws  Kohana_Exception
     */
    protected function validate_requirements(array $deal, array $stage, array $data, $loss_reason_id)
    {
        if ($stage['requires_loss_reason'] AND ! $loss_reason_id)
        {
            throw new Kohana_Exception('Для стадии «:label» укажите причину отказа', array(':label' => $stage['label']));
        }

        if ($loss_reason_id)
        {
            // Проверяем причину до записи: иначе в сделке осталась бы ссылка на
            // несуществующую запись, а отчёт по отказам её бы не увидел.
            Service_Erp221_Stages::instance()->loss_reason($loss_reason_id);
        }

        foreach ($stage['required_fields'] as $field)
        {
            $value = array_key_exists($field, $data) ? $data[$field] : Arr::get($deal, $field);

            if ($value === NULL OR $value === '' OR $value === 0 OR $value === 0.0)
            {
                throw new Kohana_Exception('Для перехода на стадию «:label» заполните поле «:field»', array(
                    ':label' => $stage['label'],
                    ':field' => $this->field_label($field),
                ));
            }
        }
    }

    /**
     * Действия стадии: что происходит автоматически при переходе.
     *
     * @param   array   $deal
     * @param   array   $stage
     * @param   array   $data
     * @param   integer $actor
     * @return  void
     */
    protected function execute_actions(array $deal, array $stage, array $data, $actor)
    {
        $loss_reason = NULL;

        if ($stage['requires_loss_reason'] AND ! empty($data['loss_reason_id']))
        {
            $loss_reason = Service_Erp221_Stages::instance()->loss_reason($data['loss_reason_id']);
        }

        $comment = trim((string) Arr::get($data, 'comment', ''));

        $text = 'Стадия: «'.$deal['stage']['label'].'» → «'.$stage['label'].'»';

        if ($loss_reason)
        {
            $text .= '. Причина отказа: '.$loss_reason['label'];
        }

        if ($comment !== '')
        {
            $text .= '. '.$comment;
        }

        // Переход пишется в ленту всегда: состояние сделки не должно меняться
        // бесследно, даже если у стадии не настроено ни одного действия.
        Service_Erp221_Timeline::instance()->add($deal['id'], array(
            'type'    => 'stage',
            'subject' => 'Смена стадии',
            'content' => $text,
            'meta'    => array(
                'from' => $deal['stage']['code'],
                'to'   => $stage['code'],
                'loss_reason_id' => $loss_reason ? $loss_reason['id'] : NULL,
            ),
        ), $actor);

        foreach ($stage['actions'] as $action)
        {
            switch ($action)
            {
                case 'create_activity':
                    // Переход уже записан выше: действие оставлено для стадий,
                    // где оно включено явно, и второй записи не делает.
                    break;

                case 'notify_owner':
                    // Уведомление пишется в ленту сделки: внешние каналы
                    // (почта, мессенджер) подключаются отдельным модулем, и
                    // молча «отправить в никуда» здесь нельзя.
                    Service_Erp221_Timeline::instance()->add($deal['id'], array(
                        'type'    => 'system',
                        'content' => 'Нужно уведомить ответственного о переходе на стадию «'.$stage['label'].'»',
                    ), $actor);
                    break;
            }
        }
    }

    /**
     * Человеческое название обязательного поля.
     *
     * @param   string  $field
     * @return  string
     */
    protected function field_label($field)
    {
        $labels = array(
            'amount'      => 'Сумма',
            'deadline'    => 'Срок',
            'description' => 'Описание',
            'source'      => 'Источник',
            'contact_id'  => 'Контактное лицо',
            'assigned_to' => 'Ответственный',
        );

        return isset($labels[$field]) ? $labels[$field] : $field;
    }
}
