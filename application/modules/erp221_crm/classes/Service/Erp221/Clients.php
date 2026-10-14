<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Клиенты CRM (erp221_crm) — это контрагенты модуля erp301.
 *
 * Второго справочника клиентов модуль не ведёт: наименование, ИНН, адрес,
 * телефон и контакты принадлежат владельцу (`erp_301_counterparties`,
 * `erp_301_contacts`), а CRM лишь дополняет запись «профилем продаж» —
 * сегментом, отраслью, источником и уровнем ЛПР. Так менеджер и бухгалтерия
 * видят одного и того же клиента, а не две похожие карточки.
 *
 * @package    Erp221
 * @category   Services
 */
class Service_Erp221_Clients
{
    /** @var Service_Erp221_Clients|null */
    protected static $_instance;

    /**
     * @return  Service_Erp221_Clients
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
     * Поиск клиентов: по умолчанию — те, у кого есть роль «Покупатель».
     *
     * @param   array   $filters  term, status, kind, role
     * @param   integer $limit
     * @param   integer $offset
     * @return  array   ['rows' => array, 'total' => int]
     */
    public function search(array $filters = array(), $limit = 25, $offset = 0)
    {
        if ( ! array_key_exists('role', $filters))
        {
            $filters['role'] = 'customer';
        }

        $page = Service_Erp301_Counterparties::instance()->search($filters, $limit, $offset);

        $rows = array();

        foreach ($page['rows'] as $row)
        {
            $rows[] = $this->prepare($row);
        }

        return array('rows' => $rows, 'total' => $page['total']);
    }

    /**
     * Карточка клиента: контрагент, профиль продаж, сделки, контакты и лента.
     *
     * @param   integer $id
     * @return  array
     * @throws  Kohana_Exception
     */
    public function get($id)
    {
        $counterparty = Service_Erp301_Counterparties::instance()->get($id);

        $client = $this->prepare($counterparty);

        $client['profile']  = $this->profile($id);
        $client['contacts'] = $this->contacts($id);
        $client['deals']    = Service_Erp221_Deals::instance()->search(array('counterparty' => $id), 50, 0);
        $client['stats']    = $this->deal_stats($id);
        $client['timeline'] = Service_Erp221_Timeline::instance()->journal(array('counterparty' => $id), 20, 0);
        $client['insights'] = Service_Erp221_Ai::instance()->history('client', $id, 5);

        return $client;
    }

    /**
     * Создать клиента CRM: контрагент с ролью «Покупатель» плюс профиль продаж.
     *
     * @param   array   $data
     * @param   integer $actor
     * @return  array
     */
    public function create(array $data, $actor = NULL)
    {
        $data['roles'] = $this->with_customer_role(Arr::get($data, 'roles', array()));

        $counterparty = Service_Erp301_Counterparties::instance()->create($data, $actor);

        $this->save_profile($counterparty->id, $data, $actor);

        Service_Erp221_Timeline::instance()->event('client.created', $actor, array(
            'counterparty_id' => (int) $counterparty->id,
            'name'            => (string) $counterparty->name,
        ));

        return $this->get($counterparty->id);
    }

    /**
     * Изменить клиента: основные поля уходят владельцу, профиль — в CRM.
     *
     * @param   integer $id
     * @param   array   $data
     * @param   integer $actor
     * @return  array
     * @throws  Kohana_Exception
     */
    public function update($id, array $data, $actor = NULL)
    {
        $counterparty = Service_Erp301_Counterparties::instance()->get($id);

        if (array_key_exists('roles', $data))
        {
            $data['roles'] = $this->with_customer_role($data['roles']);
        }

        if (array_intersect_key($data, array_flip(array(
            'name', 'full_name', 'kind', 'status', 'country', 'city', 'address',
            'phone', 'email', 'site', 'note', 'rating', 'roles', 'inn', 'kpp', 'ogrn', 'code',
        ))))
        {
            Service_Erp301_Counterparties::instance()->update($id, $data, $actor);
        }

        if ($this->has_profile_fields($data))
        {
            $this->save_profile($id, $data, $actor);
        }

        Service_Erp221_Timeline::instance()->event('client.updated', $actor, array('counterparty_id' => (int) $id));

        return $this->get($id);
    }

    /**
     * Профиль продаж клиента (если ещё не заведён — пустой каркас).
     *
     * @param   integer $id
     * @return  array
     */
    public function profile($id)
    {
        $row = DB::select()->from('erp_221_client_profiles')
            ->where('counterparty_id', '=', (int) $id)
            ->execute()->current();

        if ( ! $row)
        {
            return array(
                'counterparty_id' => (int) $id,
                'segment'         => NULL,
                'industry'        => NULL,
                'annual_revenue'  => NULL,
                'source'          => NULL,
                'owner_id'        => NULL,
                'ai_profile_notes'=> NULL,
                'is_new'          => TRUE,
            );
        }

        $row['counterparty_id'] = (int) $row['counterparty_id'];
        $row['owner_id']        = $row['owner_id'] === NULL ? NULL : (int) $row['owner_id'];
        $row['annual_revenue']  = Service_Erp221_Cast::num($row['annual_revenue']);
        $row['is_new']          = FALSE;

        return $row;
    }

    /**
     * Сохранить профиль продаж.
     *
     * @param   integer $id
     * @param   array   $data
     * @param   integer $actor
     * @return  array
     */
    public function save_profile($id, array $data, $actor = NULL)
    {
        $values = array(
            'segment'          => Service_Erp221_Cast::text(Arr::get($data, 'segment')),
            'industry'         => Service_Erp221_Cast::text(Arr::get($data, 'industry')),
            'annual_revenue'   => Service_Erp221_Cast::num(Arr::get($data, 'annual_revenue')),
            'source'           => Service_Erp221_Cast::text(Arr::get($data, 'source')),
            'owner_id'         => (int) Arr::get($data, 'owner_id') ?: NULL,
            'ai_profile_notes' => Service_Erp221_Cast::text(Arr::get($data, 'ai_profile_notes')),
            'updated_at'       => DB::expr('CURRENT_TIMESTAMP'),
        );

        $exists = DB::select('counterparty_id')->from('erp_221_client_profiles')
            ->where('counterparty_id', '=', (int) $id)->execute()->current();

        if ($exists)
        {
            DB::update('erp_221_client_profiles')->set($values)
                ->where('counterparty_id', '=', (int) $id)->execute();
        }
        else
        {
            $values['counterparty_id'] = (int) $id;
            $values['created_at']      = DB::expr('CURRENT_TIMESTAMP');
            DB::insert('erp_221_client_profiles', array_keys($values))->values(array_values($values))->execute();
        }

        return $this->profile($id);
    }

    /**
     * Контактные лица клиента с профилем продаж (уровень ЛПР, канал связи).
     *
     * @param   integer $id
     * @return  array
     */
    public function contacts($id)
    {
        // find_all(): ORM-билдер сам по себе не итерируется, без него контактов
        // в карточке не будет вовсе.
        $contacts = Service_Erp301_Counterparties::instance()->contacts($id)->find_all();

        $profiles = array();

        foreach (DB::select()->from('erp_221_contact_profiles')->execute()->as_array() as $row)
        {
            $profiles[(int) $row['contact_id']] = $row;
        }

        $result = array();

        foreach ($contacts as $contact)
        {
            $contact_id = (int) $contact->id;
            $profile    = isset($profiles[$contact_id]) ? $profiles[$contact_id] : array();

            $result[] = array(
                'id'                 => $contact_id,
                'counterparty_id'    => (int) $contact->counterparty_id,
                'name'               => (string) $contact->name,
                'position'           => $contact->position,
                'phone'              => $contact->phone,
                'email'              => $contact->email,
                'note'               => $contact->note,
                'is_primary'         => Service_Erp221_Cast::boolean($contact->is_primary),
                'decision_maker_level' => isset($profile['decision_maker_level']) ? $profile['decision_maker_level'] : 'unknown',
                'preferred_channel'  => isset($profile['preferred_channel']) ? $profile['preferred_channel'] : 'phone',
                'telegram_id'        => isset($profile['telegram_id']) ? $profile['telegram_id'] : NULL,
                'whatsapp_phone'     => isset($profile['whatsapp_phone']) ? $profile['whatsapp_phone'] : NULL,
                'birthday'           => isset($profile['birthday']) ? $profile['birthday'] : NULL,
                'social_links'       => isset($profile['social_links']) ? Service_Erp221_Cast::json($profile['social_links']) : array(),
            );
        }

        return $result;
    }

    /**
     * Добавить или изменить контактное лицо (данные — у erp301, профиль — в CRM).
     *
     * @param   integer $client_id
     * @param   array   $data
     * @param   integer $contact_id
     * @param   integer $actor
     * @return  array
     * @throws  Kohana_Exception
     */
    public function save_contact($client_id, array $data, $contact_id = NULL, $actor = NULL)
    {
        $contact = Service_Erp301_Counterparties::instance()->save_contact($client_id, $data, $contact_id);

        $this->save_contact_profile($contact->id, $data, $actor);

        Service_Erp221_Timeline::instance()->event('contact.saved', $actor, array(
            'counterparty_id' => (int) $client_id,
            'contact_id'      => (int) $contact->id,
        ));

        return $this->get($client_id);
    }

    /**
     * Убрать контактное лицо (мягко, у владельца справочника).
     *
     * @param   integer $client_id
     * @param   integer $contact_id
     * @param   integer $actor
     * @return  array
     */
    public function delete_contact($client_id, $contact_id, $actor = NULL)
    {
        Service_Erp301_Counterparties::instance()->delete_contact($client_id, $contact_id);

        Service_Erp221_Timeline::instance()->event('contact.deleted', $actor, array(
            'counterparty_id' => (int) $client_id,
            'contact_id'      => (int) $contact_id,
        ));

        return $this->get($client_id);
    }

    /**
     * Профиль продаж контактного лица.
     *
     * @param   integer $contact_id
     * @param   array   $data
     * @param   integer $actor
     * @return  array
     */
    public function save_contact_profile($contact_id, array $data, $actor = NULL)
    {
        $level = strtolower(trim((string) Arr::get($data, 'decision_maker_level', 'unknown')));

        if ( ! in_array($level, array('lpr', 'lvpr', 'user', 'unknown'), TRUE))
        {
            $level = 'unknown';
        }

        $channel = strtolower(trim((string) Arr::get($data, 'preferred_channel', 'phone')));

        if ( ! in_array($channel, array('phone', 'email', 'telegram', 'whatsapp'), TRUE))
        {
            $channel = 'phone';
        }

        $values = array(
            'decision_maker_level' => $level,
            'preferred_channel'    => $channel,
            'telegram_id'          => Service_Erp221_Cast::text(Arr::get($data, 'telegram_id')),
            'whatsapp_phone'       => Service_Erp221_Cast::text(Arr::get($data, 'whatsapp_phone')),
            'birthday'             => Service_Erp221_Cast::text(Arr::get($data, 'birthday')),
            'social_links'         => json_encode(Service_Erp221_Cast::json(Arr::get($data, 'social_links', array())), JSON_UNESCAPED_UNICODE),
            'updated_at'           => DB::expr('CURRENT_TIMESTAMP'),
        );

        $exists = DB::select('contact_id')->from('erp_221_contact_profiles')
            ->where('contact_id', '=', (int) $contact_id)->execute()->current();

        if ($exists)
        {
            DB::update('erp_221_contact_profiles')->set($values)
                ->where('contact_id', '=', (int) $contact_id)->execute();
        }
        else
        {
            $values['contact_id'] = (int) $contact_id;
            $values['created_at'] = DB::expr('CURRENT_TIMESTAMP');
            DB::insert('erp_221_contact_profiles', array_keys($values))->values(array_values($values))->execute();
        }

        return $values;
    }

    /**
     * Сводка по клиентам: сколько их, у кого есть сделки и кто без движения.
     *
     * @return  array
     */
    public function stats()
    {
        $total = (int) DB::select(array(DB::expr('COUNT(*)'), 'total'))->from('erp_301_counterparties')
            ->where('is_deleted', '=', 0)
            ->where('roles', '@>', DB::expr("'[\"customer\"]'::jsonb"))
            ->execute()->get('total');

        $with_deals = (int) DB::select(array(DB::expr('COUNT(DISTINCT counterparty_id)'), 'total'))
            ->from('erp_221_deals')
            ->where('is_deleted', '=', FALSE)
            ->execute()->get('total');

        $new_this_month = (int) DB::select(array(DB::expr('COUNT(*)'), 'total'))->from('erp_301_counterparties')
            ->where('is_deleted', '=', 0)
            ->where('roles', '@>', DB::expr("'[\"customer\"]'::jsonb"))
            ->where('created_at', '>=', DB::expr("date_trunc('month', CURRENT_DATE)"))
            ->execute()->get('total');

        return array(
            'total'          => $total,
            'with_deals'     => $with_deals,
            'without_deals'  => max(0, $total - $with_deals),
            'new_this_month' => $new_this_month,
        );
    }

    /**
     * Сделки и деньги клиента.
     *
     * @param   integer $id
     * @return  array
     */
    public function deal_stats($id)
    {
        $row = DB::select(
                array(DB::expr("COUNT(*) FILTER (WHERE s.kind = 'open')"), 'open_count'),
                array(DB::expr("COALESCE(SUM(d.amount) FILTER (WHERE s.kind = 'open'), 0)"), 'open_amount'),
                array(DB::expr("COUNT(*) FILTER (WHERE s.kind = 'won')"), 'won_count'),
                array(DB::expr("COALESCE(SUM(d.amount) FILTER (WHERE s.kind = 'won'), 0)"), 'won_amount'),
                array(DB::expr("COUNT(*) FILTER (WHERE s.kind = 'lost')"), 'lost_count')
            )
            ->from(array('erp_221_deals', 'd'))
            ->join(array('erp_221_stages', 's'), 'LEFT')->on('s.id', '=', 'd.stage_id')
            ->where('d.counterparty_id', '=', (int) $id)
            ->where('d.is_deleted', '=', FALSE)
            ->execute()->current();

        $stats = array(
            'open_count'  => (int) $row['open_count'],
            'open_amount' => (float) $row['open_amount'],
            'won_count'   => (int) $row['won_count'],
            'won_amount'  => (float) $row['won_amount'],
            'lost_count'  => (int) $row['lost_count'],
        );

        $stats['timeline'] = Service_Erp221_Timeline::instance()->journal(array('counterparty' => (int) $id), 1, 0);

        $stats['last_activity'] = $stats['timeline']['rows']
            ? $stats['timeline']['rows'][0]['created_at']
            : NULL;

        unset($stats['timeline']);

        return $stats;
    }

    /**
     * Человеческие названия ролей контрагента.
     *
     * @return  array
     */
    public function roles()
    {
        return class_exists('Model_Erp301_Counterparty') ? Model_Erp301_Counterparty::$role_labels : array();
    }

    /**
     * Подготовить контрагента к показу в CRM.
     *
     * @param   Model_Erp301_Counterparty|array  $row
     * @return  array
     */
    public function prepare($row)
    {
        $data = is_array($row) ? $row : $row->as_array();

        unset($data['search_text']);

        $data['id']         = (int) $data['id'];
        $data['roles']      = Service_Erp221_Cast::json($data['roles']);
        $data['metadata']   = Service_Erp221_Cast::json($data['metadata']);
        $data['rating']     = (int) $data['rating'];
        $data['is_deleted'] = Service_Erp221_Cast::boolean($data['is_deleted']);
        $data['role_labels'] = array();

        $labels = $this->roles();

        foreach ($data['roles'] as $role)
        {
            $data['role_labels'][] = isset($labels[$role]) ? $labels[$role] : $role;
        }

        return $data;
    }

    /**
     * Дополнить роли контрагента ролью «Покупатель».
     *
     * @param   mixed  $roles
     * @return  array
     */
    protected function with_customer_role($roles)
    {
        if (is_string($roles))
        {
            $roles = array_filter(array_map('trim', explode(',', $roles)));
        }

        $roles = array_values(array_unique((array) $roles));

        if ( ! in_array('customer', $roles, TRUE))
        {
            $roles[] = 'customer';
        }

        return $roles;
    }

    /**
     * Есть ли во входных данных поля профиля продаж.
     *
     * @param   array  $data
     * @return  boolean
     */
    protected function has_profile_fields(array $data)
    {
        return (bool) array_intersect_key($data, array_flip(array(
            'segment', 'industry', 'annual_revenue', 'source', 'owner_id', 'ai_profile_notes',
        )));
    }
}
