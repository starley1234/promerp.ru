<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Рабочее место продаж (erp221_crm): доска, сделки, клиенты, справочники,
 * аналитика, база знаний, лента и напоминания.
 *
 * Модуль ничего не дублирует у соседей: клиенты и контакты живут у erp301
 * (здесь только переход в их карточку), файлы — в erp212, уведомления — в
 * erp217. Страницы CRM показывают то, что принадлежит продажам.
 *
 * @package    Erp221
 * @category   Controllers
 */
class Controller_Erp221_Index extends Controller_Erp221_Main
{
    /** Сколько строк на странице списка. */
    const PER_PAGE = 25;

    /**
     * Доска: сделки по стадиям воронки.
     *
     * @return  void
     */
    public function action_index()
    {
        $this->allow_or_403('erp221.view');

        $filters = $this->_filters();

        $this->set_title('Продажи');
        $this->template->content = View::factory('erp221/index')
            ->set('columns', Service_Erp221_Deals::instance()->board($filters, 30))
            ->set('stats', Service_Erp221_Deals::instance()->stats())
            ->set('client_stats', Service_Erp221_Clients::instance()->stats())
            ->set('tasks', Service_Erp221_Timeline::instance()->tasks($this->actor(), array()))
            ->set('stagnant', Service_Erp221_Reminders::instance()->stagnant_deals($this->actor(), NULL, 5))
            ->set('filters', $filters)
            ->set('users', $this->_users())
            ->set('sources', $this->_sources())
            ->set('can_manage', $this->allows('erp221.deals.manage', TRUE))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Список сделок с фильтрами.
     *
     * @return  void
     */
    public function action_deals()
    {
        $this->allow_or_403('erp221.view');

        $filters = $this->_filters();
        $page    = max(1, (int) $this->_query('page', 1));

        $result = Service_Erp221_Deals::instance()->search($filters, self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        $this->set_title('Сделки');
        $this->template->content = View::factory('erp221/deals')
            ->set('rows', $result['rows'])
            ->set('total', $result['total'])
            ->set('page', $page)
            ->set('per_page', self::PER_PAGE)
            ->set('filters', $filters)
            ->set('stages', Service_Erp221_Stages::instance()->all())
            ->set('users', $this->_users())
            ->set('sources', $this->_sources())
            ->set('url', Route::url('erp221_index', array('action' => 'deals')).'?')
            ->set('can_manage', $this->allows('erp221.deals.manage', TRUE))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Карточка сделки: клиент, лента, задачи, ИИ-разбор и смена стадии.
     *
     * @return  void
     */
    public function action_deal()
    {
        $this->allow_or_403('erp221.view');

        try
        {
            $deal = Service_Erp221_Deals::instance()->get($this->request->param('id'));
        }
        catch (Exception $e)
        {
            throw new HTTP_Exception_404($e->getMessage());
        }

        $this->set_title('Сделка '.$deal['number']);
        $this->template->content = View::factory('erp221/deal')
            ->set('deal', $deal)
            ->set('stages', Service_Erp221_Stages::instance()->all())
            ->set('loss_reasons', Service_Erp221_Stages::instance()->loss_reasons())
            ->set('client_contacts', Service_Erp221_Clients::instance()->contacts($deal['counterparty_id']))
            ->set('types', Service_Erp221_Timeline::instance()->types())
            ->set('insights', Service_Erp221_Ai::instance()->kinds())
            ->set('ai_available', Service_Erp221_Ai::instance()->available())
            ->set('users', $this->_users())
            ->set('can_manage', $this->allows('erp221.deals.manage', TRUE))
            ->set('can_delete', $this->allows('erp221.deals.delete', TRUE))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Создать или изменить сделку (POST).
     *
     * @return  void
     */
    public function action_deal_save()
    {
        $this->allow_or_403('erp221.deals.manage', TRUE);

        if ($this->request->method() !== Request::POST)
        {
            $this->back_to('deals');
        }

        $data = (array) $this->request->post();
        $id   = (int) Arr::get($data, 'id');

        try
        {
            if ($id)
            {
                Service_Erp221_Deals::instance()->update($id, $data, $this->actor());

                $this->_flash('success', 'Сделка сохранена');
            }
            else
            {
                $deal = Service_Erp221_Deals::instance()->create($data, $this->actor());
                $id   = $deal['id'];

                $this->_flash('success', 'Сделка '.$deal['number'].' создана');
            }
        }
        catch (Exception $e)
        {
            $this->_flash('error', $e->getMessage());

            $this->back_to($id ? 'deal' : 'deals', $id ? $id : NULL);
        }

        $this->back_to('deal', $id);
    }

    /**
     * Сменить стадию сделки (POST).
     *
     * @return  void
     */
    public function action_deal_stage()
    {
        $this->allow_or_403('erp221.deals.manage', TRUE);

        if ($this->request->method() !== Request::POST)
        {
            $this->back_to('deals');
        }

        $id = (int) $this->request->post('id');

        try
        {
            $deal = Service_Erp221_Deals::instance()->change_stage(
                $id,
                $this->request->post('stage_id'),
                (array) $this->request->post(),
                $this->actor()
            );

            $this->_flash('success', 'Сделка на стадии «'.$deal['stage']['label'].'»');
        }
        catch (Exception $e)
        {
            $this->_flash('error', $e->getMessage());
        }

        $this->back_to('deal', $id);
    }

    /**
     * Мягко удалить сделку (POST).
     *
     * @return  void
     */
    public function action_deal_delete()
    {
        $this->allow_or_403('erp221.deals.delete', TRUE);

        if ($this->request->method() !== Request::POST)
        {
            $this->back_to('deals');
        }

        $id = (int) $this->request->post('id');

        try
        {
            Service_Erp221_Deals::instance()->delete($id, $this->actor());

            $this->_flash('success', 'Сделка убрана из работы');
        }
        catch (Exception $e)
        {
            $this->_flash('error', $e->getMessage());
        }

        $this->back_to('deals');
    }

    /**
     * Добавить или убрать участника сделки (POST).
     *
     * @return  void
     */
    public function action_deal_contact()
    {
        $this->allow_or_403('erp221.deals.manage', TRUE);

        if ($this->request->method() !== Request::POST)
        {
            $this->back_to('deals');
        }

        $id         = (int) $this->request->post('id');
        $contact_id = (int) $this->request->post('contact_id');

        try
        {
            if ($this->request->post('do') === 'remove')
            {
                Service_Erp221_Deals::instance()->remove_contact($id, $contact_id, $this->actor());

                $this->_flash('success', 'Контакт убран из сделки');
            }
            else
            {
                Service_Erp221_Deals::instance()->add_contact(
                    $id,
                    $contact_id,
                    (bool) $this->request->post('is_primary'),
                    $this->request->post('role'),
                    $this->actor()
                );

                $this->_flash('success', 'Контакт добавлен в сделку');
            }
        }
        catch (Exception $e)
        {
            $this->_flash('error', $e->getMessage());
        }

        $this->back_to('deal', $id);
    }

    /**
     * Записи ленты: добавить (POST).
     *
     * @return  void
     */
    public function action_activity_save()
    {
        $this->allow_or_403('erp221.deals.manage', TRUE);

        if ($this->request->method() !== Request::POST)
        {
            $this->back_to('deals');
        }

        $deal_id = (int) $this->request->post('deal_id');

        try
        {
            Service_Erp221_Timeline::instance()->add($deal_id, (array) $this->request->post(), $this->actor());

            $this->_flash('success', 'Запись добавлена в ленту');
        }
        catch (Exception $e)
        {
            $this->_flash('error', $e->getMessage());
        }

        $this->back_to('deal', $deal_id);
    }

    /**
     * Задачи: отметить выполненной или убрать (POST).
     *
     * @return  void
     */
    public function action_activity_delete()
    {
        $this->allow_or_403('erp221.deals.manage', TRUE);

        if ($this->request->method() !== Request::POST)
        {
            $this->back_to('deals');
        }

        $activity_id = (int) $this->request->post('id');
        $deal_id     = (int) $this->request->post('deal_id');

        try
        {
            if ($this->request->post('do') === 'complete')
            {
                Service_Erp221_Timeline::instance()->complete($activity_id, $this->actor());

                $this->_flash('success', 'Задача выполнена');
            }
            else
            {
                Service_Erp221_Timeline::instance()->delete($activity_id, $this->actor());

                $this->_flash('success', 'Запись убрана из ленты');
            }
        }
        catch (Exception $e)
        {
            $this->_flash('error', $e->getMessage());
        }

        $this->back_to($deal_id ? 'deal' : 'journal', $deal_id ? $deal_id : NULL);
    }

    /**
     * Загрузить файл в сделку (POST, поле file).
     *
     * @return  void
     */
    public function action_activity_file()
    {
        $this->allow_or_403('erp221.deals.manage', TRUE);

        if ($this->request->method() !== Request::POST)
        {
            $this->back_to('deals');
        }

        $deal_id = (int) $this->request->post('deal_id');

        try
        {
            $file = $this->uploaded('file');

            if ( ! $file OR empty($file['name']))
            {
                throw new Kohana_Exception('Выберите файл');
            }

            Service_Erp221_Timeline::instance()->upload($deal_id, $file, $this->actor(), $this->request->post('content'));

            $this->_flash('success', 'Файл приложен к сделке');
        }
        catch (Exception $e)
        {
            $this->_flash('error', $e->getMessage());
        }

        $this->back_to('deal', $deal_id);
    }

    /**
     * Запустить ИИ-разбор сделки (POST).
     *
     * @return  void
     */
    public function action_ai_run()
    {
        $this->allow_or_403('erp221.deals.manage', TRUE);

        if ($this->request->method() !== Request::POST)
        {
            $this->back_to('deals');
        }

        $deal_id = (int) $this->request->post('deal_id');

        try
        {
            $analysis = Service_Erp221_Ai::instance()->insight($deal_id, $this->request->post('insight'), $this->actor());

            if ($analysis)
            {
                $this->_flash('success', 'Разбор готов — он сохранён в карточке сделки');
            }
            else
            {
                $this->_flash('warning', 'ИИ-провайдер недоступен: разбор не выполнен, данные не изменены');
            }
        }
        catch (Exception $e)
        {
            $this->_flash('error', $e->getMessage());
        }

        $this->back_to('deal', $deal_id);
    }

    /**
     * Клиенты: контрагенты с ролью «Покупатель» и профилем продаж.
     *
     * @return  void
     */
    public function action_clients()
    {
        $this->allow_or_403('erp221.clients.view');

        $filters = array(
            'term'   => $this->_query('q', ''),
            'status' => $this->_query('status'),
            'kind'   => $this->_query('kind'),
            'role'   => $this->_query('role', 'customer'),
        );

        $page   = max(1, (int) $this->_query('page', 1));
        $result = Service_Erp221_Clients::instance()->search($filters, self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        $this->set_title('Клиенты');
        $this->template->content = View::factory('erp221/clients')
            ->set('rows', $result['rows'])
            ->set('total', $result['total'])
            ->set('page', $page)
            ->set('per_page', self::PER_PAGE)
            ->set('filters', $filters)
            ->set('stats', Service_Erp221_Clients::instance()->stats())
            ->set('roles', Service_Erp221_Clients::instance()->roles())
            ->set('url', Route::url('erp221_index', array('action' => 'clients')).'?')
            ->set('can_manage', $this->allows('erp221.clients.manage', TRUE))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Карточка клиента: реквизиты, контакты, сделки и лента.
     *
     * @return  void
     */
    public function action_client()
    {
        $this->allow_or_403('erp221.clients.view');

        try
        {
            $client = Service_Erp221_Clients::instance()->get($this->request->param('id'));
        }
        catch (Exception $e)
        {
            throw new HTTP_Exception_404($e->getMessage());
        }

        $this->set_title('Клиент '.$client['name']);
        $this->template->content = View::factory('erp221/client')
            ->set('client', $client)
            ->set('roles', Service_Erp221_Clients::instance()->roles())
            ->set('users', $this->_users())
            ->set('can_manage', $this->allows('erp221.clients.manage', TRUE))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Создать или изменить клиента (POST).
     *
     * @return  void
     */
    public function action_client_save()
    {
        $this->allow_or_403('erp221.clients.manage', TRUE);

        if ($this->request->method() !== Request::POST)
        {
            $this->back_to('clients');
        }

        $data = (array) $this->request->post();
        $id   = (int) Arr::get($data, 'id');

        try
        {
            if ($id)
            {
                Service_Erp221_Clients::instance()->update($id, $data, $this->actor());

                $this->_flash('success', 'Клиент сохранён');
            }
            else
            {
                $client = Service_Erp221_Clients::instance()->create($data, $this->actor());
                $id     = $client['id'];

                $this->_flash('success', 'Клиент добавлен');
            }
        }
        catch (Exception $e)
        {
            $this->_flash('error', $e->getMessage());

            $this->back_to($id ? 'client' : 'clients', $id ? $id : NULL);
        }

        $this->back_to('client', $id);
    }

    /**
     * Добавить или изменить контактное лицо клиента (POST).
     *
     * @return  void
     */
    public function action_contact_save()
    {
        $this->allow_or_403('erp221.clients.manage', TRUE);

        if ($this->request->method() !== Request::POST)
        {
            $this->back_to('clients');
        }

        $client_id = (int) $this->request->post('client_id');

        try
        {
            Service_Erp221_Clients::instance()->save_contact(
                $client_id,
                (array) $this->request->post(),
                (int) $this->request->post('contact_id') ?: NULL,
                $this->actor()
            );

            $this->_flash('success', 'Контакт сохранён');
        }
        catch (Exception $e)
        {
            $this->_flash('error', $e->getMessage());
        }

        $this->back_to('client', $client_id);
    }

    /**
     * Убрать контактное лицо (POST).
     *
     * @return  void
     */
    public function action_contact_delete()
    {
        $this->allow_or_403('erp221.clients.manage', TRUE);

        if ($this->request->method() !== Request::POST)
        {
            $this->back_to('clients');
        }

        $client_id = (int) $this->request->post('client_id');

        try
        {
            Service_Erp221_Clients::instance()->delete_contact($client_id, (int) $this->request->post('contact_id'), $this->actor());

            $this->_flash('success', 'Контакт убран');
        }
        catch (Exception $e)
        {
            $this->_flash('error', $e->getMessage());
        }

        $this->back_to('client', $client_id);
    }

    /**
     * Справочники: стадии воронки и причины отказа.
     *
     * @return  void
     */
    public function action_stages()
    {
        $this->allow_or_403('erp221.stages.view');

        $this->set_title('Стадии и причины отказа');
        $this->template->content = View::factory('erp221/stages')
            ->set('stages', Service_Erp221_Stages::instance()->all(FALSE))
            ->set('loss_reasons', Service_Erp221_Stages::instance()->loss_reasons(FALSE))
            ->set('kinds', Service_Erp221_Stages::instance()->kinds())
            ->set('usage', Service_Erp221_Analytics::instance()->funnel())
            ->set('can_manage', $this->allows('erp221.stages.manage', TRUE))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Сохранить стадию (POST).
     *
     * @return  void
     */
    public function action_stage_save()
    {
        $this->allow_or_403('erp221.stages.manage', TRUE);

        if ($this->request->method() !== Request::POST)
        {
            $this->back_to('stages');
        }

        try
        {
            Service_Erp221_Stages::instance()->save((array) $this->request->post(), $this->actor());

            $this->_flash('success', 'Стадия сохранена');
        }
        catch (Exception $e)
        {
            $this->_flash('error', $e->getMessage());
        }

        $this->back_to('stages');
    }

    /**
     * Удалить стадию (POST; занятую стадию удалить нельзя).
     *
     * @return  void
     */
    public function action_stage_delete()
    {
        $this->allow_or_403('erp221.stages.manage', TRUE);

        if ($this->request->method() !== Request::POST)
        {
            $this->back_to('stages');
        }

        try
        {
            Service_Erp221_Stages::instance()->delete((int) $this->request->post('id'), $this->actor());

            $this->_flash('success', 'Стадия удалена');
        }
        catch (Exception $e)
        {
            $this->_flash('error', $e->getMessage());
        }

        $this->back_to('stages');
    }

    /**
     * Сохранить причину отказа (POST).
     *
     * @return  void
     */
    public function action_loss_reason_save()
    {
        $this->allow_or_403('erp221.stages.manage', TRUE);

        if ($this->request->method() !== Request::POST)
        {
            $this->back_to('stages');
        }

        try
        {
            Service_Erp221_Stages::instance()->save_loss_reason((array) $this->request->post(), $this->actor());

            $this->_flash('success', 'Причина сохранена');
        }
        catch (Exception $e)
        {
            $this->_flash('error', $e->getMessage());
        }

        $this->back_to('stages');
    }

    /**
     * Удалить причину отказа (POST).
     *
     * @return  void
     */
    public function action_loss_reason_delete()
    {
        $this->allow_or_403('erp221.stages.manage', TRUE);

        if ($this->request->method() !== Request::POST)
        {
            $this->back_to('stages');
        }

        try
        {
            Service_Erp221_Stages::instance()->delete_loss_reason((int) $this->request->post('id'), $this->actor());

            $this->_flash('success', 'Причина удалена');
        }
        catch (Exception $e)
        {
            $this->_flash('error', $e->getMessage());
        }

        $this->back_to('stages');
    }

    /**
     * Аналитика продаж: воронка, динамика, менеджеры, источники, отказы.
     *
     * @return  void
     */
    public function action_analytics()
    {
        $this->allow_or_403('erp221.analytics.view');

        $filters = array(
            'from'        => $this->_query('from'),
            'to'          => $this->_query('to'),
            'assigned_to' => $this->_query('assigned_to'),
            'source'      => $this->_query('source'),
        );

        $analytics = Service_Erp221_Analytics::instance();

        $this->set_title('Аналитика продаж');
        $this->template->content = View::factory('erp221/analytics')
            ->set('filters', $filters)
            ->set('overview', $analytics->overview($filters))
            ->set('funnel', $analytics->funnel($filters))
            ->set('dynamics', $analytics->dynamics(6))
            ->set('managers', $analytics->managers($filters))
            ->set('sources', $analytics->sources($filters))
            ->set('reasons', $analytics->loss_reasons($filters))
            ->set('loss_comment', NULL)
            ->set('users', $this->_users())
            ->set('can_ai', $this->allows('erp221.ai', TRUE))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Разбор причин отказов моделью (POST).
     *
     * @return  void
     */
    public function action_loss_analysis()
    {
        $this->allow_or_403('erp221.ai', TRUE);

        if ($this->request->method() !== Request::POST)
        {
            $this->back_to('analytics');
        }

        try
        {
            $result = Service_Erp221_Ai::instance()->analyze_loss_patterns($this->request->post('from'));

            if ($result['comment'])
            {
                $this->_flash('success', 'Разбор отказов готов: '.mb_substr($result['comment'], 0, 300));
            }
            else
            {
                $this->_flash('warning', 'ИИ-провайдер недоступен: показана только статистика отказов');
            }
        }
        catch (Exception $e)
        {
            $this->_flash('error', $e->getMessage());
        }

        $this->back_to('analytics');
    }

    /**
     * База знаний: типовые вопросы клиентов и ответы менеджеру.
     *
     * @return  void
     */
    public function action_kb()
    {
        $this->allow_or_403('erp221.kb.view');

        $term = trim((string) $this->_query('q', ''));

        $rows = Service_Erp221_Knowledge::instance()->search($term);

        $this->set_title('База знаний');
        $this->template->content = View::factory('erp221/kb')
            ->set('rows', $rows)
            ->set('term', $term)
            ->set('can_manage', $this->allows('erp221.kb.manage', TRUE))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Добавить или изменить статью базы знаний (POST).
     *
     * @return  void
     */
    public function action_kb_save()
    {
        $this->allow_or_403('erp221.kb.manage', TRUE);

        if ($this->request->method() !== Request::POST)
        {
            $this->back_to('kb');
        }

        try
        {
            $id = Service_Erp221_Knowledge::instance()->save(array(
                'id'               => (int) $this->request->post('id'),
                'category'         => $this->request->post('category', 'general'),
                'question_pattern' => $this->request->post('question_pattern'),
                'answer_text'      => $this->request->post('answer_text'),
                'tags'             => $this->request->post('tags'),
            ), $this->actor());

            $this->_flash('success', 'Статья сохранена');
        }
        catch (Exception $e)
        {
            $this->_flash('error', $e->getMessage());
        }

        $this->back_to('kb');
    }

    /**
     * Убрать статью (POST, мягко).
     *
     * @return  void
     */
    public function action_kb_delete()
    {
        $this->allow_or_403('erp221.kb.manage', TRUE);

        if ($this->request->method() !== Request::POST)
        {
            $this->back_to('kb');
        }

        Service_Erp221_Knowledge::instance()->delete((int) $this->request->post('id'), $this->actor());

        $this->_flash('success', 'Статья убрана');

        $this->back_to('kb');
    }

    /**
     * Лента: все записи по сделкам и клиентам.
     *
     * @return  void
     */
    public function action_journal()
    {
        $this->allow_or_403('erp221.view');

        $filters = array(
            'q'      => $this->_query('q', ''),
            'type'   => $this->_query('type'),
            'status' => $this->_query('status'),
            'author' => $this->_query('author'),
        );

        $page   = max(1, (int) $this->_query('page', 1));
        $result = Service_Erp221_Timeline::instance()->journal($filters, 50, ($page - 1) * 50);

        $this->set_title('Лента работы');
        $this->template->content = View::factory('erp221/journal')
            ->set('rows', $result['rows'])
            ->set('total', $result['total'])
            ->set('page', $page)
            ->set('per_page', 50)
            ->set('filters', $filters)
            ->set('types', Service_Erp221_Timeline::instance()->types())
            ->set('statuses', Service_Erp221_Timeline::instance()->statuses())
            ->set('url', Route::url('erp221_index', array('action' => 'journal')).'?')
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Напоминания: задачи со сроком, просрочка, застой.
     *
     * @return  void
     */
    public function action_reminders()
    {
        $this->allow_or_403('erp221.view');

        $reminders = Service_Erp221_Reminders::instance();
        $user_id   = $this->actor();

        $this->set_title('Напоминания');
        $this->template->content = View::factory('erp221/reminders')
            ->set('agenda', $reminders->agenda($user_id))
            ->set('users', $this->_users())
            ->set('can_run', $this->allows('erp221.deals.manage', TRUE))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Разослать сводки через центр уведомлений (POST).
     *
     * @return  void
     */
    public function action_reminders_run()
    {
        $this->allow_or_403('erp221.deals.manage', TRUE);

        if ($this->request->method() !== Request::POST)
        {
            $this->back_to('reminders');
        }

        try
        {
            $report = Service_Erp221_Reminders::instance()->run(
                (int) $this->request->post('user_id') ?: NULL
            );

            $this->_flash('success', 'Сводка собрана для сотрудников: '.$report['users'].', уведомлений принято: '.$report['sent']);
        }
        catch (Exception $e)
        {
            $this->_flash('error', $e->getMessage());
        }

        $this->back_to('reminders');
    }

    /**
     * Выгрузка сделок в CSV (GET).
     *
     * @return  void
     */
    public function action_export()
    {
        $this->allow_or_403('erp221.analytics.view');

        $csv = Service_Erp221_Exchange::instance()->export_csv($this->_filters(), 5000);

        $this->auto_render = FALSE;
        $this->response->headers('Content-Type', 'text/csv; charset=utf-8');
        $this->response->headers('Content-Disposition', 'attachment; filename="deals-'.date('Y-m-d').'.csv"');
        $this->response->body($csv);
    }

    /**
     * Импорт сделок из CSV (POST, поле file или построчный текст).
     *
     * @return  void
     */
    public function action_import()
    {
        $this->allow_or_403('erp221.analytics.view', TRUE);

        if ($this->request->method() !== Request::POST)
        {
            $this->back_to('deals');
        }

        try
        {
            $csv = trim((string) $this->request->post('csv'));

            if ($csv === '')
            {
                $file = $this->uploaded('file');

                if ( ! $file OR empty($file['tmp_name']) OR ! is_uploaded_file($file['tmp_name']))
                {
                    throw new Kohana_Exception('Приложите CSV-файл или вставьте строки в поле');
                }

                $csv = (string) file_get_contents($file['tmp_name']);
            }

            $report = Service_Erp221_Exchange::instance()->import_csv(
                $csv,
                $this->actor(),
                (bool) $this->request->post('dry_run')
            );

            $this->_flash(
                $report['errors'] ? 'warning' : 'success',
                'Строк: '.$report['rows'].', создано: '.$report['created'].', изменено: '.$report['updated']
                    .($report['errors'] ? '. Ошибок: '.count($report['errors']).' — '.$report['errors'][0] : '')
            );
        }
        catch (Exception $e)
        {
            $this->_flash('error', $e->getMessage());
        }

        $this->back_to('deals');
    }

    /**
     * Активные контрагенты для подсказок (JSON).
     *
     * @return  void
     */
    public function action_suggest()
    {
        $this->allow_or_403('erp221.clients.view');

        $this->_json_ok(array(
            'clients' => Service_Erp301_Counterparties::instance()->suggest($this->_query('term', ''), $this->_query('role', 'customer')),
        ));
    }

    /**
     * Фильтры списков и доски.
     *
     * @return  array
     */
    protected function _filters()
    {
        return array(
            'q'           => $this->_query('q', ''),
            'stage'       => $this->_query('stage'),
            'kind'        => $this->_query('kind'),
            'assigned_to' => $this->_query('assigned_to'),
            'priority'    => $this->_query('priority'),
            'source'      => $this->_query('source'),
            'counterparty'=> $this->_query('counterparty'),
            'overdue'     => $this->_query('overdue'),
        );
    }

    /**
     * Сотрудники для выбора ответственного.
     *
     * @return  array  id => имя
     */
    protected function _users()
    {
        $users = DB::select('id', 'username')->from('users')
            ->order_by('username', 'ASC')->limit(200)->execute()->as_array();

        $result = array();

        foreach ($users as $user)
        {
            $result[(int) $user['id']] = $user['username'];
        }

        return $result;
    }

    /**
     * Источники обращений, которые уже встречались в сделках.
     *
     * @return  array
     */
    protected function _sources()
    {
        $rows = DB::select('source')->from('erp_221_deals')
            ->where('source', 'IS NOT', NULL)
            ->group_by('source')->order_by('source', 'ASC')
            ->execute()->as_array();

        $result = array();

        foreach ($rows as $row)
        {
            $result[] = $row['source'];
        }

        return $result;
    }

    /**
     * Массив PostgreSQL ({a,b}) из строки.
     *
     * @param   mixed  $value
     * @return  array
     */
    protected function _pg_array($value)
    {
        $value = trim((string) $value, '{}');

        if ($value === '')
        {
            return array();
        }

        return array_values(array_filter(array_map(function ($item) {
            return trim($item, '"');
        }, explode(',', $value))));
    }
}
