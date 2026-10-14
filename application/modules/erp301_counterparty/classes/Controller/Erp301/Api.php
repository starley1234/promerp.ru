<?php defined('SYSPATH') or die('No direct script access.');

/**
 * JSON API модуля контрагентов (erp301_counterparty).
 *
 * Используется другими модулями (закупки, отгрузка, документы) и автодополнением:
 *
 *   GET /counterparties/api/search?term=ромашка&role=supplier
 *   GET /counterparties/api/suggest?term=ром
 *   GET /counterparties/api/card/12
 *
 * @package    Erp301
 * @category   Controllers
 */
class Controller_Erp301_Api extends Controller_Erp301_Main
{
    public function before()
    {
        parent::before();

        $this->auto_render = FALSE;
        $this->response->headers('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Поиск контрагентов.
     */
    public function action_search()
    {
        if ( ! $this->can('erp301.view'))
        {
            return $this->_json(array('status' => 'error', 'message' => 'Доступ запрещён'), 403);
        }

        $filters = array(
            'term'   => trim((string) $this->request->query('term')),
            'role'   => $this->request->query('role'),
            'status' => $this->request->query('status'),
            'kind'   => $this->request->query('kind'),
        );

        $limit = (int) $this->request->query('limit');
        $limit = ($limit > 0 AND $limit <= 100) ? $limit : 25;

        $result = Service_Erp301_Counterparties::instance()->search($filters, $limit, 0);

        $rows = array();

        foreach ($result['rows'] as $row)
        {
            $rows[] = $this->_row($row);
        }

        return $this->_json(array('status' => 'ok', 'total' => $result['total'], 'data' => $rows));
    }

    /**
     * Подсказки для автодополнения.
     */
    public function action_suggest()
    {
        if ( ! $this->can('erp301.view'))
        {
            return $this->_json(array('status' => 'error', 'message' => 'Доступ запрещён'), 403);
        }

        return $this->_json(array(
            'status' => 'ok',
            'data'   => Service_Erp301_Counterparties::instance()->suggest(
                (string) $this->request->query('term'),
                $this->request->query('role')
            ),
        ));
    }

    /**
     * Карточка контрагента с контактами, реквизитами и договорами.
     */
    public function action_card()
    {
        if ( ! $this->can('erp301.view'))
        {
            return $this->_json(array('status' => 'error', 'message' => 'Доступ запрещён'), 403);
        }

        $service = Service_Erp301_Counterparties::instance();

        try
        {
            $counterparty = $service->get((int) $this->request->param('id'));
        }
        catch (Exception $e)
        {
            return $this->_json(array('status' => 'error', 'message' => $e->getMessage()), 404);
        }

        $contacts    = array();
        $requisites  = array();
        $contracts   = array();

        foreach ($service->contacts($counterparty->id)->find_all() as $contact)
        {
            $contacts[] = array(
                'id'         => (int) $contact->id,
                'name'       => $contact->name,
                'position'   => $contact->position,
                'phone'      => $contact->phone,
                'email'      => $contact->email,
                'is_primary' => (bool) $contact->is_primary,
            );
        }

        foreach ($service->requisites($counterparty->id)->find_all() as $requisite)
        {
            $requisites[] = array(
                'id'      => (int) $requisite->id,
                'kind'    => $requisite->kind,
                'summary' => $requisite->summary(),
            );
        }

        foreach ($counterparty->active_contracts()->find_all() as $contract)
        {
            $contracts[] = array(
                'id'       => (int) $contract->id,
                'number'   => $contract->number,
                'kind'     => $contract->kind,
                'status'   => $contract->status,
                'valid_to' => $contract->valid_to,
                'amount'   => $contract->amount_label(),
            );
        }

        return $this->_json(array(
            'status' => 'ok',
            'data'   => $this->_row($counterparty) + array(
                'contacts'   => $contacts,
                'requisites' => $requisites,
                'contracts'  => $contracts,
            ),
        ));
    }

    /**
     * Представление контрагента для API.
     *
     * @param  Model_Erp301_Counterparty $row
     * @return array
     */
    protected function _row($row)
    {
        return array(
            'id'      => (int) $row->id,
            'code'    => $row->code,
            'name'    => $row->name,
            'kind'    => $row->kind,
            'roles'   => $row->role_codes(),
            'inn'     => $row->inn,
            'status'  => $row->status,
            'phone'   => $row->phone,
            'email'   => $row->email,
            'address' => $row->address,
        );
    }

    /**
     * @param  array $data
     * @param  int   $code
     * @return void
     */
    protected function _json(array $data, $code = 200)
    {
        $this->response
            ->status($code)
            ->body(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}
