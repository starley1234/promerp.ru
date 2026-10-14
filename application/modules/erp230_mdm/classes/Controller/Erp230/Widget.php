<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Контроллер виджетов MDM.
 * HMVC компоненты для отображения BOM и индикаторов доверия.
 * 
 * @package Erp230
 * @version 1.1.0
 */
class Controller_Erp230_Widget extends Controller_Erp230_Main {

    /**
     * Виджет структуры изделия (BOM Tree).
     * Используется для визуализации EBOM/MBOM связей.
     */
    public function action_bom()
    {
        // 1. Получить ID объекта
        $id = $this->request->param('id');

        // 2. Проверить права
        if ( ! $this->can('mdm.object.view'))
        {
            return; // HMVC компоненты обычно возвращают пустоту при отсутствии прав
        }

        // 3. Получить данные через сервис
        $object = ORM::factory('Erp230_Object', $id);

        if ( ! $object->loaded())
        {
            return;
        }

        // Получаем связи (активные на текущий момент)
        $links = ORM::factory('Erp230_Link')
            ->where('parent_id', '=', $object->id)
            ->scope_active()
            ->find_all();

        // 4. Передать данные в View
        $view = View::factory('erp230/objects/bom')
            ->set('object', $object)
            ->set('links', $links);

        $this->response->body($view->render());
    }

    /**
     * Виджет индикатора доверия (Trust Score Indicator).
     * Показывает уровень уверенности в данных на основе источников.
     */
    public function action_trust_score()
    {
        // 1. Получить ID объекта
        $id = $this->request->param('id');

        // 2. Проверить права
        if ( ! $this->can('mdm.object.view'))
        {
            return;
        }

        // 3. Получить данные
        $object = ORM::factory('Erp230_Object', $id);
        if ( ! $object->loaded())
        {
            return;
        }

        // Оптимизация: расчет среднего доверия через SQL вместо итерации в PHP
        $score_data = DB::select([DB::expr('AVG(confidence)'), 'avg_score'])
            ->from('object_properties')
            ->where('object_id', '=', $object->id)
            ->where('is_current', '=', TRUE)
            ->execute($this->_db)
            ->current();

        $average_score = (float) Arr::get($score_data, 'avg_score', 0) * 100;

        // 4. Вывод
        $view = View::factory('erp230/widgets/trust_score')
            ->set('score', (int) $average_score)
            ->set('is_dirty', $object->is_dirty)
            ->set('object_id', $object->id);

        $this->response->body($view->render());
    }

    /**
     * Виджет кросс-референсов (External IDs).
     * Показывает связи объекта с внешними системами (PLM, ERP).
     */
    public function action_xrefs()
    {
        $id = $this->request->param('id');

        if ( ! $this->can('mdm.object.view'))
        {
            return;
        }

        $xrefs = DB::select('source_id', 'remote_id', ['name', 'source_name'])
            ->from('object_xref')
            ->join('sources', 'LEFT')->on('object_xref.source_id', '=', 'sources.id')
            ->where('object_id', '=', $id)
            ->execute($this->_db)
            ->as_array();

        $view = View::factory('erp230/widgets/xrefs')
            ->set('xrefs', $xrefs);

        $this->response->body($view->render());
    }

    /**
     * API метод для получения данных BOM в формате JSON (для динамических деревьев)
     */
    public function action_bom_json()
    {
        $id = $this->request->param('id');
        
        if ( ! $this->can('mdm.object.view'))
        {
            return $this->_json_response(['status' => 'error', 'message' => 'Forbidden'], 403);
        }

        $links = ORM::factory('Erp230_Link')
            ->where('parent_id', '=', $id)
            ->scope_active()
            ->with('child')
            ->find_all();

        $data = [];
        foreach ($links as $link)
        {
            $data[] = [
                'id'         => $link->child->id,
                'code'       => $link->child->master_code,
                'qty'        => (float) $link->qty,
                'type'       => $link->link_type,
                'designator' => $link->designator
            ];
        }

        return $this->_json_response([
            'status' => 'ok',
            'items'  => $data
        ]);
    }
}