<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Админка QR-маркировки (erp199_sqr): наклейки, объекты, инвентаризации,
 * сценарии, типы и журнал действий.
 *
 * Здесь делают то, что нельзя сделать с телефона: выпускают партию наклеек и
 * печатают их, разбирают черновики, заведённые в поле, настраивают сценарии и
 * закрывают инвентаризацию. Сам терминал — отдельный контроллер (Qr): у него
 * нет интерфейса системы, только страница наклейки.
 *
 * @package    Erp199
 * @category   Controllers
 */
class Controller_Erp199_Index extends Controller_Erp199_Main
{
    /** Сколько строк на странице списка. */
    const PER_PAGE = 50;

    /**
     * Дашборд: что происходит с наклейками прямо сейчас.
     *
     * @return  void
     */
    public function action_index()
    {
        $this->allow_or_403('erp199.view');

        $inventory = Service_Erp199_Inventories::instance()->open_session();

        $this->set_title('QR-маркировка');
        $this->template->content = View::factory('erp199/index')
            ->set('stats', Service_Erp199_Tokens::instance()->stats())
            ->set('recent', Service_Erp199_Scan::instance()->activity(NULL, 12))
            ->set('inventory', $inventory)
            ->set('inventory_progress', $inventory ? Service_Erp199_Inventories::instance()->progress($inventory['id']) : NULL)
            ->set('drafts', Service_Erp199_Objects::instance()->search(array('status' => 'draft'), 5)['rows']);
    }

    /**
     * Реестр наклеек: выпуск партии, отбор, печать.
     *
     * @return  void
     */
    public function action_tokens()
    {
        $this->allow_or_403('erp199.tokens.view');

        $filters = array(
            'q'      => $this->_query('q', ''),
            'status' => $this->_query('status'),
            'kind'   => $this->_query('kind'),
            'batch'  => $this->_query('batch'),
            'bound'  => $this->_query('bound'),
        );

        $page   = max(1, (int) $this->_query('page', 1));
        $result = Service_Erp199_Tokens::instance()->search($filters, self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        $this->set_title('Наклейки');
        $this->template->content = View::factory('erp199/tokens')
            ->set('rows', $result['rows'])
            ->set('total', $result['total'])
            ->set('page', $page)
            ->set('per_page', self::PER_PAGE)
            ->set('filters', $filters)
            ->set('stats', Service_Erp199_Tokens::instance()->stats())
            ->set('states', Service_Erp199_Tokens::instance()->states())
            ->set('kinds', Service_Erp199_Targets::instance()->kinds())
            ->set('can_issue', $this->allows('erp199.tokens.issue', TRUE))
            ->set('can_bind', $this->allows('erp199.tokens.bind', TRUE))
            ->set('url', Route::url('erp199_index', array('action' => 'tokens')).'?')
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Выпустить партию наклеек (POST).
     *
     * @return  void
     */
    public function action_issue()
    {
        $this->allow_or_403('erp199.tokens.issue', TRUE);

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect(Route::url('erp199_index', array('action' => 'tokens')));
        }

        try
        {
            $result = Service_Erp199_Tokens::instance()->issue(
                (int) $this->request->post('count'),
                $this->request->post('batch'),
                $this->actor(),
                $this->request->post('note')
            );

            $this->_flash('success', 'Выпущено наклеек: '.count($result['codes']).' (партия '.$result['batch'].')');

            $location = Route::url('erp199_index', array('action' => 'label')).'?batch='.urlencode($result['batch']);
        }
        catch (Exception $e)
        {
            $this->_flash('error', $e->getMessage());

            $location = Route::url('erp199_index', array('action' => 'tokens'));
        }

        // Переход — вне try: redirect() в Kohana сам бросает исключение,
        // и перехват Exception принял бы успешный переход за ошибку.
        $this->redirect($location);
    }

    /**
     * Карточка наклейки: цель, фото, история, привязка и смена состояния.
     *
     * @return  void
     */
    public function action_token()
    {
        $this->allow_or_403('erp199.tokens.view');

        try
        {
            $token = Service_Erp199_Tokens::instance()->get($this->request->param('id'));
        }
        catch (Exception $e)
        {
            throw new HTTP_Exception_404($e->getMessage());
        }

        // Карточка цели приходит от модуля-владельца; если записи больше нет,
        // Targets::card вернёт NULL, и страница покажет «цель не найдена».
        $target = $token['target_kind']
            ? Service_Erp199_Targets::instance()->card($token['target_kind'], $token['target_id'])
            : NULL;

        $this->set_title('Наклейка '.$token['code']);
        $this->template->content = View::factory('erp199/token')
            ->set('token', $token)
            ->set('target', $target)
            ->set('activity', Service_Erp199_Scan::instance()->activity($token['id'], 30))
            ->set('photos', Service_Erp199_Media::instance()->search(array('token_id' => $token['id'])))
            ->set('states', Service_Erp199_Tokens::instance()->states())
            ->set('kinds', Service_Erp199_Targets::instance()->kinds())
            ->set('can_bind', $this->allows('erp199.tokens.bind', TRUE))
            ->set('can_manage', $this->allows('erp199.tokens.manage', TRUE))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Привязать наклейку к цели (POST).
     *
     * @return  void
     */
    public function action_bind()
    {
        $this->allow_or_403('erp199.tokens.bind', TRUE);

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect(Route::url('erp199_index', array('action' => 'tokens')));
        }

        $code = (string) $this->request->post('token');

        try
        {
            $result = $this->scan_service()->bind(
                $code,
                $this->request->post('kind'),
                $this->request->post('ref'),
                $this->actor(),
                $this->request->post('comment'),
                $this->meta((array) $this->request->post())
            );

            $this->_flash('success', 'Наклейка привязана к цели: '.$result['token']['target_label']);
        }
        catch (Exception $e)
        {
            $this->_flash('error', $e->getMessage());
        }

        $this->redirect(Route::url('erp199_index', array('action' => 'token', 'id' => $code)));
    }

    /**
     * Сменить состояние наклейки (POST).
     *
     * @return  void
     */
    public function action_status()
    {
        $this->allow_or_403('erp199.tokens.manage', TRUE);

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect(Route::url('erp199_index', array('action' => 'tokens')));
        }

        $code = (string) $this->request->post('token');

        try
        {
            Service_Erp199_Tokens::instance()->set_status(
                $code,
                $this->request->post('status'),
                $this->actor(),
                $this->request->post('comment')
            );

            $this->_flash('success', 'Состояние наклейки обновлено');
        }
        catch (Exception $e)
        {
            $this->_flash('error', $e->getMessage());
        }

        $this->redirect(Route::url('erp199_index', array('action' => 'token', 'id' => $code)));
    }

    /**
     * Печать наклеек: партия целиком, пачка кодов или одна наклейка.
     *
     * @return  void
     */
    public function action_label()
    {
        $this->allow_or_403('erp199.tokens.view');

        $codes = $this->request->post('codes');

        if ($this->_query('code'))
        {
            $codes = array($this->_query('code'));
        }
        elseif ($this->_query('id'))
        {
            $codes = array($this->_query('id'));
        }
        elseif ($this->request->param('id'))
        {
            $codes = array($this->request->param('id'));
        }
        elseif ($this->_query('batch'))
        {
            $page  = Service_Erp199_Tokens::instance()->search(array('batch' => $this->_query('batch')), 500, 0);
            $codes = array_column($page['rows'], 'code');
        }

        $tokens = array();

        foreach ((array) $codes as $ref)
        {
            if (trim((string) $ref) === '')
            {
                continue;
            }

            try
            {
                $tokens[] = Service_Erp199_Tokens::instance()->get($ref);
            }
            catch (Exception $e)
            {
                // Наклейка не найдена — печать остальных продолжается, а код
                // виден в журнале: лучше напечатать 49 из 50, чем ни одной.
                Kohana::$log->add(Log::WARNING, 'erp199: наклейка :ref не найдена для печати', array(':ref' => (string) $ref));
            }
        }

        $this->auto_render = FALSE;
        $this->response->headers('Content-Type', 'text/html; charset=utf-8');
        // Адрес для QR: ссылка на страницу наклейки (обычная камера откроет
        // терминал), либо только код — по настройке terminal.print_url.
        $print_url = (bool) Kohana::$config->load('erp199_sqr.terminal.print_url');

        $this->response->body(View::factory('erp199/label')
            ->set('tokens', $tokens)
            ->set('print_url', $print_url)
            ->set('base_url', rtrim(URL::base(TRUE, TRUE), '/').'/qr/'));
    }

    /**
     * Свободные объекты — то, для чего в системе нет владельца.
     *
     * @return  void
     */
    public function action_objects()
    {
        $this->allow_or_403('erp199.objects.view');

        $filters = array(
            'q'      => $this->_query('q', ''),
            'status' => $this->_query('status'),
            'type'   => $this->_query('type'),
        );

        $page   = max(1, (int) $this->_query('page', 1));
        $result = Service_Erp199_Objects::instance()->search($filters, self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        $this->set_title('Свободные объекты');
        $this->template->content = View::factory('erp199/objects')
            ->set('rows', $result['rows'])
            ->set('total', $result['total'])
            ->set('page', $page)
            ->set('per_page', self::PER_PAGE)
            ->set('filters', $filters)
            ->set('types', Service_Erp199_Types::instance()->all())
            ->set('statuses', Service_Erp199_Objects::instance()->statuses())
            ->set('can_create', $this->allows('erp199.objects.create', TRUE))
            ->set('can_moderate', $this->allows('erp199.objects.moderate', TRUE))
            ->set('url', Route::url('erp199_index', array('action' => 'objects')).'?')
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Карточка свободного объекта: атрибуты, наклейки, история, модерация.
     *
     * @return  void
     */
    public function action_object()
    {
        $this->allow_or_403('erp199.objects.view');

        try
        {
            $object = Service_Erp199_Objects::instance()->get($this->request->param('id'));
        }
        catch (Exception $e)
        {
            throw new HTTP_Exception_404($e->getMessage());
        }

        $this->set_title('Объект '.$object['name']);
        $this->template->content = View::factory('erp199/object')
            ->set('object', $object)
            ->set('tokens', Service_Erp199_Objects::instance()->tokens_of($object['id']))
            ->set('history', Service_Erp199_Objects::instance()->history($object['id']))
            ->set('photos', $object['photos'])
            ->set('types', Service_Erp199_Types::instance()->all())
            ->set('statuses', Service_Erp199_Objects::instance()->statuses())
            ->set('can_edit', $this->allows('erp199.objects.edit', TRUE))
            ->set('can_moderate', $this->allows('erp199.objects.moderate', TRUE))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Сохранить правки свободного объекта (POST).
     *
     * @return  void
     */
    public function action_save()
    {
        $this->allow_or_403('erp199.objects.edit', TRUE);

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect(Route::url('erp199_index', array('action' => 'objects')));
        }

        $id = (int) $this->request->post('id');

        try
        {
            Service_Erp199_Objects::instance()->update($id, (array) $this->request->post(), $this->actor());

            $this->_flash('success', 'Объект сохранён');
        }
        catch (Exception $e)
        {
            $this->_flash('error', $e->getMessage());
        }

        $this->redirect(Route::url('erp199_index', array('action' => 'object', 'id' => $id)));
    }

    /**
     * Решение модератора по черновику из поля (POST).
     *
     * @return  void
     */
    public function action_moderate()
    {
        $this->allow_or_403('erp199.objects.moderate', TRUE);

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect(Route::url('erp199_index', array('action' => 'objects')));
        }

        $id = (int) $this->request->post('id');

        try
        {
            Service_Erp199_Objects::instance()->moderate(
                $id,
                $this->request->post('status'),
                $this->actor(),
                $this->request->post('comment')
            );

            $this->_flash('success', 'Решение принято');
        }
        catch (Exception $e)
        {
            $this->_flash('error', $e->getMessage());
        }

        $this->redirect(Route::url('erp199_index', array('action' => 'object', 'id' => $id)));
    }

    /**
     * Инвентаризации: открытые и закрытые сессии.
     *
     * @return  void
     */
    public function action_inventories()
    {
        $this->allow_or_403('erp199.inventories.view');

        $service  = Service_Erp199_Inventories::instance();
        $rows     = $service->all(100);
        $progress = array();

        // Прогресс считается по каждой сессии отдельно и отдаётся по id:
        // страница показывает «отмечено / ожидалось» рядом с названием.
        foreach ($rows as $row)
        {
            $progress[(int) $row['id']] = $service->progress($row);
        }

        $this->set_title('Инвентаризации');
        $this->template->content = View::factory('erp199/inventories')
            ->set('rows', $rows)
            ->set('progress', $progress)
            ->set('kinds', Service_Erp199_Targets::instance()->kinds())
            ->set('can_manage', $this->allows('erp199.inventories.manage', TRUE))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Сессия инвентаризации: прогресс, отмеченное, расхождения, закрытие.
     *
     * @return  void
     */
    public function action_inventory()
    {
        $this->allow_or_403('erp199.inventories.view');

        try
        {
            $inventory = Service_Erp199_Inventories::instance()->get($this->request->param('id'));
        }
        catch (Exception $e)
        {
            throw new HTTP_Exception_404($e->getMessage());
        }

        $service = Service_Erp199_Inventories::instance();
        $q       = trim((string) $this->_query('q', ''));

        $this->set_title('Инвентаризация '.$inventory['code']);
        $this->template->content = View::factory('erp199/inventory')
            ->set('inventory', $inventory)
            ->set('progress', $service->progress($inventory['id']))
            ->set('expected', $service->expected($inventory, 500, $q))
            ->set('q', $q)
            ->set('can_manage', $this->allows('erp199.inventories.manage', TRUE))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Открыть новую сессию инвентаризации (POST).
     *
     * @return  void
     */
    public function action_inventory_open()
    {
        $this->allow_or_403('erp199.inventories.manage', TRUE);

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect(Route::url('erp199_index', array('action' => 'inventories')));
        }

        try
        {
            // Область проверки: виды целей и партия наклеек (пусто — все).
            $scope = array();
            $kinds = array_values(array_filter((array) $this->request->post('kinds'), 'strlen'));
            $batch = trim((string) $this->request->post('batch'));

            if ($kinds)
            {
                $scope['kinds'] = $kinds;
            }

            if ($batch !== '')
            {
                $scope['batch'] = $batch;
            }

            $inventory = Service_Erp199_Inventories::instance()->open(array(
                'label'   => $this->request->post('label'),
                'scope'   => $scope,
                'comment' => $this->request->post('note'),
            ), $this->actor());

            $this->_flash('success', 'Инвентаризация открыта: '.$inventory['code']);

            $location = Route::url('erp199_index', array('action' => 'inventory', 'id' => $inventory['id']));
        }
        catch (Exception $e)
        {
            $this->_flash('error', $e->getMessage());

            $location = Route::url('erp199_index', array('action' => 'inventories'));
        }

        $this->redirect($location);
    }

    /**
     * Закрыть сессию инвентаризации (POST).
     *
     * @return  void
     */
    public function action_inventory_close()
    {
        $this->allow_or_403('erp199.inventories.manage', TRUE);

        if ($this->request->method() !== Request::POST)
        {
            $this->redirect(Route::url('erp199_index', array('action' => 'inventories')));
        }

        $id = (int) $this->request->post('id');

        try
        {
            Service_Erp199_Inventories::instance()->close($id, $this->actor(), $this->request->post('comment'));

            $this->_flash('success', 'Инвентаризация закрыта');
        }
        catch (Exception $e)
        {
            $this->_flash('error', $e->getMessage());
        }

        $this->redirect(Route::url('erp199_index', array('action' => 'inventory', 'id' => $id)));
    }

    /**
     * Каталог сценариев терминала.
     *
     * @return  void
     */
    public function action_scenarios()
    {
        $this->allow_or_403('erp199.scenarios.view');

        $this->set_title('Сценарии терминала');
        $this->template->content = View::factory('erp199/scenarios')
            ->set('scenarios', Service_Erp199_Scenarios::instance()->all(FALSE))
            ->set('kinds', Service_Erp199_Targets::instance()->kinds())
            ->set('can_manage', $this->allows('erp199.scenarios.manage', TRUE))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Один сценарий: форма и сохранение (POST).
     *
     * @return  void
     */
    public function action_scenario()
    {
        $this->allow_or_403($this->request->method() === Request::POST ? 'erp199.scenarios.manage' : 'erp199.scenarios.view', TRUE);

        $service = Service_Erp199_Scenarios::instance();
        $ref     = $this->request->param('id') ? $this->request->param('id') : $this->_query('code');

        if ($this->request->method() === Request::POST)
        {
            try
            {
                $scenario = $service->save((array) $this->request->post(), $this->actor());

                $this->_flash('success', 'Сценарий сохранён: '.$scenario['label']);

                $location = Route::url('erp199_index', array('action' => 'scenario', 'id' => $scenario['code']));
            }
            catch (Exception $e)
            {
                $this->_flash('error', $e->getMessage());

                $location = Route::url('erp199_index', array('action' => 'scenario', 'id' => $ref));
            }

            $this->redirect($location);
        }

        $scenario = NULL;

        if ($ref)
        {
            try
            {
                $scenario = $service->get($ref);
            }
            catch (Exception $e)
            {
                throw new HTTP_Exception_404($e->getMessage());
            }
        }

        $this->set_title($scenario ? 'Сценарий '.$scenario['label'] : 'Новый сценарий');
        $this->template->content = View::factory('erp199/scenario')
            ->set('scenario', $scenario)
            ->set('kinds', Service_Erp199_Targets::instance()->kinds())
            ->set('can_manage', $this->allows('erp199.scenarios.manage', TRUE))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Виды объектов, заводимых с телефона.
     *
     * @return  void
     */
    public function action_types()
    {
        $this->allow_or_403('erp199.types.view');

        $this->set_title('Типы объектов');
        $this->template->content = View::factory('erp199/types')
            ->set('types', Service_Erp199_Types::instance()->all(FALSE))
            ->set('can_manage', $this->allows('erp199.types.manage', TRUE))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Один тип объекта: форма и сохранение (POST).
     *
     * @return  void
     */
    public function action_type()
    {
        $this->allow_or_403($this->request->method() === Request::POST ? 'erp199.types.manage' : 'erp199.types.view', TRUE);

        $service = Service_Erp199_Types::instance();
        $ref     = $this->request->param('id') ? $this->request->param('id') : $this->_query('code');

        if ($this->request->method() === Request::POST)
        {
            try
            {
                $type = $service->save((array) $this->request->post(), $this->actor());

                $this->_flash('success', 'Тип сохранён: '.$type['label']);

                $location = Route::url('erp199_index', array('action' => 'type', 'id' => $type['code']));
            }
            catch (Exception $e)
            {
                $this->_flash('error', $e->getMessage());

                $location = Route::url('erp199_index', array('action' => 'type', 'id' => $ref));
            }

            $this->redirect($location);
        }

        $type = NULL;

        if ($ref)
        {
            try
            {
                $type = $service->get($ref);
            }
            catch (Exception $e)
            {
                throw new HTTP_Exception_404($e->getMessage());
            }
        }

        $this->set_title($type ? 'Тип '.$type['label'] : 'Новый тип');
        $this->template->content = View::factory('erp199/type')
            ->set('type', $type)
            ->set('kinds', Service_Erp199_Targets::instance()->kinds())
            ->set('can_manage', $this->allows('erp199.types.manage', TRUE))
            ->set('messages', $this->_flash_messages());
    }

    /**
     * Журнал: кто, когда и что делал с наклейками и объектами.
     *
     * @return  void
     */
    public function action_activity()
    {
        $this->allow_or_403('erp199.activity.view');

        $filters = array(
            'q'      => $this->_query('q', ''),
            'action' => $this->_query('action'),
            'result' => $this->_query('result'),
            'code'   => $this->_query('code'),
        );

        $page   = max(1, (int) $this->_query('page', 1));
        $result = Service_Erp199_Tokens::instance()->journal($filters, self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        $this->set_title('Журнал QR-маркировки');
        $this->template->content = View::factory('erp199/activity')
            ->set('rows', $result)
            ->set('page', $page)
            ->set('filters', $filters)
            ->set('actions', Service_Erp199_Tokens::instance()->actions())
            ->set('url', Route::url('erp199_index', array('action' => 'activity')).'?')
            ->set('messages', $this->_flash_messages());
    }
}
