<?php defined('SYSPATH') or die('No direct script access.');

/**
 * JSON API продаж (erp221_crm) — для мобильного клиента и интеграций.
 *
 * Интерфейс узкий и предсказуемый: чтение — GET, изменения — POST (контракт
 * `Controller_Base`), ответы в формате `{success, data}`. Менеджер в поездке
 * видит свою воронку, добавляет запись в ленту и двигает сделку — этого
 * достаточно, тяжёлые операции остаются браузеру.
 *
 * @package    Erp221
 * @category   Controllers
 */
class Controller_Erp221_Api extends Controller_Erp221_Main
{
    public function before()
    {
        parent::before();

        $this->auto_render = FALSE;
        $this->response->headers('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Корень API: что умеет адрес.
     *
     * @return  void
     */
    public function action_index()
    {
        $this->_json_ok(array(
            'module'  => 'erp221_crm',
            'title'   => 'Продажи: JSON API',
            'version' => 1,
            'read'    => array('board', 'deals', 'deal', 'clients', 'client', 'stages', 'journal', 'tasks', 'analytics', 'kb', 'suggest'),
            'write'   => array('deal_save', 'deal_stage', 'deal_delete', 'activity_save', 'activity_delete', 'client_save', 'contact_save', 'ai_run'),
            'auth'    => 'Сессия системы или ключ доступа (erp804_mcp) в заголовке Authorization: Bearer …',
        ));
    }

    // =========================================================================
    //  Чтение
    // =========================================================================

    /**
     * Доска: сделки по стадиям.
     *
     * @return  void
     */
    public function action_board()
    {
        $this->guard_api('erp221.view');

        $filters = $this->payload();

        $columns = array();

        foreach (Service_Erp221_Deals::instance()->board($filters, (int) Arr::get($filters, 'limit', 30)) as $column)
        {
            $columns[] = array(
                'stage'    => $column['stage'],
                'count'    => $column['count'],
                'amount'   => $column['amount'],
                'forecast' => $column['forecast'],
                'deals'    => array_map(array($this, '_deal_row'), $column['deals']),
            );
        }

        $this->_json_ok(array(
            'columns' => $columns,
            'stats'   => Service_Erp221_Deals::instance()->stats(),
        ));
    }

    /**
     * Список сделок с фильтрами.
     *
     * @return  void
     */
    public function action_deals()
    {
        $this->guard_api('erp221.view');

        $filters = $this->payload();
        $limit   = max(1, min(200, (int) Arr::get($filters, 'limit', 25)));
        $offset  = max(0, (int) Arr::get($filters, 'offset', 0));

        $result = Service_Erp221_Deals::instance()->search($filters, $limit, $offset);

        $this->_json_ok(array(
            'deals' => array_map(array($this, '_deal_row'), $result['rows']),
            'total' => $result['total'],
        ));
    }

    /**
     * Карточка сделки.
     *
     * @return  void
     */
    public function action_deal()
    {
        $this->guard_api('erp221.view');

        $ref = $this->request->param('id') ? $this->request->param('id') : $this->_query('id');

        if ( ! $ref)
        {
            $this->_json_fail('Укажите сделку: /crm/api/deal/<номер или id>', 'validation', 400);
            return;
        }

        try
        {
            $deal = Service_Erp221_Deals::instance()->get($ref);
        }
        catch (Exception $e)
        {
            $this->_json_fail($e->getMessage(), 'not_found', 404);
            return;
        }

        $this->_json_ok(array(
            'deal'        => $this->_deal_row($deal),
            'contacts'    => $deal['contacts'],
            'timeline'    => $deal['timeline'],
            'tasks'       => $deal['tasks'],
            'stale_days'  => $deal['stale_days'],
            'stage'       => $deal['stage'],
            'insights'    => $deal['insights'],
        ));
    }

    /**
     * Клиенты: контрагенты с профилем продаж.
     *
     * @return  void
     */
    public function action_clients()
    {
        $this->guard_api('erp221.clients.view');

        $filters = $this->payload();
        $limit   = max(1, min(200, (int) Arr::get($filters, 'limit', 25)));
        $offset  = max(0, (int) Arr::get($filters, 'offset', 0));

        $result = Service_Erp221_Clients::instance()->search(array(
            'term'   => Arr::get($filters, 'q', Arr::get($filters, 'term')),
            'status' => Arr::get($filters, 'status'),
            'kind'   => Arr::get($filters, 'kind'),
            'role'   => Arr::get($filters, 'role', 'customer'),
        ), $limit, $offset);

        $this->_json_ok(array(
            'clients' => $result['rows'],
            'total'   => $result['total'],
            'stats'   => Service_Erp221_Clients::instance()->stats(),
        ));
    }

    /**
     * Карточка клиента.
     *
     * @return  void
     */
    public function action_client()
    {
        $this->guard_api('erp221.clients.view');

        $ref = $this->request->param('id') ? $this->request->param('id') : $this->_query('id');

        try
        {
            $this->_json_ok(array('client' => Service_Erp221_Clients::instance()->get($ref)));
        }
        catch (Exception $e)
        {
            $this->_json_fail($e->getMessage(), 'not_found', 404);
            return;
        }
    }

    /**
     * Справочники: стадии, причины отказа, воронка.
     *
     * @return  void
     */
    public function action_stages()
    {
        $this->guard_api('erp221.view');

        $this->_json_ok(array(
            'stages'       => Service_Erp221_Stages::instance()->all(),
            'loss_reasons' => Service_Erp221_Stages::instance()->loss_reasons(),
            'kinds'        => Service_Erp221_Stages::instance()->kinds(),
            'funnel'       => Service_Erp221_Analytics::instance()->funnel($this->payload()),
        ));
    }

    /**
     * Лента: записи по сделкам и клиентам.
     *
     * @return  void
     */
    public function action_journal()
    {
        $this->guard_api('erp221.view');

        $filters = $this->payload();
        $limit   = max(1, min(200, (int) Arr::get($filters, 'limit', 50)));
        $offset  = max(0, (int) Arr::get($filters, 'offset', 0));

        $result = Service_Erp221_Timeline::instance()->journal($filters, $limit, $offset);

        $this->_json_ok(array(
            'entries' => $result['rows'],
            'total'   => $result['total'],
            'types'   => Service_Erp221_Timeline::instance()->types(),
        ));
    }

    /**
     * Мои задачи: что ждёт выполнения.
     *
     * @return  void
     */
    public function action_tasks()
    {
        $this->guard_api('erp221.view');

        $filters = $this->payload();
        $user_id = (int) Arr::get($filters, 'assigned_to') ?: $this->actor();

        $this->_json_ok(array(
            'tasks'   => Service_Erp221_Timeline::instance()->tasks($user_id, $filters),
            'agenda'  => Service_Erp221_Reminders::instance()->agenda($user_id),
        ));
    }

    /**
     * Аналитика продаж.
     *
     * @return  void
     */
    public function action_analytics()
    {
        $this->guard_api('erp221.analytics.view');

        $filters   = $this->payload();
        $analytics = Service_Erp221_Analytics::instance();

        $this->_json_ok(array(
            'overview'  => $analytics->overview($filters),
            'funnel'    => $analytics->funnel($filters),
            'dynamics'  => $analytics->dynamics((int) Arr::get($filters, 'months', 6)),
            'managers'  => $analytics->managers($filters),
            'sources'   => $analytics->sources($filters),
            'reasons'   => $analytics->loss_reasons($filters),
        ));
    }

    /**
     * База знаний: ответы на типовые вопросы.
     *
     * @return  void
     */
    public function action_kb()
    {
        $this->guard_api('erp221.kb.view');

        $term = trim((string) Arr::get($this->payload(), 'q', ''));

        $this->_json_ok(array(
            'articles' => Service_Erp221_Knowledge::instance()->search($term, 50),
        ));
    }

    /**
     * Подсказки клиентов для автодополнения.
     *
     * @return  void
     */
    public function action_suggest()
    {
        $this->guard_api('erp221.clients.view');

        $term = (string) Arr::get($this->payload(), 'term', '');

        $this->_json_ok(array(
            'clients' => Service_Erp301_Counterparties::instance()->suggest($term, 'customer'),
        ));
    }

    // =========================================================================
    //  Изменения (только POST)
    // =========================================================================

    /**
     * Создать или изменить сделку.
     *
     * @return  void
     */
    public function action_deal_save()
    {
        $this->guard_api('erp221.deals.manage', TRUE);

        $data = $this->payload();
        $id   = (int) Arr::get($data, 'id');

        try
        {
            $deal = $id
                ? Service_Erp221_Deals::instance()->update($id, $data, $this->actor())
                : Service_Erp221_Deals::instance()->create($data, $this->actor());

            $this->_json_ok(array('deal' => $this->_deal_row($deal)));
        }
        catch (Exception $e)
        {
            $this->_json_fail($e->getMessage(), 'validation', 400);
            return;
        }
    }

    /**
     * Сменить стадию сделки.
     *
     * @return  void
     */
    public function action_deal_stage()
    {
        $this->guard_api('erp221.deals.manage', TRUE);

        $data = $this->payload();

        try
        {
            $deal = Service_Erp221_Deals::instance()->change_stage(
                (int) Arr::get($data, 'id'),
                Arr::get($data, 'stage_id', Arr::get($data, 'stage')),
                $data,
                $this->actor()
            );

            $this->_json_ok(array('deal' => $this->_deal_row($deal)));
        }
        catch (Exception $e)
        {
            $this->_json_fail($e->getMessage(), 'validation', 400);
            return;
        }
    }

    /**
     * Убрать сделку из работы.
     *
     * @return  void
     */
    public function action_deal_delete()
    {
        $this->guard_api('erp221.deals.delete', TRUE);

        try
        {
            Service_Erp221_Deals::instance()->delete((int) Arr::get($this->payload(), 'id'), $this->actor());

            $this->_json_ok(array('deleted' => TRUE));
        }
        catch (Exception $e)
        {
            $this->_json_fail($e->getMessage(), 'validation', 400);
            return;
        }
    }

    /**
     * Добавить запись в ленту сделки.
     *
     * @return  void
     */
    public function action_activity_save()
    {
        $this->guard_api('erp221.deals.manage', TRUE);

        $data = $this->payload();

        try
        {
            $activity = Service_Erp221_Timeline::instance()->add((int) Arr::get($data, 'deal_id'), $data, $this->actor());

            $this->_json_ok(array('activity' => $activity));
        }
        catch (Exception $e)
        {
            $this->_json_fail($e->getMessage(), 'validation', 400);
            return;
        }
    }

    /**
     * Отметить задачу выполненной или убрать запись из ленты.
     *
     * @return  void
     */
    public function action_activity_delete()
    {
        $this->guard_api('erp221.deals.manage', TRUE);

        $data = $this->payload();

        try
        {
            if (Arr::get($data, 'do') === 'complete')
            {
                $this->_json_ok(array('activity' => Service_Erp221_Timeline::instance()->complete((int) Arr::get($data, 'id'), $this->actor())));
            }

            Service_Erp221_Timeline::instance()->delete((int) Arr::get($data, 'id'), $this->actor());

            $this->_json_ok(array('deleted' => TRUE));
        }
        catch (Exception $e)
        {
            $this->_json_fail($e->getMessage(), 'validation', 400);
            return;
        }
    }

    /**
     * Создать или изменить клиента.
     *
     * @return  void
     */
    public function action_client_save()
    {
        $this->guard_api('erp221.clients.manage', TRUE);

        $data = $this->payload();
        $id   = (int) Arr::get($data, 'id');

        try
        {
            $client = $id
                ? Service_Erp221_Clients::instance()->update($id, $data, $this->actor())
                : Service_Erp221_Clients::instance()->create($data, $this->actor());

            $this->_json_ok(array('client' => $client));
        }
        catch (Exception $e)
        {
            $this->_json_fail($e->getMessage(), 'validation', 400);
            return;
        }
    }

    /**
     * Добавить или изменить контактное лицо клиента.
     *
     * @return  void
     */
    public function action_contact_save()
    {
        $this->guard_api('erp221.clients.manage', TRUE);

        $data = $this->payload();

        try
        {
            $client = Service_Erp221_Clients::instance()->save_contact(
                (int) Arr::get($data, 'client_id'),
                $data,
                (int) Arr::get($data, 'contact_id') ?: NULL,
                $this->actor()
            );

            $this->_json_ok(array('client' => $client));
        }
        catch (Exception $e)
        {
            $this->_json_fail($e->getMessage(), 'validation', 400);
            return;
        }
    }

    /**
     * ИИ-разбор сделки.
     *
     * @return  void
     */
    public function action_ai_run()
    {
        $this->guard_api('erp221.ai', TRUE);

        $data = $this->payload();

        try
        {
            $analysis = Service_Erp221_Ai::instance()->insight(
                (int) Arr::get($data, 'deal_id'),
                (string) Arr::get($data, 'insight', 'summary'),
                $this->actor()
            );

            if ( ! $analysis)
            {
                $this->_json_fail('ИИ-провайдер недоступен: разбор не выполнен', 'unavailable', 503);
                return;
            }

            $this->_json_ok(array('analysis' => $analysis));
        }
        catch (Exception $e)
        {
            $this->_json_fail($e->getMessage(), 'validation', 400);
            return;
        }
    }

    /**
     * Компактное представление сделки для списков и мобильного клиента.
     *
     * @param   array  $deal
     * @return  array
     */
    protected function _deal_row(array $deal)
    {
        return array(
            'id'          => $deal['id'],
            'number'      => $deal['number'],
            'title'       => $deal['title'],
            'client'      => array('id' => $deal['client']['id'], 'name' => $deal['client']['name']),
            'stage'       => $deal['stage'],
            'amount'      => $deal['amount'],
            'currency'    => $deal['currency'],
            'probability' => $deal['probability'],
            'forecast'    => $deal['forecast'],
            'deadline'    => $deal['deadline'],
            'priority'    => $deal['priority_level'],
            'assigned_to' => $deal['assigned_to'],
            'is_overdue'  => $deal['is_overdue'],
            'ai_summary'  => $deal['ai_summary'],
        );
    }
}
