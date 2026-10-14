<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Контроль качества (erp405_quality): партии, дефекты, карантин, решения.
 *
 * Сервис отвечает за весь путь несоответствия:
 *
 *   завести контроль → проверить → записать дефекты → изолировать партию
 *   на карантин → принять решение (переделать / вернуть / утиль / как есть)
 *   → исполнить решение движением склада → закрыть контроль
 *
 * Три правила, из которых выведено всё остальное:
 *
 *   1. **Итог и решение — разные вещи.** `result` («годная / брак») ставится по
 *      факту проверки, `decision` («что с ней делать») принимает человек, и
 *      может принять позже. Партия может стоять на карантине с итогом «брак» и
 *      без решения — это нормальное рабочее состояние, а не незаполненное поле.
 *   2. **Решение исполняется складом, а не флагом.** «В утиль» — это перемещение
 *      на склад типа `scrap` через `Service_Erp401_Stock`, а не пометка в
 *      таблице. Если склада нужного типа нет — честная ошибка
 *      `warehouse_required`, а не запись «утилизировано» без движения.
 *   3. **Чужие данные — через сервисы владельца.** Склад — `erp401`, позиция
 *      приёмки и её входной контроль — `erp403`, номенклатура — `erp401`/`erp227`.
 *      Прямых запросов в таблицы соседей нет.
 *
 * Остаток по партии нигде не хранится дважды: `quarantine_qty` — это след
 * решений сервиса, а фактический остаток всегда можно увидеть в `erp401`.
 *
 * @package    Erp405
 * @category   Services
 */
class Service_Erp405_Inspections
{
    /** @var Service_Erp405_Inspections|null */
    protected static $_instance;

    /**
     * @return  Service_Erp405_Inspections
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self;
        }

        return self::$_instance;
    }

    // =========================================================================
    //  Чтение
    // =========================================================================

    /**
     * @param   int|string $id_or_uuid
     * @return  Model_Erp405_Inspection|null
     */
    public function get($id_or_uuid)
    {
        if ($id_or_uuid === NULL OR $id_or_uuid === '' OR $id_or_uuid === 0)
        {
            return NULL;
        }

        // Ищем по uuid только то, что на uuid похоже: иначе PostgreSQL
        // отвечает «invalid input syntax for type uuid» вместо пустого ответа.
        $ref = Erp::id_or_uuid($id_or_uuid);

        if ($ref === NULL)
        {
            return NULL;
        }

        $inspection = ORM::factory('Erp405_Inspection')
            ->where($ref[0], '=', $ref[1])
            ->find();

        return $inspection->loaded() ? $inspection : NULL;
    }

    /**
     * Список контролей.
     *
     * @param   array   $filters kind, status, result, source, item_id, supplier_id, pending
     * @param   int     $limit
     * @param   int     $offset
     * @return  ORM_Result
     */
    public function search(array $filters = array(), $limit = 25, $offset = 0)
    {
        $query = ORM::factory('Erp405_Inspection');
        $query = $this->apply_filters($query, $filters);

        return $query
            ->order_by('created_at', 'DESC')
            ->order_by('id', 'DESC')
            ->limit($limit)
            ->offset($offset)
            ->find_all();
    }

    /**
     * @param   array $filters
     * @return  int
     */
    public function count(array $filters = array())
    {
        $query = $this->apply_filters(ORM::factory('Erp405_Inspection'), $filters);

        return (int) $query->count_all();
    }

    /**
     * @param   ORM     $query
     * @param   array   $filters
     * @return  ORM
     */
    public function apply_filters($query, array $filters)
    {
        if ( ! empty($filters['kind']) AND isset(Model_Erp405_Inspection::$kinds[$filters['kind']]))
        {
            $query->where('kind', '=', (string) $filters['kind']);
        }

        if ( ! empty($filters['status']) AND isset(Model_Erp405_Inspection::$statuses[$filters['status']]))
        {
            $query->where('status', '=', (string) $filters['status']);
        }

        if ( ! empty($filters['result']))
        {
            $query->where('result', '=', (string) $filters['result']);
        }

        if ( ! empty($filters['source']))
        {
            $query->where('source', '=', (string) $filters['source']);
        }

        if ( ! empty($filters['item_id']))
        {
            $query->where('item_id', '=', (int) $filters['item_id']);
        }

        if ( ! empty($filters['supplier_id']))
        {
            $query->where('supplier_id', '=', (int) $filters['supplier_id']);
        }

        if ( ! empty($filters['pending']))
        {
            $query->where('result', '=', 'failed')
                ->where('decision', 'IS', DB::expr('NULL'))
                ->where('status', 'NOT IN', array('cancelled', 'closed'));
        }

        if ( ! empty($filters['term']))
        {
            $term = '%'.trim((string) $filters['term']).'%';
            $query->where('number', 'ILIKE', $term)
                ->or_where('item_name', 'ILIKE', $term);
        }

        return $query;
    }

    /**
     * Контроли, которые ждут решения человека (итог «брак», решения нет).
     *
     * @param   int $limit
     * @return  ORM_Result
     */
    public function pending($limit = 20)
    {
        return $this->search(array('pending' => TRUE), $limit, 0);
    }

    /**
     * Дефекты контроля.
     *
     * @param   int $inspection_id
     * @return  array
     */
    public function defects($inspection_id)
    {
        return ORM::factory('Erp405_Defect')
            ->where('inspection_id', '=', (int) $inspection_id)
            ->order_by('id', 'ASC')
            ->find_all()
            ->as_array();
    }

    /**
     * Журнал решений контроля.
     *
     * @param   int $inspection_id
     * @return  array
     */
    public function decisions($inspection_id)
    {
        return ORM::factory('Erp405_Decision')
            ->where('inspection_id', '=', (int) $inspection_id)
            ->order_by('id', 'ASC')
            ->find_all()
            ->as_array();
    }

    /**
     * Причину дефекта по умолчанию подсказывает источник контроля: приёмка
     * приходит от поставщика, операция цеха — от производства.
     *
     * @param   string $source
     * @return  string
     */
    public function default_cause($source)
    {
        switch ((string) $source)
        {
            case 'erp403_receipt':
            case 'erp407_proposal':
                return 'supplier';

            case 'erp402_order':
            case 'erp408_operation':
                return 'production';

            default:
                return 'unknown';
        }
    }

    // =========================================================================
    //  Склады и доступ к соседям
    // =========================================================================

    /**
     * Склады типа `quarantine` (изоляция партий).
     *
     * @return  array  id => название
     */
    public function quarantine_warehouses()
    {
        return $this->_warehouses('quarantine');
    }

    /**
     * Склады типа `scrap` (утиль).
     *
     * @return  array
     */
    public function scrap_warehouses()
    {
        return $this->_warehouses('scrap');
    }

    /**
     * @param   string $type
     * @return  array
     */
    protected function _warehouses($type)
    {
        if ( ! class_exists('Service_Erp401_Stock'))
        {
            return array();
        }

        $found = array();

        try
        {
            foreach (Service_Erp401_Stock::instance()->warehouses(TRUE) as $warehouse)
            {
                if ((string) $warehouse->type === (string) $type)
                {
                    $found[(int) $warehouse->id] = (string) $warehouse->name;
                }
            }
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::WARNING, 'erp405: склады недоступны — :error', array(':error' => $e->getMessage()));
        }

        return $found;
    }

    /**
     * Первый склад нужного типа (по умолчанию для операций).
     *
     * @param   string $type
     * @return  int|null
     */
    public function default_warehouse($type)
    {
        foreach ($this->_warehouses($type) as $id => $name)
        {
            return (int) $id;
        }

        return NULL;
    }

    /**
     * Название позиции и единица измерения (номенклатура — erp401/erp227).
     *
     * @param   int $item_id
     * @return  array  [name, uom_code]
     */
    public function item_info($item_id)
    {
        if ( ! $item_id OR ! class_exists('Service_Erp401_Stock'))
        {
            return array(NULL, NULL);
        }

        try
        {
            $item = Service_Erp401_Stock::instance()->item((int) $item_id);
        }
        catch (Exception $e)
        {
            return array(NULL, NULL);
        }

        if ( ! $item)
        {
            return array(NULL, NULL);
        }

        return array(
            isset($item['name']) ? (string) $item['name'] : NULL,
            isset($item['uom_code']) ? (string) $item['uom_code'] : NULL,
        );
    }

    // =========================================================================
    //  Создание и правка контроля
    // =========================================================================

    /**
     * Завести контроль качества.
     *
     * @param   array   $data      kind, source, source_ref, item_id, qty, warehouse_id,
     *                             quarantine_warehouse_id, supplier_id, order_id,
     *                             operation_id, inspector_id, note
     * @param   int|null $user_id
     * @return  Model_Erp405_Inspection
     * @throws  Exception_Erp405_Quality
     */
    public function register(array $data, $user_id = NULL)
    {
        $kind = (string) Arr::get($data, 'kind', 'incoming');

        if ( ! isset(Model_Erp405_Inspection::$kinds[$kind]))
        {
            throw new Exception_Erp405_Quality('Неизвестный вид контроля: :kind', array(':kind' => $kind), 'invalid_argument');
        }

        $source = (string) Arr::get($data, 'source', 'manual');

        if ( ! isset(Model_Erp405_Inspection::$sources[$source]))
        {
            throw new Exception_Erp405_Quality('Неизвестный источник контроля: :source', array(':source' => $source), 'invalid_argument');
        }

        $qty = (float) Arr::get($data, 'qty', 0);

        if ($qty <= 0)
        {
            throw new Exception_Erp405_Quality('Партия без количества: контроль не заводится', NULL, 'invalid_argument');
        }

        $item_id = (int) Arr::get($data, 'item_id', 0);
        list($item_name, ) = $this->item_info($item_id);

        $inspection = ORM::factory('Erp405_Inspection');
        $inspection->number        = $this->number();
        $inspection->kind          = $kind;
        $inspection->source        = $source;
        $inspection->source_ref    = Arr::get($data, 'source_ref') !== NULL ? (string) $data['source_ref'] : NULL;
        $inspection->item_id       = $item_id > 0 ? $item_id : NULL;
        $inspection->item_name     = (string) Arr::get($data, 'item_name', $item_name !== NULL ? $item_name : '');
        $inspection->qty           = $qty;
        $inspection->warehouse_id  = (int) Arr::get($data, 'warehouse_id', 0) ?: NULL;
        $inspection->supplier_id   = (int) Arr::get($data, 'supplier_id', 0) ?: NULL;
        $inspection->order_id      = (int) Arr::get($data, 'order_id', 0) ?: NULL;
        $inspection->operation_id  = (int) Arr::get($data, 'operation_id', 0) ?: NULL;
        $inspection->inspector_id  = (int) Arr::get($data, 'inspector_id', 0) ?: $user_id;
        $inspection->status        = 'draft';
        $inspection->note          = (string) Arr::get($data, 'note', '');
        $inspection->created_by    = $user_id;
        $inspection->save();

        Event::fire('erp405.inspection.created', $this->payload($inspection) + array('actor_id' => $user_id));

        $this->_journal($inspection, 'create', array(), $inspection->as_array(), $user_id,
            'Контроль '.$inspection->number.' заведён');

        return $inspection;
    }

    /**
     * Начать (или продолжить) проверку: черновик становится рабочим контролем.
     *
     * @param   int     $id
     * @param   int|null $user_id
     * @return  Model_Erp405_Inspection
     * @throws  Exception_Erp405_Quality
     */
    public function start($id, $user_id = NULL)
    {
        $inspection = $this->_require($id);

        if ((string) $inspection->status === 'draft')
        {
            $inspection->status = 'registered';
            $inspection->save();

            Event::fire('erp405.inspection.started', $this->payload($inspection) + array('actor_id' => $user_id));
        }

        return $inspection;
    }

    /**
     * Изменить шапку контроля (количество партии, примечание).
     *
     * @param   int     $id
     * @param   array   $data
     * @param   int|null $user_id
     * @return  Model_Erp405_Inspection
     * @throws  Exception_Erp405_Quality
     */
    public function update($id, array $data, $user_id = NULL)
    {
        $inspection = $this->_require($id);

        if ( ! $inspection->is_editable())
        {
            throw new Exception_Erp405_Quality('Контроль :number уже завершён: правки запрещены', array(':number' => $inspection->number), 'invalid_transition');
        }

        $before = $inspection->as_array();

        foreach (array('note', 'item_name') as $field)
        {
            if (array_key_exists($field, $data))
            {
                $inspection->$field = (string) $data[$field];
            }
        }

        if (array_key_exists('qty', $data))
        {
            $qty = (float) $data['qty'];

            if ($qty <= 0)
            {
                throw new Exception_Erp405_Quality('Количество партии должно быть больше нуля', NULL, 'invalid_argument');
            }

            if ($qty < (float) $inspection->defect_qty)
            {
                throw new Exception_Erp405_Quality(
                    'В партии :qty, а дефектов уже :defects — уменьшить количество нельзя',
                    array(':qty' => $qty, ':defects' => (float) $inspection->defect_qty),
                    'qty_exceeded'
                );
            }

            $inspection->qty = $qty;
        }

        $inspection->save();

        $this->_journal($inspection, 'update', $before, $inspection->as_array(), $user_id,
            'Контроль '.$inspection->number.' отредактирован');

        return $inspection;
    }

    // =========================================================================
    //  Дефекты
    // =========================================================================

    /**
     * Записать несоответствие.
     *
     * @param   int     $inspection_id
     * @param   array   $data  defect_code_id|code, qty, severity, cause, supplier_id,
     *                         order_id, operation_id, photo_file_id, note
     * @param   int|null $user_id
     * @return  Model_Erp405_Defect
     * @throws  Exception_Erp405_Quality
     */
    public function add_defect($inspection_id, array $data, $user_id = NULL)
    {
        $inspection = $this->_require($inspection_id);

        if ( ! $inspection->is_editable())
        {
            throw new Exception_Erp405_Quality('Контроль :number завершён: дефекты не добавляются', array(':number' => $inspection->number), 'invalid_transition');
        }

        $qty = (float) Arr::get($data, 'qty', 0);

        if ($qty <= 0)
        {
            throw new Exception_Erp405_Quality('Количество дефекта должно быть больше нуля', NULL, 'invalid_argument');
        }

        $defect_code_id = (int) Arr::get($data, 'defect_code_id', 0);
        $defect_code    = NULL;

        if ($defect_code_id > 0)
        {
            $defect_code = Service_Erp405_DefectCodes::instance()->find($defect_code_id);

            if ( ! $defect_code)
            {
                throw new Exception_Erp405_Quality('Код дефекта не найден', array(':id' => $defect_code_id), 'not_found');
            }
        }

        $cause = (string) Arr::get($data, 'cause', $this->default_cause($inspection->source));

        if ( ! isset(Model_Erp405_Defect::$causes[$cause]))
        {
            $cause = 'unknown';
        }

        $severity = (int) Arr::get($data, 'severity', $defect_code ? (int) $defect_code->severity : 2);

        if ($severity < 1 OR $severity > 3)
        {
            $severity = 2;
        }

        $defect = ORM::factory('Erp405_Defect');
        $defect->inspection_id  = (int) $inspection->id;
        $defect->defect_code_id = $defect_code ? (int) $defect_code->id : NULL;
        $defect->code           = $defect_code ? (string) $defect_code->code : (string) Arr::get($data, 'code', '');
        $defect->qty            = $qty;
        $defect->severity       = $severity;
        $defect->cause          = $cause;
        $defect->supplier_id    = (int) Arr::get($data, 'supplier_id', $inspection->supplier_id) ?: NULL;
        $defect->order_id       = (int) Arr::get($data, 'order_id', $inspection->order_id) ?: NULL;
        $defect->operation_id   = (int) Arr::get($data, 'operation_id', $inspection->operation_id) ?: NULL;
        $defect->item_id        = (int) $inspection->item_id ?: NULL;
        $defect->photo_file_id  = (int) Arr::get($data, 'photo_file_id', 0) ?: NULL;
        $defect->note           = (string) Arr::get($data, 'note', '');
        $defect->created_by     = $user_id;

        if ((float) $inspection->defect_qty + $qty > (float) $inspection->qty)
        {
            throw new Exception_Erp405_Quality(
                'Дефектов :defects из партии :qty — больше, чем есть',
                array(':defects' => (float) $inspection->defect_qty + $qty, ':qty' => (float) $inspection->qty),
                'qty_exceeded'
            );
        }

        $defect->save();

        $this->_recount($inspection, $user_id);

        $inspection->inspector_id = $inspection->inspector_id ? $inspection->inspector_id : $user_id;
        $inspection->save();

        Event::fire('erp405.defect.found', $this->payload($inspection) + array(
            'defect_id' => (int) $defect->id,
            'code'      => (string) $defect->code,
            'qty'       => (float) $defect->qty,
            'cause'     => (string) $defect->cause,
            'actor_id'  => $user_id,
        ));

        $this->_journal($inspection, 'update', array('defects' => (float) $inspection->defect_qty - $qty),
            array('defects' => (float) $inspection->defect_qty, 'code' => (string) $defect->code, 'qty' => $qty), $user_id,
            'Дефект: '.($defect->code !== '' ? $defect->code : 'без кода').' × '.$this->_num($qty));

        return $defect;
    }

    /**
     * Убрать запись о дефекте (ошиблись при вводе).
     *
     * @param   int     $defect_id
     * @param   int|null $user_id
     * @return  Model_Erp405_Inspection
     * @throws  Exception_Erp405_Quality
     */
    public function remove_defect($defect_id, $user_id = NULL)
    {
        $defect = ORM::factory('Erp405_Defect', (int) $defect_id);

        if ( ! $defect->loaded())
        {
            throw new Exception_Erp405_Quality('Запись о дефекте не найдена', array(':id' => $defect_id), 'not_found');
        }

        $inspection = $this->_require((int) $defect->inspection_id);

        if ( ! $inspection->is_editable())
        {
            throw new Exception_Erp405_Quality('Контроль :number завершён: дефекты не удаляются', array(':number' => $inspection->number), 'invalid_transition');
        }

        $defect->delete();

        $this->_recount($inspection, $user_id);

        return $inspection;
    }

    /**
     * Пересчитать итог контроля по записанным дефектам.
     *
     * Итог «брак» ставится самим фактом дефекта: контролёр не должен помнить о
     * второй кнопке. «Годная» — действие осознанное (`mark_passed()`), потому
     * что отсутствие дефектов ещё не значит, что проверка проведена.
     *
     * @param   Model_Erp405_Inspection $inspection
     * @param   int|null                $user_id
     * @return  Model_Erp405_Inspection
     */
    protected function _recount(Model_Erp405_Inspection $inspection, $user_id = NULL)
    {
        $row = DB::select(array(DB::expr('COALESCE(SUM(qty), 0)'), 'total'))
            ->from('erp_405_defects')
            ->where('inspection_id', '=', (int) $inspection->id)
            ->execute()
            ->current();

        $inspection->defect_qty = (float) $row['total'];
        $inspection->result     = ((float) $inspection->defect_qty > 0) ? 'failed' : NULL;
        $inspection->save();

        return $inspection;
    }

    // =========================================================================
    //  Итог, карантин и решения
    // =========================================================================

    /**
     * Партия признана годной.
     *
     * @param   int     $id
     * @param   int|null $user_id
     * @return  Model_Erp405_Inspection
     * @throws  Exception_Erp405_Quality
     */
    public function mark_passed($id, $user_id = NULL)
    {
        $inspection = $this->_require($id);

        if (in_array((string) $inspection->status, array('closed', 'cancelled'), TRUE))
        {
            throw new Exception_Erp405_Quality('Контроль :number уже завершён', array(':number' => $inspection->number), 'invalid_transition');
        }

        if ((float) $inspection->defect_qty > 0)
        {
            throw new Exception_Erp405_Quality(
                'В партии :defects брака: годной признать нельзя, нужно решение',
                array(':defects' => (float) $inspection->defect_qty),
                'invalid_transition'
            );
        }

        $before = $inspection->as_array();

        $inspection->result       = 'passed';
        $inspection->status       = 'decided';
        $inspection->inspected_at = date('Y-m-d H:i:s');
        $inspection->decided_by   = $user_id;
        $inspection->decided_at   = date('Y-m-d H:i:s');
        $inspection->save();

        $this->_sync_receipt_quality($inspection, 'passed');

        Event::fire('erp405.inspection.passed', $this->payload($inspection) + array('actor_id' => $user_id));

        $this->_journal($inspection, 'update', $before, $inspection->as_array(), $user_id,
            'Контроль '.$inspection->number.': партия годная');

        return $inspection;
    }

    /**
     * Изолировать партию на карантинный склад.
     *
     * @param   int      $id
     * @param   int|null $warehouse_id склад типа `quarantine` (по умолчанию первый)
     * @param   float|null $qty        сколько изолировать (по умолчанию — бракованное количество)
     * @param   int|null $user_id
     * @return  Model_Erp405_Inspection
     * @throws  Exception_Erp405_Quality
     */
    public function quarantine($id, $warehouse_id = NULL, $qty = NULL, $user_id = NULL)
    {
        $inspection = $this->_require($id);

        if ((float) $inspection->defect_qty <= 0)
        {
            throw new Exception_Erp405_Quality('Изолировать нечего: дефектов у партии нет', NULL, 'invalid_transition');
        }

        $warehouse_id = $warehouse_id ? (int) $warehouse_id : $this->default_warehouse('quarantine');

        if ( ! $warehouse_id OR ! isset($this->quarantine_warehouses()[$warehouse_id]))
        {
            throw new Exception_Erp405_Quality(
                'Нет склада карантина: заведите склад типа «quarantine» в модуле склада',
                NULL,
                'warehouse_required'
            );
        }

        $qty = ($qty === NULL) ? (float) $inspection->defect_qty : (float) $qty;

        if ($qty <= 0)
        {
            throw new Exception_Erp405_Quality('Количество для карантина должно быть больше нуля', NULL, 'invalid_argument');
        }

        $already  = (float) $inspection->quarantine_qty;
        $capacity = (float) $inspection->defect_qty - $already;

        if ($qty > $capacity + 0.000001)
        {
            throw new Exception_Erp405_Quality(
                'На карантин уже отправлено :already из :defects — остаток :left',
                array(':already' => $this->_num($already), ':defects' => $this->_num((float) $inspection->defect_qty), ':left' => $this->_num(max(0, $capacity))),
                'qty_exceeded'
            );
        }

        $movement = $this->_move_to($inspection, (int) $inspection->warehouse_id, $warehouse_id, $qty,
            'Карантин '.$inspection->number, $user_id, 'erp405_quarantine');

        $inspection->quarantine_warehouse_id = $warehouse_id;
        $inspection->quarantine_qty          = round($already + $qty, 6);
        $inspection->inspected_at            = $inspection->inspected_at ? $inspection->inspected_at : date('Y-m-d H:i:s');
        $inspection->save();

        $this->_record_decision($inspection, 'quarantine', $qty, (int) $inspection->warehouse_id, $warehouse_id,
            $movement, 'Партия изолирована на карантин', $user_id);

        Event::fire('erp405.inspection.quarantined', $this->payload($inspection) + array(
            'qty'          => $qty,
            'warehouse_id' => $warehouse_id,
            'actor_id'     => $user_id,
        ));

        return $inspection;
    }

    /**
     * Выпустить партию из карантина обратно в оборот.
     *
     * @param   int      $id
     * @param   int|null $warehouse_id куда вернуть (по умолчанию — исходный склад)
     * @param   float|null $qty
     * @param   int|null $user_id
     * @return  Model_Erp405_Inspection
     * @throws  Exception_Erp405_Quality
     */
    public function release($id, $warehouse_id = NULL, $qty = NULL, $user_id = NULL)
    {
        $inspection = $this->_require($id);

        if ( ! $inspection->is_quarantined())
        {
            throw new Exception_Erp405_Quality('Партия не на карантине: выпускать нечего', NULL, 'not_quarantined');
        }

        $from = (int) $inspection->quarantine_warehouse_id;
        $to   = $warehouse_id ? (int) $warehouse_id : (int) $inspection->warehouse_id;

        if ( ! $to)
        {
            throw new Exception_Erp405_Quality('Не указан склад возврата: выпустить партию некуда', NULL, 'warehouse_required');
        }

        $qty = ($qty === NULL) ? (float) $inspection->quarantine_qty : (float) $qty;

        if ($qty <= 0 OR $qty > (float) $inspection->quarantine_qty + 0.000001)
        {
            throw new Exception_Erp405_Quality(
                'На карантине :have — выпустить просят :need',
                array(':have' => $this->_num((float) $inspection->quarantine_qty), ':need' => $this->_num($qty)),
                'qty_exceeded'
            );
        }

        $movement = $this->_move_to($inspection, $from, $to, $qty,
            'Выпуск из карантина '.$inspection->number, $user_id, 'erp405_release');

        $inspection->quarantine_qty = round((float) $inspection->quarantine_qty - $qty, 6);

        if ((float) $inspection->quarantine_qty <= 0)
        {
            $inspection->quarantine_qty          = 0;
            $inspection->quarantine_warehouse_id = NULL;
        }

        $inspection->save();

        $this->_record_decision($inspection, 'release', $qty, $from, $to, $movement,
            'Партия выпущена из карантина', $user_id);

        Event::fire('erp405.inspection.released', $this->payload($inspection) + array(
            'qty'          => $qty,
            'warehouse_id' => $to,
            'actor_id'     => $user_id,
        ));

        return $inspection;
    }

    /**
     * Принять решение по браку и исполнить его складом.
     *
     * @param   int      $id
     * @param   string   $decision use_as_is|rework|return|scrap|sort
     * @param   string|null $comment
     * @param   int|null $user_id
     * @param   array    $options   warehouse_id — куда именно списать утиль
     *                              (по умолчанию первый склад типа `scrap`)
     * @return  Model_Erp405_Inspection
     * @throws  Exception_Erp405_Quality
     */
    public function decide($id, $decision, $comment = NULL, $user_id = NULL, array $options = array())
    {
        $inspection = $this->_require($id);

        if ( ! isset(Model_Erp405_Inspection::$decisions[$decision]))
        {
            throw new Exception_Erp405_Quality('Неизвестное решение: :decision', array(':decision' => $decision), 'invalid_argument');
        }

        if ((string) $inspection->result !== 'failed')
        {
            throw new Exception_Erp405_Quality(
                'Решения по :number нет: партия без брака — её либо принимают годной, либо снимают контроль',
                array(':number' => $inspection->number),
                'invalid_transition'
            );
        }

        if (in_array((string) $inspection->status, array('closed', 'cancelled'), TRUE))
        {
            throw new Exception_Erp405_Quality('Контроль :number уже завершён', array(':number' => $inspection->number), 'invalid_transition');
        }

        $before = $inspection->as_array();
        $qty    = (float) $inspection->defect_qty;
        $movement_ref = NULL;

        switch ($decision)
        {
            case 'scrap':
                $scrap_id = ! empty($options['warehouse_id'])
                    ? (int) $options['warehouse_id']
                    : $this->default_warehouse('scrap');

                if ( ! $scrap_id)
                {
                    throw new Exception_Erp405_Quality(
                        'Нет склада утиля: заведите склад типа «scrap» в модуле склада',
                        NULL,
                        'warehouse_required'
                    );
                }

                $movement = $this->scrap($inspection, $scrap_id, $qty, $user_id);
                $movement_ref = $movement ? (string) $movement->id : NULL;
                break;

            case 'return':
                // Брак уходит поставщику: если партия уже на нашем складе —
                // изолируем её, чтобы её не выдали в производство.
                if ((int) $inspection->warehouse_id AND ! $inspection->is_quarantined())
                {
                    try
                    {
                        $this->quarantine($id, NULL, NULL, $user_id);
                        $inspection = $this->_require($id);
                    }
                    catch (Exception_Erp405_Quality $e)
                    {
                        $this->_warn('erp405: карантин недоступен при возврате поставщику — :error', $e->getMessage());
                    }
                }

                Event::fire('erp405.decision.return', $this->payload($inspection) + array(
                    'qty'        => $qty,
                    'supplier_id'=> (int) $inspection->supplier_id ?: NULL,
                    'source'     => (string) $inspection->source,
                    'source_ref' => (string) $inspection->source_ref,
                    'actor_id'   => $user_id,
                ));
                break;

            case 'rework':
                Event::fire('erp405.decision.rework', $this->payload($inspection) + array(
                    'qty'      => $qty,
                    'order_id' => (int) $inspection->order_id ?: NULL,
                    'actor_id' => $user_id,
                ));
                break;

            case 'use_as_is':
            case 'sort':
            default:
                Event::fire('erp405.decision.accepted', $this->payload($inspection) + array(
                    'decision' => (string) $decision,
                    'qty'      => $qty,
                    'actor_id' => $user_id,
                ));
                break;
        }

        $inspection->decision         = (string) $decision;
        $inspection->decision_comment = ($comment !== NULL) ? (string) $comment : $inspection->decision_comment;
        $inspection->decided_by       = $user_id;
        $inspection->decided_at       = date('Y-m-d H:i:s');
        $inspection->inspected_at     = $inspection->inspected_at ? $inspection->inspected_at : date('Y-m-d H:i:s');
        $inspection->status           = 'decided';
        $inspection->save();

        $this->_record_decision($inspection, (string) $decision, $qty, (int) $inspection->warehouse_id, NULL,
            $movement_ref, ($comment !== NULL) ? (string) $comment : NULL, $user_id);

        $this->_sync_receipt_quality($inspection, 'failed');

        Event::fire('erp405.inspection.decided', $this->payload($inspection) + array(
            'decision' => (string) $decision,
            'qty'      => $qty,
            'actor_id' => $user_id,
        ));

        $this->_journal($inspection, 'update', $before, $inspection->as_array(), $user_id,
            'Решение по '.$inspection->number.': '.$inspection->decision_label());

        return $inspection;
    }

    /**
     * Списать брак в утиль: перемещение на склад типа `scrap`.
     *
     * Отдельный метод (а не ветка в `decide()`), потому что утилизировать
     * партию можно и без решения по всей сделке: например, часть годной партии
     * повреждена при разгрузке.
     *
     * @param   int      $id
     * @param   int|null $warehouse_id склад утиля (по умолчанию первый)
     * @param   float|null $qty
     * @param   int|null $user_id
     * @return  Model_Erp401_Movement|null
     * @throws  Exception_Erp405_Quality
     */
    public function scrap($id, $warehouse_id = NULL, $qty = NULL, $user_id = NULL)
    {
        $inspection = ($id instanceof Model_Erp405_Inspection) ? $id : $this->_require($id);

        $scrap_id = $warehouse_id ? (int) $warehouse_id : $this->default_warehouse('scrap');

        if ( ! $scrap_id OR ! isset($this->scrap_warehouses()[$scrap_id]))
        {
            throw new Exception_Erp405_Quality(
                'Нет склада утиля: заведите склад типа «scrap» в модуле склада',
                NULL,
                'warehouse_required'
            );
        }

        $qty = ($qty === NULL) ? (float) $inspection->defect_qty : (float) $qty;

        if ($qty <= 0)
        {
            throw new Exception_Erp405_Quality('Списывать нечего: количество брака ноль', NULL, 'invalid_argument');
        }

        $movement = $this->_move_to($inspection, (int) $inspection->warehouse_id, $scrap_id, $qty,
            'Утиль '.$inspection->number, $user_id, 'erp405_scrap');

        Event::fire('erp405.scrap.written_off', $this->payload($inspection) + array(
            'qty'          => $qty,
            'warehouse_id' => $scrap_id,
            'movement_id'  => $movement ? (int) $movement->id : NULL,
            'actor_id'     => $user_id,
        ));

        return $movement;
    }

    /**
     * Закрыть контроль (решение исполнено, документ больше не правится).
     *
     * @param   int     $id
     * @param   int|null $user_id
     * @return  Model_Erp405_Inspection
     * @throws  Exception_Erp405_Quality
     */
    public function close($id, $user_id = NULL)
    {
        $inspection = $this->_require($id);

        if ((string) $inspection->status === 'closed')
        {
            return $inspection;
        }

        if (in_array((string) $inspection->status, array('draft', 'cancelled'), TRUE))
        {
            throw new Exception_Erp405_Quality(
                'Контроль :number не проведён: закрывать нечего',
                array(':number' => $inspection->number),
                'invalid_transition'
            );
        }

        $before = $inspection->as_array();

        if ($inspection->is_quarantined())
        {
            throw new Exception_Erp405_Quality(
                'Партия :number на карантине (:qty): сначала решение и выпуск',
                array(':number' => $inspection->number, ':qty' => $this->_num((float) $inspection->quarantine_qty)),
                'invalid_transition'
            );
        }

        $inspection->status = 'closed';
        $inspection->save();

        Event::fire('erp405.inspection.closed', $this->payload($inspection) + array('actor_id' => $user_id));

        $this->_journal($inspection, 'update', $before, $inspection->as_array(), $user_id,
            'Контроль '.$inspection->number.' закрыт');

        return $inspection;
    }

    /**
     * Отменить контроль (завели по ошибке).
     *
     * @param   int     $id
     * @param   string|null $reason
     * @param   int|null $user_id
     * @return  Model_Erp405_Inspection
     * @throws  Exception_Erp405_Quality
     */
    public function cancel($id, $reason = NULL, $user_id = NULL)
    {
        $inspection = $this->_require($id);

        if ($inspection->is_quarantined())
        {
            throw new Exception_Erp405_Quality(
                'Партия :number на карантине: сначала выпуск, потом отмена',
                array(':number' => $inspection->number),
                'invalid_transition'
            );
        }

        $before = $inspection->as_array();

        $inspection->status           = 'cancelled';
        $inspection->decision_comment = ($reason !== NULL) ? (string) $reason : $inspection->decision_comment;
        $inspection->save();

        $this->_journal($inspection, 'update', $before, $inspection->as_array(), $user_id,
            'Контроль '.$inspection->number.' отменён'.($reason ? ': '.$reason : ''));

        return $inspection;
    }

    /**
     * Завести контроль по позиции приёмки закупки (erp403).
     *
     * Это входной контроль: позиция приёмки уже знает, что пришло, сколько и от
     * какого поставщика; модуль качества берёт партию под свою ответственность.
     * Повторный вызов для той же строки не создаёт второй контроль — возвращает
     * существующий (идемпотентность на уровне данных, а не ключа запроса).
     *
     * @param   int     $line_id
     * @param   int|null $user_id
     * @return  Model_Erp405_Inspection
     * @throws  Exception_Erp405_Quality
     */
    public function from_receipt_line($line_id, $user_id = NULL)
    {
        if ( ! class_exists('Service_Erp403_Receipts'))
        {
            throw new Exception_Erp405_Quality('Модуль закупок недоступен', NULL, 'dependency');
        }

        $line = Service_Erp403_Receipts::instance()->line($line_id);

        if ( ! $line)
        {
            throw new Exception_Erp405_Quality('Позиция приёмки не найдена', array(':id' => $line_id), 'not_found');
        }

        $existing = ORM::factory('Erp405_Inspection')
            ->where('source', '=', 'erp403_receipt')
            ->where('source_ref', '=', (string) $line_id)
            ->find();

        if ($existing->loaded())
        {
            return $existing;
        }

        $receipt = ORM::factory('Erp403_Receipt', (int) $line->receipt_id);

        if ( ! $receipt->loaded())
        {
            throw new Exception_Erp405_Quality('Приёмка не найдена', array(':id' => (int) $line->receipt_id), 'not_found');
        }

        $qty = (float) $line->qty;

        if ($qty <= 0)
        {
            throw new Exception_Erp405_Quality('В позиции приёмки нулевое количество: контроль не нужен', NULL, 'invalid_argument');
        }

        return $this->register(array(
            'kind'          => 'incoming',
            'source'        => 'erp403_receipt',
            'source_ref'    => (string) $line_id,
            'item_id'       => (int) $line->item_id,
            'item_name'     => (string) $line->name,
            'qty'           => $qty,
            'warehouse_id'  => (int) $receipt->warehouse_id,
            'supplier_id'   => (int) $receipt->supplier_id,
            'note'          => 'Входной контроль приёмки '.$receipt->number,
        ), $user_id);
    }

    /**
     * Контроли по позиции приёмки (для карточки приёмки в erp403).
     *
     * @param   int $line_id
     * @return  array
     */
    public function for_receipt_line($line_id)
    {
        return ORM::factory('Erp405_Inspection')
            ->where('source', '=', 'erp403_receipt')
            ->where('source_ref', '=', (string) $line_id)
            ->order_by('id', 'DESC')
            ->find_all()
            ->as_array();
    }

    // =========================================================================
    //  Служебное
    // =========================================================================

    /**
     * Номер контроля: ПК-ГГГГ-НННННН.
     *
     * @return  string
     */
    public function number()
    {
        $row = DB::select(array(DB::expr("nextval('erp_405_inspection_number_seq')"), 'number'))
            ->execute()
            ->current();

        return 'ПК-'.date('Y').'-'.str_pad((int) $row['number'], 6, '0', STR_PAD_LEFT);
    }

    /**
     * Полезная нагрузка события: по ней журнал и соседи узнают партию.
     *
     * @param   Model_Erp405_Inspection $inspection
     * @return  array
     */
    public function payload(Model_Erp405_Inspection $inspection)
    {
        return array(
            'id'           => (int) $inspection->id,
            'uuid'         => (string) $inspection->uuid,
            'number'       => (string) $inspection->number,
            'title'        => (string) $inspection->number,
            'kind'         => (string) $inspection->kind,
            'source'       => (string) $inspection->source,
            'source_ref'   => (string) $inspection->source_ref,
            'item_id'      => (int) $inspection->item_id ?: NULL,
            'item_name'    => (string) $inspection->item_name,
            'qty'          => (float) $inspection->qty,
            'defect_qty'   => (float) $inspection->defect_qty,
            'result'       => (string) $inspection->result,
            'decision'     => (string) $inspection->decision,
            'status'       => (string) $inspection->status,
            'warehouse_id' => (int) $inspection->warehouse_id ?: NULL,
            'supplier_id'  => (int) $inspection->supplier_id ?: NULL,
        );
    }

    /**
     * Перенести количество между складами через складской сервис.
     *
     * @param   Model_Erp405_Inspection $inspection
     * @param   int     $from
     * @param   int     $to
     * @param   float   $qty
     * @param   string  $note
     * @param   int|null $user_id
     * @param   string  $doc_type
     * @return  Model_Erp401_Movement
     * @throws  Exception_Erp405_Quality
     */
    protected function _move_to(Model_Erp405_Inspection $inspection, $from, $to, $qty, $note, $user_id = NULL, $doc_type = 'erp405')
    {
        if ( ! class_exists('Service_Erp401_Stock'))
        {
            throw new Exception_Erp405_Quality('Модуль склада недоступен: движение невозможно', NULL, 'dependency');
        }

        if ( ! $from OR ! $to)
        {
            throw new Exception_Erp405_Quality(
                'Не известен склад партии: движение по :number невозможно',
                array(':number' => $inspection->number),
                'warehouse_required'
            );
        }

        if ( ! (int) $inspection->item_id)
        {
            throw new Exception_Erp405_Quality(
                'У партии :number нет номенклатуры: складское движение невозможно',
                array(':number' => $inspection->number),
                'invalid_argument'
            );
        }

        try
        {
            return Service_Erp401_Stock::instance()->transfer($from, $to, (int) $inspection->item_id, $qty, array(
                'doc_type' => $doc_type,
                'doc_id'   => (int) $inspection->id,
                'note'     => $note,
            ));
        }
        catch (Exception $e)
        {
            throw new Exception_Erp405_Quality(
                'Склад не смог выполнить движение по :number: :error',
                array(':number' => $inspection->number, ':error' => $e->getMessage()),
                'dependency',
                0,
                $e
            );
        }
    }

    /**
     * Запись в журнал решений контроля.
     *
     * @param   Model_Erp405_Inspection $inspection
     * @param   string  $decision
     * @param   float   $qty
     * @param   int|null $from
     * @param   int|null $to
     * @param   Model_Erp401_Movement|string|null $movement
     * @param   string|null $comment
     * @param   int|null $user_id
     * @return  Model_Erp405_Decision
     */
    protected function _record_decision(Model_Erp405_Inspection $inspection, $decision, $qty, $from = NULL, $to = NULL, $movement = NULL, $comment = NULL, $user_id = NULL)
    {
        $record = ORM::factory('Erp405_Decision');
        $record->inspection_id     = (int) $inspection->id;
        $record->decision          = (string) $decision;
        $record->qty               = (float) $qty;
        $record->from_warehouse_id = ($from !== NULL AND (int) $from > 0) ? (int) $from : NULL;
        $record->to_warehouse_id   = ($to !== NULL AND (int) $to > 0) ? (int) $to : NULL;
        // Движение передают либо моделью (карантин и выпуск), либо уже готовой
        // ссылкой (утиль внутри decide(), где движение выполняется отдельно).
        if ($movement instanceof Model_Erp401_Movement)
        {
            $record->movement_ref = (string) $movement->id;
        }
        elseif (is_scalar($movement) AND (string) $movement !== '')
        {
            $record->movement_ref = (string) $movement;
        }
        else
        {
            $record->movement_ref = NULL;
        }
        $record->comment           = $comment;
        $record->decided_by        = $user_id;
        $record->save();

        return $record;
    }

    /**
     * Сообщить закупке итог входного контроля позиции приёмки.
     *
     * @param   Model_Erp405_Inspection $inspection
     * @param   string  $status passed|failed
     * @return  void
     */
    protected function _sync_receipt_quality(Model_Erp405_Inspection $inspection, $status)
    {
        if ((string) $inspection->source !== 'erp403_receipt' OR ! class_exists('Service_Erp403_Receipts'))
        {
            return;
        }

        try
        {
            Service_Erp403_Receipts::instance()->set_quality(
                (int) $inspection->source_ref,
                $status,
                'Контроль '.$inspection->number
            );
        }
        catch (Exception $e)
        {
            $this->_warn('erp405: не удалось обновить входной контроль приёмки — :error', $e->getMessage());
        }
    }

    /**
     * @param   int|string $id
     * @return  Model_Erp405_Inspection
     * @throws  Exception_Erp405_Quality
     */
    protected function _require($id)
    {
        $inspection = $this->get($id);

        if ( ! $inspection)
        {
            throw new Exception_Erp405_Quality('Контроль качества не найден', array(':id' => $id), 'not_found');
        }

        return $inspection;
    }

    /**
     * @param   Model_Erp405_Inspection $inspection
     * @param   string  $action
     * @param   array   $before
     * @param   array   $after
     * @param   int|null $user_id
     * @param   string|null $message
     * @return  void
     */
    protected function _journal(Model_Erp405_Inspection $inspection, $action, array $before, array $after, $user_id = NULL, $message = NULL)
    {
        if ( ! class_exists('Service_Erp119_Journal'))
        {
            return;
        }

        try
        {
            Service_Erp119_Journal::instance()->change('inspection', (string) $inspection->uuid, $action, $before, $after, array(
                'module'       => 'erp405',
                'entity_label' => (string) $inspection->number,
                'message'      => $message,
                'actor_id'     => $user_id,
            ));
        }
        catch (Exception $e)
        {
            $this->_warn('erp405: журнал действий недоступен — :error', $e->getMessage());
        }
    }

    /**
     * @param   string $message
     * @param   string $error
     * @return  void
     */
    protected function _warn($message, $error)
    {
        Kohana::$log->add(Log::WARNING, $message, array(':error' => $error));
    }

    /**
     * Число для сообщений: без хвостовых нулей.
     *
     * @param   float $value
     * @return  string
     */
    protected function _num($value)
    {
        $value = (float) $value;

        return (abs($value - round($value)) < 0.000001) ? (string) (int) round($value) : rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }
}
