<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Стадии воронки и причины отказа (erp221_crm).
 *
 * Воронка — это справочник, а не код: менеджер может переименовать стадию,
 * поменять вероятность, задать обязательные поля (`required_fields`) и действия
 * при переходе (`actions`) без выпуска новой версии. Требования исполняет
 * Service_Erp221_Deals при смене стадии — здесь они только хранятся.
 *
 * @package    Erp221
 * @category   Services
 */
class Service_Erp221_Stages
{
    /** @var Service_Erp221_Stages|null */
    protected static $_instance;

    /** @var array|null Кэш справочников на время запроса */
    protected $_cache;

    /**
     * @return  Service_Erp221_Stages
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
     * Стадии воронки по порядку.
     *
     * @param   boolean $active_only
     * @return  array  список подготовленных записей
     */
    public function all($active_only = TRUE)
    {
        $this->load();

        $rows = $this->_cache['stages'];

        if ( ! $active_only)
        {
            return $rows;
        }

        return array_values(array_filter($rows, function ($stage) {
            return $stage['is_active'];
        }));
    }

    /**
     * Причины отказа.
     *
     * @param   boolean $active_only
     * @return  array
     */
    public function loss_reasons($active_only = TRUE)
    {
        $this->load();

        $rows = $this->_cache['loss_reasons'];

        if ( ! $active_only)
        {
            return $rows;
        }

        return array_values(array_filter($rows, function ($reason) {
            return $reason['is_active'];
        }));
    }

    /**
     * Стадия по коду или id.
     *
     * @param   mixed  $ref
     * @return  array
     * @throws  Kohana_Exception
     */
    public function get($ref)
    {
        foreach ($this->all(FALSE) as $stage)
        {
            if ($stage['code'] === (string) $ref OR (int) $stage['id'] === (int) $ref)
            {
                return $stage;
            }
        }

        throw new Kohana_Exception('Стадия «:ref» не найдена', array(':ref' => (string) $ref));
    }

    /**
     * Стадия по коду и без исключения — нужна при импорте и смене стадии.
     *
     * @param   mixed  $ref
     * @return  array|null
     */
    public function find($ref)
    {
        try
        {
            return $this->get($ref);
        }
        catch (Exception $e)
        {
            return NULL;
        }
    }

    /**
     * Стадия по умолчанию: первая открытая по порядку.
     *
     * @return  array
     * @throws  Kohana_Exception
     */
    public function default_stage()
    {
        foreach ($this->all() as $stage)
        {
            if ($stage['kind'] === 'open')
            {
                return $stage;
            }
        }

        throw new Kohana_Exception('В воронке нет ни одной открытой стадии — заполните справочник стадий');
    }

    /**
     * Причина отказа по коду или id.
     *
     * @param   mixed  $ref
     * @return  array
     * @throws  Kohana_Exception
     */
    public function loss_reason($ref)
    {
        foreach ($this->loss_reasons(FALSE) as $reason)
        {
            if ($reason['code'] === (string) $ref OR (int) $reason['id'] === (int) $ref)
            {
                return $reason;
            }
        }

        throw new Kohana_Exception('Причина отказа «:ref» не найдена', array(':ref' => (string) $ref));
    }

    /**
     * Виды стадий: как их понимает воронка.
     *
     * @return  array
     */
    public function kinds()
    {
        return array(
            'open' => 'В работе',
            'won'  => 'Успех',
            'lost' => 'Отказ',
        );
    }

    /**
     * Добавить или обновить стадию (админка).
     *
     * @param   array   $data  code, label, kind, probability, sort, color, …
     * @param   integer $actor
     * @return  array
     * @throws  Kohana_Exception
     */
    public function save(array $data, $actor = NULL)
    {
        $code = strtolower(trim((string) Arr::get($data, 'code', '')));

        if ( ! preg_match('/^[a-z][a-z0-9_]{1,31}$/', $code))
        {
            throw new Kohana_Exception('Код стадии: латиница, цифры и подчёркивание, начиная с буквы');
        }

        $label = trim((string) Arr::get($data, 'label', ''));

        if ($label === '')
        {
            throw new Kohana_Exception('Укажите название стадии');
        }

        $kind = (string) Arr::get($data, 'kind', 'open');

        if ( ! isset($this->kinds()[$kind]))
        {
            throw new Kohana_Exception('Неизвестный вид стадии «:kind»', array(':kind' => $kind));
        }

        $values = array(
            'code'                  => $code,
            'label'                 => mb_substr($label, 0, 120),
            'kind'                  => $kind,
            'probability'           => max(0, min(100, (int) Arr::get($data, 'probability', 20))),
            'sort'                  => (int) Arr::get($data, 'sort', 100),
            'color'                 => Arr::get($data, 'color') ?: NULL,
            'requires_loss_reason'  => ! empty($data['requires_loss_reason']),
            'required_fields'       => json_encode($this->string_list(Arr::get($data, 'required_fields', array())), JSON_UNESCAPED_UNICODE),
            'actions'               => json_encode($this->string_list(Arr::get($data, 'actions', array())), JSON_UNESCAPED_UNICODE),
            'is_active'             => ! isset($data['is_active']) OR ! empty($data['is_active']),
            'updated_at'            => DB::expr('CURRENT_TIMESTAMP'),
        );

        $existing = DB::select('id')->from('erp_221_stages')->where('code', '=', $code)->execute()->current();

        if ($existing)
        {
            DB::update('erp_221_stages')->set($values)->where('id', '=', (int) $existing['id'])->execute();
        }
        else
        {
            $values['created_at'] = DB::expr('CURRENT_TIMESTAMP');
            DB::insert('erp_221_stages', array_keys($values))->values(array_values($values))->execute();
        }

        $this->_cache = NULL;

        Service_Erp221_Timeline::instance()->event('stage.saved', $actor, array('code' => $code));

        return $this->get($code);
    }

    /**
     * Убрать стадию из воронки.
     *
     * Стадия, на которой стоят сделки, не удаляется: история продаж должна
     * оставаться читаемой. Такую стадию можно только выключить.
     *
     * @param   integer $id
     * @param   integer $actor
     * @return  boolean
     * @throws  Kohana_Exception
     */
    public function delete($id, $actor = NULL)
    {
        $stage = $this->get($id);

        $deals = (int) DB::select(array(DB::expr('COUNT(*)'), 'total'))->from('erp_221_deals')
            ->where('stage_id', '=', (int) $stage['id'])
            ->where('is_deleted', '=', FALSE)
            ->execute()->get('total');

        if ($deals > 0)
        {
            throw new Kohana_Exception('На стадии «:label» стоят сделки (:count) — выключите её вместо удаления', array(
                ':label' => $stage['label'],
                ':count' => $deals,
            ));
        }

        DB::delete('erp_221_stages')->where('id', '=', (int) $stage['id'])->execute();

        $this->_cache = NULL;

        Service_Erp221_Timeline::instance()->event('stage.deleted', $actor, array('code' => $stage['code']));

        return TRUE;
    }

    /**
     * Добавить или обновить причину отказа.
     *
     * @param   array   $data
     * @param   integer $actor
     * @return  array
     * @throws  Kohana_Exception
     */
    public function save_loss_reason(array $data, $actor = NULL)
    {
        $code = strtolower(trim((string) Arr::get($data, 'code', '')));

        if ( ! preg_match('/^[a-z][a-z0-9_]{1,31}$/', $code))
        {
            throw new Kohana_Exception('Код причины: латиница, цифры и подчёркивание, начиная с буквы');
        }

        $label = trim((string) Arr::get($data, 'label', ''));

        if ($label === '')
        {
            throw new Kohana_Exception('Укажите формулировку причины отказа');
        }

        $values = array(
            'code'       => $code,
            'label'      => mb_substr($label, 0, 160),
            'sort'       => (int) Arr::get($data, 'sort', 100),
            'is_active'  => ! isset($data['is_active']) OR ! empty($data['is_active']),
        );

        $existing = DB::select('id')->from('erp_221_loss_reasons')->where('code', '=', $code)->execute()->current();

        if ($existing)
        {
            DB::update('erp_221_loss_reasons')->set($values)->where('id', '=', (int) $existing['id'])->execute();
        }
        else
        {
            $values['created_at'] = DB::expr('CURRENT_TIMESTAMP');
            DB::insert('erp_221_loss_reasons', array_keys($values))->values(array_values($values))->execute();
        }

        $this->_cache = NULL;

        Service_Erp221_Timeline::instance()->event('loss_reason.saved', $actor, array('code' => $code));

        return $this->loss_reason($code);
    }

    /**
     * Удалить причину отказа, если по ней нет закрытых сделок.
     *
     * @param   integer $id
     * @param   integer $actor
     * @return  boolean
     * @throws  Kohana_Exception
     */
    public function delete_loss_reason($id, $actor = NULL)
    {
        $reason = $this->loss_reason($id);

        $deals = (int) DB::select(array(DB::expr('COUNT(*)'), 'total'))->from('erp_221_deals')
            ->where('loss_reason_id', '=', (int) $reason['id'])
            ->execute()->get('total');

        if ($deals > 0)
        {
            throw new Kohana_Exception('Причина «:label» указана в сделках (:count) — выключите её вместо удаления', array(
                ':label' => $reason['label'],
                ':count' => $deals,
            ));
        }

        DB::delete('erp_221_loss_reasons')->where('id', '=', (int) $reason['id'])->execute();

        $this->_cache = NULL;

        Service_Erp221_Timeline::instance()->event('loss_reason.deleted', $actor, array('code' => $reason['code']));

        return TRUE;
    }

    /**
     * Подготовить стадию к показу: раскодировать JSON и булевы признаки.
     *
     * @param   array  $row
     * @return  array
     */
    public function prepare(array $row)
    {
        $row['probability']          = (int) $row['probability'];
        $row['sort']                 = (int) $row['sort'];
        $row['is_active']            = Service_Erp221_Cast::boolean($row['is_active']);
        $row['requires_loss_reason'] = Service_Erp221_Cast::boolean($row['requires_loss_reason']);
        $row['required_fields']      = Service_Erp221_Cast::json($row['required_fields']);
        $row['actions']              = Service_Erp221_Cast::json($row['actions']);

        return $row;
    }

    /**
     * Загрузить оба справочника одним заходом.
     *
     * @return  void
     */
    protected function load()
    {
        if ($this->_cache !== NULL)
        {
            return;
        }

        $this->_cache = array('stages' => array(), 'loss_reasons' => array());

        $stages = DB::select()->from('erp_221_stages')
            ->order_by('sort', 'ASC')->order_by('id', 'ASC')
            ->execute()->as_array();

        foreach ($stages as $row)
        {
            $this->_cache['stages'][] = $this->prepare($row);
        }

        $reasons = DB::select()->from('erp_221_loss_reasons')
            ->order_by('sort', 'ASC')->order_by('id', 'ASC')
            ->execute()->as_array();

        foreach ($reasons as $row)
        {
            $row['sort']      = (int) $row['sort'];
            $row['is_active'] = Service_Erp221_Cast::boolean($row['is_active']);

            $this->_cache['loss_reasons'][] = $row;
        }
    }

    /**
     * Список строк из значения формы: массив или строка через запятую.
     *
     * @param   mixed  $value
     * @return  array
     */
    protected function string_list($value)
    {
        if (is_string($value))
        {
            $value = explode(',', $value);
        }

        $result = array();

        foreach ((array) $value as $item)
        {
            $item = strtolower(trim((string) $item));

            if ($item !== '')
            {
                $result[] = $item;
            }
        }

        return array_values(array_unique($result));
    }
}
