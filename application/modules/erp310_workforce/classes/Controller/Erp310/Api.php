<?php defined('SYSPATH') or die('No direct script access.');

class Controller_Erp310_Api extends Controller_Erp310_Base
{
    public function before()
    {
        parent::before();
        $this->auto_render = FALSE;
        $this->response->headers('Content-Type', 'application/json; charset=utf-8');
    }

    public function action_reference()
    {
        if ( ! $this->can('erp310.view')) return $this->json(array('error' => 'Доступ запрещён', 'items' => array()), 403);
        try
        {
            $items = Service_Erp310_Workforce::instance()->references(
                $this->request->query('type'), $this->request->query('q'), 15,
                $this->request->query('current_id'), $this->request->query('exclude_id'),
                Service_Erp100_Access::instance()->allowed_ids($this->user_id(), 'department')
            );
            return $this->json(array('items' => $items));
        }
        catch (Exception $e)
        {
            return $this->json(array('error' => $e->getMessage(), 'items' => array()), 400);
        }
    }

    public function action_employees()
    {
        if ( ! $this->can('erp310.view')) return $this->json(array('status' => 'error', 'message' => 'Доступ запрещён'), 403);
        $allowed = Service_Erp100_Access::instance()->allowed_ids($this->user_id(), 'department');
        $filters = array(
            'term' => $this->request->query('term'), 'status' => $this->request->query('status'),
            'department_id' => $this->request->query('department_id'),
        );
        if ($allowed !== NULL) $filters['department_ids']=$allowed;
        $result = Service_Erp310_Workforce::instance()->search($filters, min(100, max(1, (int) $this->request->query('limit') ?: 25)), 0);
        return $this->json(array('status' => 'ok') + $result);
    }

    public function action_employee()
    {
        if ( ! $this->can('erp310.view')) return $this->json(array('status' => 'error', 'message' => 'Доступ запрещён'), 403);
        try {
            $employee=Service_Erp310_Workforce::instance()->get((int)$this->request->param('id'));
            if ( ! $employee['department_id'] OR ! $this->in_scope('department',(int)$employee['department_id'])) return $this->json(array('status'=>'error','message'=>'Сотрудник вне области доступа'),403);
            return $this->json(array('status'=>'ok','data'=>$employee));
        }
        catch (Exception $e) { return $this->json(array('status' => 'error', 'message' => $e->getMessage()), 404); }
    }

    public function action_history()
    {
        if ( ! $this->can('erp310.view')) return $this->json(array('status' => 'error', 'message' => 'Доступ запрещён'), 403);
        try
        {
            $id = (int) $this->request->param('id');
            $employee=Service_Erp310_Workforce::instance()->get($id);
            if ( ! $employee['department_id'] OR ! $this->in_scope('department',(int)$employee['department_id'])) return $this->json(array('status'=>'error','message'=>'Сотрудник вне области доступа'),403);
            return $this->json(array('status' => 'ok', 'data' => Service_Erp310_Workforce::instance()->assignment_history($id)));
        }
        catch (Exception $e) { return $this->json(array('status' => 'error', 'message' => $e->getMessage()), 404); }
    }

    public function action_staffing()
    {
        if ( ! $this->can('erp310.view')) return $this->json(array('status'=>'error','message'=>'Доступ запрещён'), 403);
        return $this->json(array('status'=>'ok','data'=>Service_Erp310_Workforce::instance()->staffing_rows(TRUE)));
    }

    public function action_positions()
    {
        if ( ! $this->can('erp310.view')) return $this->json(array('status' => 'error', 'message' => 'Доступ запрещён'), 403);
        return $this->json(array('status' => 'ok', 'data' => Service_Erp310_Workforce::instance()->position_rows(TRUE)));
    }

    public function action_departments()
    {
        if ( ! $this->can('erp310.view')) return $this->json(array('status' => 'error', 'message' => 'Доступ запрещён'), 403);
        $rows = array();
        foreach (Service_Erp310_Workforce::instance()->departments() as $row)
            $rows[] = array('id' => (int) $row->id, 'parent_id' => $row->parent_id ? (int) $row->parent_id : NULL, 'code' => $row->code, 'name' => $row->name, 'kind' => $row->kind);
        return $this->json(array('status' => 'ok', 'data' => $rows));
    }

    protected function json(array $data, $status = 200)
    {
        $this->response->status($status)->body(json_encode($data, JSON_UNESCAPED_UNICODE));
    }
}
