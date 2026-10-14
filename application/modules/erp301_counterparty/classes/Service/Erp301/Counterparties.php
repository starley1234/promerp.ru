<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Сервис контрагентов (erp301_counterparty) — вся бизнес-логика модуля.
 *
 * Правила модуля:
 *   * данные меняются только через сервис (контроллеры и задачи — тонкие обёртки);
 *   * удаление мягкое (is_deleted), физически записи не исчезают;
 *   * каждое значимое действие публикует событие (erp301.*) и пишет запись в лог;
 *   * уникальность кода и ИНН проверяется моделью до записи в БД.
 *
 * Примеры:
 *
 *     $service = Service_Erp301_Counterparties::instance();
 *
 *     $counterparty = $service->create(array(
 *         'name'  => 'ООО «Ромашка»',
 *         'code'  => 'ROMASHKA',
 *         'roles' => array('supplier'),
 *         'inn'   => '7701234567',
 *     ), $user_id);
 *
 *     $page = $service->search(array('term' => 'ромаш', 'role' => 'supplier'), 25, 0);
 *
 * @package    Erp301
 * @category   Services
 */
class Service_Erp301_Counterparties
{
    /** @var Service_Erp301_Counterparties */
    protected static $_instance;

    /**
     * @return Service_Erp301_Counterparties
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    protected function __construct() {}

    // -------------------------------------------------------------------------
    //  Контрагенты
    // -------------------------------------------------------------------------

    /**
     * Создать контрагента.
     *
     * @param  array    $data
     * @param  int|null $user_id
     * @return Model_Erp301_Counterparty
     * @throws ORM_Validation_Exception
     */
    public function create(array $data, $user_id = NULL)
    {
        $counterparty = ORM::factory('Erp301_Counterparty');

        $values = $this->_prepare($data);
        $values['created_by'] = $user_id ? (int) $user_id : NULL;

        if (empty($values['code']))
        {
            $values['code'] = $this->next_code($values['name']);
        }

        $values['search_text'] = $this->_search_text($values);

        $counterparty->values($values)->create();

        $this->_event('erp301.counterparty.created', $counterparty, $user_id);

        return $counterparty;
    }

    /**
     * Изменить контрагента.
     *
     * @param  int      $id
     * @param  array    $data
     * @param  int|null $user_id
     * @return Model_Erp301_Counterparty
     * @throws Kohana_Exception|ORM_Validation_Exception
     */
    public function update($id, array $data, $user_id = NULL)
    {
        $counterparty = $this->get($id);

        $values = $this->_prepare($data, $counterparty);

        if (empty($values['code']))
        {
            // Код не передан — оставляем прежний, но он участвует в поиске
            unset($values['code']);
        }

        $values['search_text'] = $this->_search_text($values, $counterparty);

        $counterparty->values($values)->update();

        $this->_event('erp301.counterparty.updated', $counterparty, $user_id);

        return $counterparty;
    }

    /**
     * Мягкое удаление контрагента вместе с контактами, реквизитами и договорами.
     *
     * @param  int      $id
     * @param  int|null $user_id
     * @return bool
     * @throws Kohana_Exception
     */
    public function delete($id, $user_id = NULL)
    {
        $counterparty = $this->get($id);

        DB::query(NULL, 'BEGIN')->execute();

        $tables = array('erp_301_contacts', 'erp_301_requisites', 'erp_301_contracts');

        try
        {
            foreach ($tables as $table)
            {
                DB::update($table)
                    ->set(array('is_deleted' => 1, 'updated_at' => date('Y-m-d H:i:s')))
                    ->where('counterparty_id', '=', $counterparty->id)
                    ->execute();
            }

            $counterparty->values(array(
                'is_deleted' => 1,
                'status'     => 'archived',
            ))->update();

            DB::query(NULL, 'COMMIT')->execute();
        }
        catch (Exception $e)
        {
            DB::query(NULL, 'ROLLBACK')->execute();

            throw $e;
        }

        $this->_event('erp301.counterparty.deleted', $counterparty, $user_id);

        return TRUE;
    }

    /**
     * Запись по ID (без удалённых).
     *
     * @param  int $id
     * @return Model_Erp301_Counterparty
     * @throws Kohana_Exception
     */
    public function get($id)
    {
        $counterparty = ORM::factory('Erp301_Counterparty', (int) $id);

        if ( ! $counterparty->loaded() OR (int) $counterparty->is_deleted === 1)
        {
            throw new Kohana_Exception('Контрагент #:id не найден', array(':id' => (int) $id));
        }

        return $counterparty;
    }

    /**
     * Поиск с фильтрами и пагинацией.
     *
     * Поддерживаемые фильтры:
     *   term   — подстрока в наименовании, коде или ИНН;
     *   role   — supplier|customer|carrier|contractor;
     *   status — active|blocked|archived;
     *   kind   — legal|individual|state|foreign.
     *
     * @param  array $filters
     * @param  int   $limit
     * @param  int   $offset
     * @return array ['rows' => array, 'total' => int]
     */
    public function search(array $filters = array(), $limit = 25, $offset = 0)
    {
        $query = $this->_search_query($filters);

        $total = (int) $query->count_all();

        $rows = $this->_search_query($filters)
            ->order_by('name', 'ASC')
            ->limit((int) $limit)
            ->offset((int) $offset)
            ->find_all();

        return array(
            'rows'  => $rows,
            'total' => $total,
        );
    }

    /**
     * Подсказки для автодополнения (до 10 записей).
     *
     * @param  string $term
     * @param  string $role
     * @return array  [['id' => 1, 'label' => '...', 'hint' => 'ИНН ...'], ...]
     */
    public function suggest($term, $role = NULL)
    {
        $result = array();
        $page   = $this->search(array('term' => $term, 'role' => $role), 10, 0);

        foreach ($page['rows'] as $row)
        {
            $result[] = array(
                'id'    => (int) $row->id,
                'label' => $row->name,
                'hint'  => trim(implode(' · ', array_filter(array($row->code, $row->inn)))),
            );
        }

        return $result;
    }

    /**
     * Найти неудалённого контрагента по ИНН.
     *
     * @param  string $inn
     * @return Model_Erp301_Counterparty|null
     */
    public function find_by_inn($inn)
    {
        $counterparty = ORM::factory('Erp301_Counterparty')
            ->where('inn', '=', (string) $inn)
            ->where('is_deleted', '=', 0)
            ->find();

        return $counterparty->loaded() ? $counterparty : NULL;
    }

    /**
     * Сводка для дашборда модуля.
     *
     * @return array
     */
    public function stats()
    {
        $by_status = array();

        foreach (DB::select('status', array(DB::expr('COUNT(*)'), 'total'))
            ->from('erp_301_counterparties')
            ->where('is_deleted', '=', 0)
            ->group_by('status')
            ->execute()->as_array() as $row)
        {
            $by_status[$row['status']] = (int) $row['total'];
        }

        $by_role = array();

        foreach (array_keys(Model_Erp301_Counterparty::$role_labels) as $role)
        {
            $by_role[$role] = (int) DB::select(array(DB::expr('COUNT(*)'), 'total'))
                ->from('erp_301_counterparties')
                ->where('is_deleted', '=', 0)
                ->where('roles', '@>', DB::expr("'[\"" . $role . "\"]'::jsonb"))
                ->execute()->get('total');
        }

        return array(
            'total'          => $by_status ? array_sum($by_status) : 0,
            'by_status'      => $by_status,
            'by_role'        => $by_role,
            'active_contracts' => (int) DB::select(array(DB::expr('COUNT(*)'), 'total'))
                ->from('erp_301_contracts')
                ->where('is_deleted', '=', 0)
                ->where('status', '=', 'active')
                ->execute()->get('total'),
            'expiring'       => $this->expiring_contracts_count(30),
        );
    }

    /**
     * Действующие договоры, истекающие в ближайшие N дней (для напоминаний).
     *
     * @param  int $days
     * @return ORM
     */
    public function expiring_contracts($days = 30)
    {
        return $this->_expiring_query($days)->order_by('valid_to', 'ASC');
    }

    /**
     * Сколько договоров истекает в ближайшие N дней.
     *
     * Считается отдельным запросом без сортировки: ORM::count_all() на запросе
     * с order_by() в PostgreSQL даёт ошибку «column … must appear in the GROUP BY clause».
     *
     * @param  int $days
     * @return int
     */
    public function expiring_contracts_count($days = 30)
    {
        return (int) $this->_expiring_query($days)->count_all();
    }

    /**
     * Базовый запрос «истекающие договоры» (без сортировки).
     *
     * @param  int $days
     * @return ORM
     */
    protected function _expiring_query($days = 30)
    {
        return ORM::factory('Erp301_Contract')
            ->where('is_deleted', '=', 0)
            ->where('status', '=', 'active')
            ->where('valid_to', 'IS NOT', NULL)
            ->where('valid_to', '<=', date('Y-m-d', strtotime('+'.(int) $days.' days')))
            ->where('valid_to', '>=', date('Y-m-d'));
    }

    // -------------------------------------------------------------------------
    //  Контакты
    // -------------------------------------------------------------------------

    /**
     * Контакты контрагента.
     *
     * @param  int $counterparty_id
     * @return ORM
     */
    public function contacts($counterparty_id)
    {
        return ORM::factory('Erp301_Contact')
            ->where('counterparty_id', '=', (int) $counterparty_id)
            ->where('is_deleted', '=', 0)
            ->order_by('is_primary', 'DESC')
            ->order_by('name', 'ASC');
    }

    /**
     * Добавить или изменить контактное лицо.
     *
     * @param  int      $counterparty_id
     * @param  array    $data
     * @param  int|null $contact_id
     * @return Model_Erp301_Contact
     * @throws ORM_Validation_Exception
     */
    public function save_contact($counterparty_id, array $data, $contact_id = NULL)
    {
        $this->get($counterparty_id);

        $contact = $contact_id
            ? ORM::factory('Erp301_Contact', (int) $contact_id)
            : ORM::factory('Erp301_Contact');

        if ($contact_id AND ( ! $contact->loaded() OR (int) $contact->counterparty_id !== (int) $counterparty_id))
        {
            throw new Kohana_Exception('Контакт #:id не найден у контрагента #:cp', array(
                ':id' => (int) $contact_id,
                ':cp' => (int) $counterparty_id,
            ));
        }

        $contact->values(array(
            'counterparty_id' => (int) $counterparty_id,
            'name'            => trim((string) Arr::get($data, 'name', '')),
            'position'        => Arr::get($data, 'position'),
            'phone'           => Arr::get($data, 'phone'),
            'email'           => Arr::get($data, 'email'),
            'note'            => Arr::get($data, 'note'),
            'is_primary'      => Arr::get($data, 'is_primary') ? 1 : 0,
            'is_deleted'      => 0,
        ));

        if ($contact->is_primary)
        {
            // Основной контакт может быть только один
            DB::update('erp_301_contacts')
                ->set(array('is_primary' => 0))
                ->where('counterparty_id', '=', (int) $counterparty_id)
                ->where('id', '!=', (int) $contact->id)
                ->execute();
        }

        $contact->save();

        return $contact;
    }

    /**
     * Удалить контакт (мягко).
     *
     * @param  int $counterparty_id
     * @param  int $contact_id
     * @return bool
     */
    public function delete_contact($counterparty_id, $contact_id)
    {
        $contact = ORM::factory('Erp301_Contact', (int) $contact_id);

        if ( ! $contact->loaded() OR (int) $contact->counterparty_id !== (int) $counterparty_id)
        {
            throw new Kohana_Exception('Контакт #:id не найден у контрагента #:cp', array(
                ':id' => (int) $contact_id,
                ':cp' => (int) $counterparty_id,
            ));
        }

        $contact->values(array('is_deleted' => 1, 'is_primary' => 0))->update();

        return TRUE;
    }

    // -------------------------------------------------------------------------
    //  Реквизиты
    // -------------------------------------------------------------------------

    /**
     * Реквизиты контрагента.
     *
     * @param  int    $counterparty_id
     * @param  string $kind
     * @return ORM
     */
    public function requisites($counterparty_id, $kind = NULL)
    {
        $query = ORM::factory('Erp301_Requisite')
            ->where('counterparty_id', '=', (int) $counterparty_id)
            ->where('is_deleted', '=', 0);

        if ($kind)
        {
            $query->where('kind', '=', $kind);
        }

        return $query->order_by('is_primary', 'DESC')->order_by('id', 'ASC');
    }

    /**
     * Добавить или изменить реквизиты.
     *
     * @param  int      $counterparty_id
     * @param  array    $data
     * @param  int|null $requisite_id
     * @return Model_Erp301_Requisite
     * @throws ORM_Validation_Exception
     */
    public function save_requisite($counterparty_id, array $data, $requisite_id = NULL)
    {
        $this->get($counterparty_id);

        $requisite = $requisite_id
            ? ORM::factory('Erp301_Requisite', (int) $requisite_id)
            : ORM::factory('Erp301_Requisite');

        if ($requisite_id AND ( ! $requisite->loaded() OR (int) $requisite->counterparty_id !== (int) $counterparty_id))
        {
            throw new Kohana_Exception('Реквизиты #:id не найдены у контрагента #:cp', array(
                ':id' => (int) $requisite_id,
                ':cp' => (int) $counterparty_id,
            ));
        }

        $requisite->values(array(
            'counterparty_id' => (int) $counterparty_id,
            'kind'            => Arr::get($data, 'kind', 'bank'),
            'title'           => Arr::get($data, 'title'),
            'bank_name'       => Arr::get($data, 'bank_name'),
            'bik'             => Arr::get($data, 'bik'),
            'account'         => Arr::get($data, 'account'),
            'corr_account'    => Arr::get($data, 'corr_account'),
            'currency'        => strtoupper((string) Arr::get($data, 'currency', 'RUB')),
            'address'         => Arr::get($data, 'address'),
            'is_primary'      => Arr::get($data, 'is_primary') ? 1 : 0,
            'is_deleted'      => 0,
        ))->save();

        return $requisite;
    }

    /**
     * Реквизиты для печати: основной банковский счёт и юридический адрес.
     *
     * @param  int $counterparty_id
     * @return array ['bank' => Model|NULL, 'legal' => Model|NULL, 'lines' => array]
     */
    public function printable_requisites($counterparty_id)
    {
        $bank  = $this->requisites($counterparty_id, 'bank')->find();
        $legal = $this->requisites($counterparty_id, 'legal')->find();

        $lines = array();

        foreach (array($bank, $legal) as $requisite)
        {
            if ($requisite->loaded() AND $requisite->summary())
            {
                $lines[] = $requisite->summary();
            }
        }

        return array(
            'bank'  => $bank->loaded() ? $bank : NULL,
            'legal' => $legal->loaded() ? $legal : NULL,
            'lines' => $lines,
        );
    }

    // -------------------------------------------------------------------------
    //  Договоры
    // -------------------------------------------------------------------------

    /**
     * Список договоров с фильтрами.
     *
     * @param  array $filters  counterparty_id, status, kind, term
     * @param  int   $limit
     * @param  int   $offset
     * @return array ['rows' => array, 'total' => int]
     */
    public function contracts(array $filters = array(), $limit = 50, $offset = 0)
    {
        $build = function () use ($filters) {
            $query = ORM::factory('Erp301_Contract')->where('is_deleted', '=', 0);

            if ( ! empty($filters['counterparty_id']))
            {
                $query->where('counterparty_id', '=', (int) $filters['counterparty_id']);
            }

            if ( ! empty($filters['status']))
            {
                $query->where('status', '=', $filters['status']);
            }

            if ( ! empty($filters['kind']))
            {
                $query->where('kind', '=', $filters['kind']);
            }

            if ( ! empty($filters['term']))
            {
                $query->where('search_text', 'LIKE', '%'.$this->_lower($filters['term']).'%');
            }

            return $query;
        };

        $total = (int) $build()->count_all();
        $rows  = $build()->order_by('valid_to', 'DESC')->order_by('id', 'DESC')
            ->limit((int) $limit)->offset((int) $offset)->find_all();

        return array('rows' => $rows, 'total' => $total);
    }

    /**
     * Создать или изменить договор.
     *
     * @param  int      $counterparty_id
     * @param  array    $data
     * @param  int|null $contract_id
     * @param  int|null $user_id
     * @return Model_Erp301_Contract
     * @throws ORM_Validation_Exception
     */
    public function save_contract($counterparty_id, array $data, $contract_id = NULL, $user_id = NULL)
    {
        $this->get($counterparty_id);

        $contract = $contract_id
            ? ORM::factory('Erp301_Contract', (int) $contract_id)
            : ORM::factory('Erp301_Contract');

        if ($contract_id AND ( ! $contract->loaded() OR (int) $contract->counterparty_id !== (int) $counterparty_id))
        {
            throw new Kohana_Exception('Договор #:id не найден у контрагента #:cp', array(
                ':id' => (int) $contract_id,
                ':cp' => (int) $counterparty_id,
            ));
        }

        $contract->values(array(
            'counterparty_id' => (int) $counterparty_id,
            'number'          => trim((string) Arr::get($data, 'number', '')),
            'title'           => Arr::get($data, 'title'),
            'kind'            => Arr::get($data, 'kind', 'supply'),
            'signed_at'       => $this->_date(Arr::get($data, 'signed_at')),
            'valid_from'      => $this->_date(Arr::get($data, 'valid_from')),
            'valid_to'        => $this->_date(Arr::get($data, 'valid_to')),
            'amount'          => $this->_amount(Arr::get($data, 'amount')),
            'currency'        => strtoupper((string) Arr::get($data, 'currency', 'RUB')),
            'payment_terms'   => Arr::get($data, 'payment_terms'),
            'status'          => Arr::get($data, 'status', 'draft'),
            'file_id'         => Arr::get($data, 'file_id') ? (int) Arr::get($data, 'file_id') : NULL,
            'note'            => Arr::get($data, 'note'),
            'is_deleted'      => 0,
            'created_by'      => $contract->loaded() ? $contract->created_by : ($user_id ? (int) $user_id : NULL),
        ));

        $contract->search_text = $this->_lower(implode(' ', array_filter(array($contract->number, $contract->title))));

        $is_new = ! $contract->loaded();

        $contract->save();

        $this->_event($is_new ? 'erp301.contract.created' : 'erp301.contract.updated', $contract, $user_id);

        return $contract;
    }

    /**
     * Расторгнуть договор (статус + дата окончания).
     *
     * @param  int      $contract_id
     * @param  int|null $user_id
     * @return Model_Erp301_Contract
     * @throws Kohana_Exception
     */
    public function terminate_contract($contract_id, $user_id = NULL)
    {
        $contract = ORM::factory('Erp301_Contract', (int) $contract_id);

        if ( ! $contract->loaded() OR (int) $contract->is_deleted === 1)
        {
            throw new Kohana_Exception('Договор #:id не найден', array(':id' => (int) $contract_id));
        }

        $contract->values(array(
            'status'   => 'terminated',
            'valid_to' => date('Y-m-d'),
        ))->update();

        $this->_event('erp301.contract.terminated', $contract, $user_id);

        return $contract;
    }

    /**
     * Пересчитать статусы договоров: истёкшие «active» → «expired».
     *
     * Используется задачей `bin/console erp301:contracts:actualize`.
     *
     * @return int  сколько договоров обновлено
     */
    public function actualize_contract_statuses()
    {
        return (int) DB::update('erp_301_contracts')
            ->set(array('status' => 'expired', 'updated_at' => date('Y-m-d H:i:s')))
            ->where('status', '=', 'active')
            ->where('is_deleted', '=', 0)
            ->where('valid_to', 'IS NOT', NULL)
            ->where('valid_to', '<', date('Y-m-d'))
            ->execute();
    }

    // -------------------------------------------------------------------------
    //  Внутренние помощники
    // -------------------------------------------------------------------------

    /**
     * Следующий свободный код: CP-0001, CP-0002, …
     *
     * @param  string $name  подсказка (используется, если код сгенерировать не удалось)
     * @return string
     */
    public function next_code($name = '')
    {
        $max = DB::select(array(DB::expr("COALESCE(MAX(NULLIF(regexp_replace(code, '\\D', '', 'g'), '')::int), 0)"), 'max'))
            ->from('erp_301_counterparties')
            ->execute()
            ->get('max');

        return 'CP-'.str_pad((string) ((int) $max + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Запрос поиска (используется дважды: для COUNT и для страницы).
     *
     * @param  array $filters
     * @return ORM
     */
    protected function _search_query(array $filters)
    {
        $query = ORM::factory('Erp301_Counterparty')->where('is_deleted', '=', 0);

        if ( ! empty($filters['term']))
        {
            // search_text заполняется в PHP (mb_strtolower): ILIKE/lower() не
            // сворачивают регистр кириллицы в кластере с локалью C.
            $query->where('search_text', 'LIKE', '%'.$this->_lower($filters['term']).'%');
        }

        if ( ! empty($filters['role']))
        {
            $query->where('roles', '@>', DB::expr("'[\"" . str_replace('"', '', $filters['role']) . "\"]'::jsonb"));
        }

        if ( ! empty($filters['status']))
        {
            $query->where('status', '=', $filters['status']);
        }

        if ( ! empty($filters['kind']))
        {
            $query->where('kind', '=', $filters['kind']);
        }

        return $query;
    }

    /**
     * Приведение входных данных к полям модели.
     *
     * @param  array $data
     * @param  Model_Erp301_Counterparty|null $current
     * @return array
     */
    protected function _prepare(array $data, $current = NULL)
    {
        $values = array(
            'name'       => trim((string) Arr::get($data, 'name', '')),
            'full_name'  => Arr::get($data, 'full_name'),
            'kind'       => Arr::get($data, 'kind', 'legal'),
            // Значения по умолчанию задаём явно: ORM не читает DEFAULT из БД,
            // поэтому без этого модель после create() отличалась бы от строки в таблице.
            'status'     => Arr::get($data, 'status', $current ? $current->status : 'active'),
            'is_deleted' => 0,
            'country'   => Arr::get($data, 'country', 'RU'),
            'city'      => Arr::get($data, 'city'),
            'address'   => Arr::get($data, 'address'),
            'phone'     => Arr::get($data, 'phone'),
            'email'     => Arr::get($data, 'email'),
            'site'      => Arr::get($data, 'site'),
            'note'      => Arr::get($data, 'note'),
            'rating'    => (int) Arr::get($data, 'rating', 0),
        );

        // Пустые строки в необязательных числовых/строковых полях — это NULL
        foreach (array('inn', 'kpp', 'ogrn', 'email', 'site', 'city', 'phone') as $field)
        {
            if (isset($values[$field]) AND $values[$field] === '')
            {
                $values[$field] = NULL;
            }
        }

        if (isset($data['code']))
        {
            $values['code'] = strtoupper(trim((string) $data['code']));
        }

        if (isset($data['inn']))
        {
            $values['inn'] = preg_replace('/\D/', '', (string) $data['inn']) ?: NULL;
        }

        if (isset($data['roles']))
        {
            $roles = is_array($data['roles']) ? $data['roles'] : explode(',', (string) $data['roles']);

            $values['roles'] = json_encode(array_values(array_intersect(
                array_map('trim', $roles),
                array_keys(Model_Erp301_Counterparty::$role_labels)
            )));
        }
        elseif ($current === NULL)
        {
            $values['roles'] = json_encode(array());
        }


        return $values;
    }

    /**
     * Дата из строки формы (пустая строка → NULL).
     *
     * @param  string $value
     * @return string|null
     */
    protected function _date($value)
    {
        if ($value === NULL OR trim((string) $value) === '')
        {
            return NULL;
        }

        $time = strtotime(str_replace('/', '.', (string) $value));

        return $time ? date('Y-m-d', $time) : NULL;
    }

    /**
     * Сумма из строки формы («1 250 000,50» → 1250000.50).
     *
     * @param  string $value
     * @return float|null
     */
    protected function _amount($value)
    {
        if ($value === NULL OR trim((string) $value) === '')
        {
            return NULL;
        }

        $normalized = str_replace(array(' ', "\xc2\xa0", ','), array('', '', '.'), (string) $value);

        return is_numeric($normalized) ? (float) $normalized : NULL;
    }

    /**
     * Нижний регистр для поиска (UTF-8, не зависит от локали БД).
     *
     * @param   string  $value
     * @return  string
     */
    protected function _lower($value)
    {
        return mb_strtolower(trim((string) $value), 'UTF-8');
    }

    /**
     * Значение поля search_text: наименования, код и ИНН в нижнем регистре.
     *
     * @param   array                       $values
     * @param   Model_Erp301_Counterparty   $current
     * @return  string
     */
    protected function _search_text(array $values, $current = NULL)
    {
        $parts = array();

        foreach (array('name', 'full_name', 'code', 'inn') as $field)
        {
            if (array_key_exists($field, $values))
            {
                $parts[] = $values[$field];
            }
            elseif ($current)
            {
                $parts[] = $current->$field;
            }
        }

        return $this->_lower(implode(' ', array_filter($parts)));
    }

    /**
     * Публикация события модуля и запись в лог.
     *
     * @param  string $name
     * @param  ORM    $object
     * @param  int|null $user_id
     * @return void
     */
    protected function _event($name, $object, $user_id = NULL)
    {
        $payload = array(
            'event'   => $name,
            'id'      => (int) $object->id,
            'user_id' => $user_id ? (int) $user_id : NULL,
            'at'      => date('Y-m-d H:i:s'),
        );

        if ($object instanceof Model_Erp301_Counterparty)
        {
            $payload['code'] = $object->code;
            $payload['name'] = $object->name;
        }
        elseif ($object instanceof Model_Erp301_Contract)
        {
            $payload['counterparty_id'] = (int) $object->counterparty_id;
            $payload['number']          = $object->number;
        }

        try
        {
            Event::fire($name, $payload);
        }
        catch (Exception $e)
        {
            // Сбой подписчика не должен ломать бизнес-операцию
            Kohana::$log->add(Log::WARNING, 'erp301: подписчик события :event упал — :error', array(
                ':event' => $name,
                ':error' => $e->getMessage(),
            ));
        }

        Kohana::$log->add(Log::INFO, 'erp301: :event', array(':event' => $name.': '.json_encode($payload, JSON_UNESCAPED_UNICODE)));
    }
}
