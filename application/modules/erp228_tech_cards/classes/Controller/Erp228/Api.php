<?php defined('SYSPATH') or die('No direct script access.');

/**
 * JSON API технологических карт (erp228_tech_cards).
 *
 * Используется другими модулями и интерфейсом:
 *   GET  /tech_cards/api/status/<id>      состояние генерации
 *   GET  /tech_cards/api/data/<doc_id>    операции и структура документа
 *   GET  /tech_cards/api/search?term=…    список карт
 *   GET  /tech_cards/api/suggest?term=…   автодополнение
 *   GET  /tech_cards/api/stats            сводка модуля
 *   POST /tech_cards/api/generate         запустить генерацию (id в теле)
 *
 * @package    Erp228
 * @category   Controllers
 */
class Controller_Erp228_Api extends Controller_Erp228_Main
{
    /**
     * Состояние генерации карты.
     */
    public function action_status()
    {
        return $this->_respond(function () {
            $this->_require_view();

            try
            {
                $card = Service_Erp228_TechCards::instance()->get($this->request->param('id'));
            }
            catch (Kohana_Exception $e)
            {
                throw new HTTP_Exception_404('Технологическая карта не найдена');
            }

            $document = Service_Erp228_TechCards::instance()->actual_document($card->id);

            return array(
                'status' => 'ok',
                'id'     => (int) $card->id,
                'uuid'   => $card->uuid,
                'state'  => $card->status,
                'state_label' => $card->status_label(),
                'error_msg'   => $card->error_msg,
                'updated_at'  => $card->updated_at,
                'document_id' => $document ? (int) $document->id : NULL,
            );
        });
    }

    /**
     * Данные документа: операции, структура, стоимость.
     */
    public function action_data()
    {
        return $this->_respond(function () {
            $this->_require_view();

            $document = ORM::factory('Erp228_TechDocument', (int) $this->request->param('id'));

            if ( ! $document->loaded())
            {
                throw new HTTP_Exception_404('Документ не найден');
            }

            $operations = array();

            foreach ($document->operations_ordered() as $operation)
            {
                $operations[] = array(
                    'id'           => (int) $operation->id,
                    'sort_order'   => (int) $operation->sort_order,
                    'name'         => $operation->op_name,
                    'description'  => $operation->op_description,
                    'duration_sec' => (int) $operation->duration_sec,
                    'cost'         => (float) $operation->cost_amount,
                    'params'       => $operation->decode_params(),
                );
            }

            return array(
                'status' => 'ok',
                'meta'   => array(
                    'card_id'     => (int) $document->tech_card_id,
                    'document_id' => (int) $document->id,
                    'is_actual'   => (int) $document->is_actual,
                    'model'       => $document->llm_model,
                    'tokens'      => (int) $document->tokens_total,
                    'gen_time_ms' => (int) $document->gen_time_ms,
                    'duration_total' => $document->duration_total(),
                    'cost_total'     => $document->cost_total(),
                ),
                'structured_data' => $document->decode_structured(),
                'operations'      => $operations,
            );
        });
    }

    /**
     * Запуск генерации техпроцесса (POST).
     */
    public function action_generate()
    {
        return $this->_respond(function () {
            if ( ! $this->can('erp228.manage'))
            {
                throw new HTTP_Exception_403('У вас нет прав на генерацию техпроцесса');
            }

            $id   = (int) Arr::get($_POST, 'id', $this->request->param('id'));
            $card = Service_Erp228_TechCards::instance()->get($id);
            $document = Service_Erp228_TechCards::instance()->generate($card->id, $this->_user_id());

            return array(
                'status'      => 'ok',
                'document_id' => (int) $document->id,
                'card_status' => 'ready',
                'message'     => 'Техпроцесс сформирован',
            );
        });
    }

    /**
     * Список карт с фильтрами (постранично).
     */
    public function action_search()
    {
        return $this->_respond(function () {
            $this->_require_view();

            $query    = $this->request->query();
            $limit    = isset($query['limit']) ? min(max((int) $query['limit'], 1), 100) : 20;
            $page     = isset($query['page']) ? max((int) $query['page'], 1) : 1;

            $service = Service_Erp228_TechCards::instance();
            $result  = $service->search(array(
                'term'            => isset($query['term']) ? (string) $query['term'] : NULL,
                'status'          => isset($query['status']) ? (string) $query['status'] : NULL,
                'production_type' => isset($query['production_type']) ? (string) $query['production_type'] : NULL,
            ), $limit, ($page - 1) * $limit);

            $rows = array();

            foreach ($result['rows'] as $card)
            {
                $rows[] = $card->summary() + array(
                    'created_at'      => $card->created_at,
                    'production_label' => $card->production_label(),
                );
            }

            return array(
                'status' => 'ok',
                'total'  => (int) $result['total'],
                'limit'  => $limit,
                'offset' => ($page - 1) * $limit,
                'data'   => $rows,
            );
        });
    }

    /**
     * Автодополнение по названию карты.
     */
    public function action_suggest()
    {
        return $this->_respond(function () {
            $this->_require_view();

            $term = (string) $this->request->query('term');

            return array(
                'status' => 'ok',
                'data'   => Service_Erp228_TechCards::instance()->suggest($term),
            );
        });
    }

    /**
     * Сводка модуля (для панели и виджетов).
     */
    public function action_stats()
    {
        return $this->_respond(function () {
            $this->_require_view();

            $stats = Service_Erp228_TechCards::instance()->stats();

            return array(
                'status' => 'ok',
                'total'  => $stats['total'],
                'ready'  => $stats['ready'],
                'by_status' => $stats['by_status'],
                'by_type'   => $stats['by_type'],
                'documents' => $stats['documents'],
                'operations' => $stats['operations'],
                'cost_total' => $stats['cost_total'],
            );
        });
    }

    /**
     * @return void
     * @throws HTTP_Exception_403
     */
    protected function _require_view()
    {
        if ( ! $this->can('erp228.view'))
        {
            throw new HTTP_Exception_403('У вас нет прав на просмотр техкарт');
        }
    }
}
