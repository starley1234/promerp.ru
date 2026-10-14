<?php defined('SYSPATH') or die('No direct script access.');

/**
 * JSON API качества (erp405_quality): `/quality/api/<action>(/<id>)`.
 *
 * Повторяет экраны один в один, чтобы агент и внешний воркер видели то же, что
 * контролёр: сводку, контроли, дефекты, коды дефектов, статистику по причинам и
 * поставщикам, очередь решений и карантин.
 *
 * Права разведены по шагам процесса, а не «на всё сразу»:
 *   * чтение — `erp405.view`;
 *   * завести контроль, записать дефекты — `erp405.inspect`;
 *   * решение по браку, карантин, выпуск, утиль — `erp405.decide`;
 *   * справочник кодов дефектов — `erp405.manage`.
 *
 * Метод запроса — часть контракта (см. `application/config/api.php`): изменяющие
 * действия вызываются POST-ом, GET на них получает 405 `method_not_allowed` от
 * ядра, ещё до тела действия. Изменяющие действия защищены от повтора ключом
 * `Idempotency-Key` — тоже ядром, модулю для этого писать нечего.
 *
 * @package    Erp405
 * @category   Controllers
 */
class Controller_Erp405_Api extends Controller_Base
{
    /** @var Service_Erp405_Inspections|null */
    protected $_inspections;

    /** @var Service_Erp405_DefectCodes|null */
    protected $_codes;

    /** @var Service_Erp405_Stats|null */
    protected $_stats;

    // =========================================================================
    //  Чтение
    // =========================================================================

    /**
     * Сводка качества.
     */
    public function action_stats()
    {
        if ( ! $this->can('erp405.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $this->_json_ok(array(
            'summary'    => $this->_stats()->summary(),
            'quarantine' => array('count' => count($this->_stats()->quarantine())),
            'by_cause'   => $this->_stats()->by_cause(),
        ));
    }

    /**
     * Список контролей с фильтрами.
     */
    public function action_inspections()
    {
        if ( ! $this->can('erp405.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $filters = array(
            'kind'        => $this->_query('kind'),
            'status'      => $this->_query('status'),
            'result'      => $this->_query('result'),
            'source'      => $this->_query('source'),
            'term'        => $this->_query('term'),
            'supplier_id' => $this->_query('supplier_id'),
            'item_id'     => $this->_query('item_id'),
            'pending'     => ($this->_query('pending') !== NULL AND $this->_query('pending') !== ''),
        );

        $rows = array();

        foreach ($this->_inspections()->search($filters, (int) $this->_query('limit', 25), (int) $this->_query('offset', 0)) as $inspection)
        {
            $rows[] = $this->_inspection_row($inspection);
        }

        $this->_json_ok(array('rows' => $rows, 'total' => $this->_inspections()->count($filters)));
    }

    /**
     * Контроль целиком: партия, дефекты, решения.
     */
    public function action_inspection()
    {
        if ( ! $this->can('erp405.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $inspection = $this->_inspections()->get($this->_query('id', $this->request->param('id')));

        if ( ! $inspection)
        {
            $this->_json_fail('Контроль качества не найден', 'not_found', 404);
            return;
        }

        $this->_json_ok(array(
            'inspection' => $this->_inspection_row($inspection),
            'defects'    => array_map(array($this, '_defect_row'), $this->_inspections()->defects($inspection->id)),
            'decisions'  => array_map(array($this, '_decision_row'), $this->_inspections()->decisions($inspection->id)),
            'quarantine' => array(
                'qty'          => (float) $inspection->quarantine_qty,
                'warehouse_id' => (int) $inspection->quarantine_warehouse_id ?: NULL,
            ),
        ));
    }

    /**
     * Очередь решений: брак без решения.
     */
    public function action_pending()
    {
        if ( ! $this->can('erp405.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $rows = array();

        foreach ($this->_inspections()->pending((int) $this->_query('limit', 25)) as $inspection)
        {
            $rows[] = $this->_inspection_row($inspection);
        }

        $this->_json_ok(array('rows' => $rows));
    }

    /**
     * Карантин: что лежит изолированным.
     */
    public function action_quarantine()
    {
        if ( ! $this->can('erp405.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $this->_json_ok(array(
            'rows'       => $this->_stats()->quarantine(),
            'warehouses' => $this->_inspections()->quarantine_warehouses(),
        ));
    }

    /**
     * Справочник кодов дефектов.
     */
    public function action_codes()
    {
        if ( ! $this->can('erp405.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $active_only = ($this->_query('all') === NULL OR $this->_query('all') === '');
        $rows        = array();

        foreach ($this->_codes()->search(array(
            'term'        => $this->_query('term'),
            'category'    => $this->_query('category'),
            'active_only' => $active_only,
        ), (int) $this->_query('limit', 200), 0) as $code)
        {
            $rows[] = $this->_code_row($code) + array('usage' => $this->_codes()->usage((int) $code->id));
        }

        $this->_json_ok(array('rows' => $rows, 'categories' => Model_Erp405_DefectCode::$categories));
    }

    /**
     * Один код дефекта.
     */
    public function action_code()
    {
        if ( ! $this->can('erp405.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $code = $this->_codes()->find($this->_query('id', $this->request->param('id')));

        if ( ! $code)
        {
            $this->_json_fail('Код дефекта не найден', 'not_found', 404);
            return;
        }

        $this->_json_ok(array('code' => $this->_code_row($code) + array('usage' => $this->_codes()->usage((int) $code->id))));
    }

    /**
     * Статистика: по кодам, причинам, поставщикам, номенклатуре и месяцам.
     */
    public function action_by_cause()
    {
        if ( ! $this->can('erp405.view'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $from = $this->_query('from');
        $to   = $this->_query('to');

        $this->_json_ok(array(
            'from'        => $from,
            'to'          => $to,
            'by_cause'    => $this->_stats()->by_cause($from, $to),
            'by_code'     => $this->_stats()->by_code($from, $to, (int) $this->_query('limit', 20)),
            'by_supplier' => $this->_stats()->by_supplier($from, $to, (int) $this->_query('limit', 20)),
            'by_item'     => $this->_stats()->by_item($from, $to, (int) $this->_query('limit', 20)),
            'by_month'    => $this->_stats()->by_month((int) $this->_query('months', 6)),
        ));
    }

    // =========================================================================
    //  Изменения
    // =========================================================================

    /**
     * Завести контроль.
     */
    public function action_inspection_create()
    {
        if ( ! $this->can('erp405.inspect'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $post = $this->request->post();

        try
        {
            $inspection = $this->_inspections()->register(array(
                'kind'         => Arr::get($post, 'kind', 'incoming'),
                'source'       => Arr::get($post, 'source', 'manual'),
                'source_ref'   => Arr::get($post, 'source_ref'),
                'item_id'      => Arr::get($post, 'item_id'),
                'item_name'    => Arr::get($post, 'item_name'),
                'qty'          => Arr::get($post, 'qty'),
                'warehouse_id' => Arr::get($post, 'warehouse_id'),
                'supplier_id'  => Arr::get($post, 'supplier_id'),
                'order_id'     => Arr::get($post, 'order_id'),
                'note'         => Arr::get($post, 'note'),
            ), $this->_current_user_id());
        }
        catch (Exception_Erp405_Quality $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array('inspection' => $this->_inspection_row($inspection)), 201);
    }

    /**
     * Завести входной контроль по позиции приёмки закупки (erp403).
     */
    public function action_inspection_from_receipt()
    {
        if ( ! $this->can('erp405.inspect'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        try
        {
            $inspection = $this->_inspections()->from_receipt_line((int) Arr::get($this->request->post(), 'line_id'), $this->_current_user_id());
        }
        catch (Exception_Erp405_Quality $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array('inspection' => $this->_inspection_row($inspection)), 201);
    }

    /**
     * Записать дефект.
     */
    public function action_defect_add()
    {
        if ( ! $this->can('erp405.inspect'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $post = $this->request->post();

        try
        {
            $defect = $this->_inspections()->add_defect((int) $this->_query('id', $this->request->param('id')), array(
                'defect_code_id' => Arr::get($post, 'defect_code_id'),
                'qty'            => Arr::get($post, 'qty'),
                'cause'          => Arr::get($post, 'cause'),
                'severity'       => Arr::get($post, 'severity'),
                'note'           => Arr::get($post, 'note'),
            ), $this->_current_user_id());
        }
        catch (Exception_Erp405_Quality $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $inspection = $this->_inspections()->get((int) $defect->inspection_id);

        $this->_json_ok(array(
            'defect'     => $this->_defect_row($defect),
            'inspection' => $this->_inspection_row($inspection),
        ), 201);
    }

    /**
     * Убрать запись о дефекте.
     */
    public function action_defect_remove()
    {
        if ( ! $this->can('erp405.inspect'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        try
        {
            $inspection = $this->_inspections()->remove_defect((int) $this->_query('defect_id', $this->request->param('id')), $this->_current_user_id());
        }
        catch (Exception_Erp405_Quality $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array('inspection' => $this->_inspection_row($inspection)));
    }

    /**
     * Признать партию годной.
     */
    public function action_passed()
    {
        if ( ! $this->can('erp405.decide'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        try
        {
            $inspection = $this->_inspections()->mark_passed((int) $this->_query('id', $this->request->param('id')), $this->_current_user_id());
        }
        catch (Exception_Erp405_Quality $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array('inspection' => $this->_inspection_row($inspection)));
    }

    /**
     * Изолировать партию на карантин.
     */
    public function action_quarantine_apply()
    {
        if ( ! $this->can('erp405.decide'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $post = $this->request->post();

        try
        {
            $inspection = $this->_inspections()->quarantine(
                (int) $this->_query('id', $this->request->param('id')),
                Arr::get($post, 'warehouse_id'),
                Arr::get($post, 'qty'),
                $this->_current_user_id()
            );
        }
        catch (Exception_Erp405_Quality $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array('inspection' => $this->_inspection_row($inspection)));
    }

    /**
     * Выпустить партию из карантина.
     */
    public function action_release()
    {
        if ( ! $this->can('erp405.decide'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $post = $this->request->post();

        try
        {
            $inspection = $this->_inspections()->release(
                (int) $this->_query('id', $this->request->param('id')),
                Arr::get($post, 'warehouse_id'),
                Arr::get($post, 'qty'),
                $this->_current_user_id()
            );
        }
        catch (Exception_Erp405_Quality $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array('inspection' => $this->_inspection_row($inspection)));
    }

    /**
     * Решение по браку: переделать, вернуть, утиль, как есть, сортировка.
     */
    public function action_decide()
    {
        if ( ! $this->can('erp405.decide'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $post = $this->request->post();

        try
        {
            $inspection = $this->_inspections()->decide(
                (int) $this->_query('id', $this->request->param('id')),
                (string) Arr::get($post, 'decision'),
                Arr::get($post, 'comment'),
                $this->_current_user_id(),
                array('warehouse_id' => Arr::get($post, 'warehouse_id'))
            );
        }
        catch (Exception_Erp405_Quality $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array('inspection' => $this->_inspection_row($inspection)));
    }

    /**
     * Закрыть контроль.
     */
    public function action_close()
    {
        if ( ! $this->can('erp405.decide'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        try
        {
            $inspection = $this->_inspections()->close((int) $this->_query('id', $this->request->param('id')), $this->_current_user_id());
        }
        catch (Exception_Erp405_Quality $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array('inspection' => $this->_inspection_row($inspection)));
    }

    /**
     * Отменить контроль.
     */
    public function action_cancel()
    {
        if ( ! $this->can('erp405.decide'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        try
        {
            $inspection = $this->_inspections()->cancel(
                (int) $this->_query('id', $this->request->param('id')),
                Arr::get($this->request->post(), 'reason'),
                $this->_current_user_id()
            );
        }
        catch (Exception_Erp405_Quality $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array('inspection' => $this->_inspection_row($inspection)));
    }

    /**
     * Завести или изменить код дефекта.
     */
    public function action_code_save()
    {
        if ( ! $this->can('erp405.manage'))
        {
            $this->_json_fail('Доступ запрещен', 'forbidden', 403);
            return;
        }

        $post = $this->request->post();
        $id   = (int) Arr::get($post, 'id', 0);

        $data = array(
            'code'        => Arr::get($post, 'code'),
            'name'        => Arr::get($post, 'name'),
            'category'    => Arr::get($post, 'category'),
            'severity'    => Arr::get($post, 'severity'),
            'description' => Arr::get($post, 'description'),
            'is_active'   => Arr::get($post, 'is_active'),
        );

        try
        {
            $code = $id ? $this->_codes()->update($id, $data, $this->_current_user_id()) : $this->_codes()->create($data, $this->_current_user_id());
        }
        catch (Exception_Erp405_Quality $e)
        {
            $this->_json_fail($e->getMessage(), $e->reason(), 400);
            return;
        }

        $this->_json_ok(array('code' => $this->_code_row($code)), $id ? 200 : 201);
    }

    // =========================================================================
    //  Представление
    // =========================================================================

    /**
     * @param   Model_Erp405_Inspection $inspection
     * @return  array
     */
    public function _inspection_row($inspection)
    {
        return array(
            'id'            => (int) $inspection->id,
            'uuid'          => (string) $inspection->uuid,
            'number'        => (string) $inspection->number,
            'kind'          => (string) $inspection->kind,
            'kind_label'    => $inspection->kind_label(),
            'source'        => (string) $inspection->source,
            'source_label'  => $inspection->source_label(),
            'source_ref'    => $inspection->source_ref ? (string) $inspection->source_ref : NULL,
            'item_id'       => (int) $inspection->item_id ?: NULL,
            'item_name'     => (string) $inspection->item_name,
            'qty'           => (float) $inspection->qty,
            'defect_qty'    => (float) $inspection->defect_qty,
            'good_qty'      => $inspection->good_qty(),
            'defect_share'  => $inspection->defect_share(),
            'warehouse_id'  => (int) $inspection->warehouse_id ?: NULL,
            'supplier_id'   => (int) $inspection->supplier_id ?: NULL,
            'order_id'      => (int) $inspection->order_id ?: NULL,
            'status'        => (string) $inspection->status,
            'status_label'  => $inspection->status_label(),
            'result'        => (string) $inspection->result,
            'result_label'  => $inspection->result_label(),
            'decision'      => (string) $inspection->decision,
            'decision_label'=> $inspection->decision_label(),
            'quarantine'    => array(
                'qty'          => (float) $inspection->quarantine_qty,
                'warehouse_id' => (int) $inspection->quarantine_warehouse_id ?: NULL,
            ),
            'editable'      => $inspection->is_editable(),
            'pending'       => $inspection->is_pending_decision(),
            'inspected_at'  => $inspection->inspected_at ? (string) $inspection->inspected_at : NULL,
            'decided_at'    => $inspection->decided_at ? (string) $inspection->decided_at : NULL,
            'created_at'    => $inspection->created_at ? (string) $inspection->created_at : NULL,
            'note'          => $inspection->note ? (string) $inspection->note : NULL,
        );
    }

    /**
     * @param   Model_Erp405_Defect $defect
     * @return  array
     */
    public function _defect_row($defect)
    {
        return array(
            'id'             => (int) $defect->id,
            'inspection_id'  => (int) $defect->inspection_id,
            'defect_code_id' => (int) $defect->defect_code_id ?: NULL,
            'code'           => $defect->code ? (string) $defect->code : NULL,
            'qty'            => (float) $defect->qty,
            'severity'       => (int) $defect->severity,
            'severity_label' => $defect->severity_label(),
            'cause'          => (string) $defect->cause,
            'cause_label'    => $defect->cause_label(),
            'supplier_id'    => (int) $defect->supplier_id ?: NULL,
            'order_id'       => (int) $defect->order_id ?: NULL,
            'operation_id'   => (int) $defect->operation_id ?: NULL,
            'item_id'        => (int) $defect->item_id ?: NULL,
            'note'           => $defect->note ? (string) $defect->note : NULL,
            'created_at'     => $defect->created_at ? (string) $defect->created_at : NULL,
        );
    }

    /**
     * @param   Model_Erp405_Decision $decision
     * @return  array
     */
    public function _decision_row($decision)
    {
        return array(
            'id'                => (int) $decision->id,
            'decision'          => (string) $decision->decision,
            'decision_label'    => $decision->kind_label(),
            'qty'               => (float) $decision->qty,
            'from_warehouse_id' => (int) $decision->from_warehouse_id ?: NULL,
            'to_warehouse_id'   => (int) $decision->to_warehouse_id ?: NULL,
            'movement_ref'      => $decision->movement_ref ? (string) $decision->movement_ref : NULL,
            'comment'           => $decision->comment ? (string) $decision->comment : NULL,
            'created_at'        => $decision->created_at ? (string) $decision->created_at : NULL,
        );
    }

    /**
     * @param   Model_Erp405_DefectCode $code
     * @return  array
     */
    public function _code_row($code)
    {
        return array(
            'id'             => (int) $code->id,
            'code'           => (string) $code->code,
            'name'           => (string) $code->name,
            'category'       => (string) $code->category,
            'category_label' => $code->category_label(),
            'severity'       => (int) $code->severity,
            'severity_label' => $code->severity_label(),
            'description'    => $code->description ? (string) $code->description : NULL,
            'active'         => $code->is_active(),
        );
    }

    // =========================================================================
    //  Служебное
    // =========================================================================

    /**
     * @return  Service_Erp405_Inspections
     */
    protected function _inspections()
    {
        if ($this->_inspections === NULL)
        {
            $this->_inspections = Service_Erp405_Inspections::instance();
        }

        return $this->_inspections;
    }

    /**
     * @return  Service_Erp405_DefectCodes
     */
    protected function _codes()
    {
        if ($this->_codes === NULL)
        {
            $this->_codes = Service_Erp405_DefectCodes::instance();
        }

        return $this->_codes;
    }

    /**
     * @return  Service_Erp405_Stats
     */
    protected function _stats()
    {
        if ($this->_stats === NULL)
        {
            $this->_stats = Service_Erp405_Stats::instance();
        }

        return $this->_stats;
    }

    /**
     * @return  int|null
     */
    protected function _current_user_id()
    {
        try
        {
            $user = Auth::instance()->get_user();

            return ($user AND $user->loaded()) ? (int) $user->id : NULL;
        }
        catch (Exception $e)
        {
            return NULL;
        }
    }
}
