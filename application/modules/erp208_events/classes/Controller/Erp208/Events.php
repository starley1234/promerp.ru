<?php defined('SYSPATH') or die('No direct script access.'); 

/**
 * @package Erp208
 * @version 1.0.1
 * @author AI
 */
class Controller_Erp208_Events extends Controller_Erp208_Main {

    /**
     * @var bool Отключает автоматический рендеринг шаблона для AJAX-запросов.
     */
    public $auto_render = TRUE;

    /**
     * @var array Мета-данные страницы.
     */
    protected $_meta = array(
        'title'       => 'Управление событиями',
        'description' => 'Просмотр и управление событиями и подписками в системе ERP.',
        'keywords'    => 'ERP, события, EventBus, подписки, логирование'
    );

    /**
     * Выполняется перед каждым действием контроллера.
     * Устанавливает мета-данные и навигационное меню.
     */
    public function before()
    {
        parent::before();

        // Установка мета-данных
        $this->_meta['title'] = __('Event Management');
        $this->_meta['description'] = __('View and manage events and subscriptions in the ERP system.');
        $this->set_title($this->_meta['title']);
        $this->set_description($this->_meta['description']);
        $this->set_keywords($this->_meta['keywords']);
        
    }

    /**
     * Главная страница модуля. Отображает дашборд с виджетами.
     */
    public function action_index()
    {
        $this->redirect(Route::url('erp208_events', array('action' => 'list_events')));
    }

    /**
     * Отображает список всех зарегистрированных событий.
     */
    public function action_list_events()
    {
        if ( ! $this->can('erp208_events.list_events'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $this->_meta['title'] = __('Список событий');
        $this->set_title($this->_meta['title']);

        $query_params = $this->request->query();
        $per_page = isset($query_params['per_page']) ? (int) $query_params['per_page'] : 20;
        $search   = isset($query_params['search']) ? trim($query_params['search']) : '';

        $events_model = ORM::factory('Erp208_Event');

        if ( ! empty($search))
        {
            $events_model->where('event_name', 'LIKE', '%' . $search . '%');
        }

        $total_items = $events_model->reset(FALSE)->count_all();

        $pagination = Pagination::factory(array(
            'total_items'    => $total_items,
            'items_per_page' => $per_page,
            'current_page'   => array('source' => 'query_string', 'key' => 'page'),
        ));

        $events = $events_model
            ->order_by('id', 'DESC')
            ->limit($pagination->items_per_page)
            ->offset($pagination->offset)
            ->find_all();

        $view = View::factory('erp208/event/list')
            ->set('events', $events)
            ->set('pagination', $pagination)
            ->set('search', $search);
        
        $this->template->content = $view;
    }

    /**
     * Отображает детальную информацию о конкретном событии.
     */
    public function action_view_event()
    {
        $id = (int) $this->request->param('id');
        $event = ORM::factory('Erp208_Event', $id);

        if ( ! $this->can('erp208_events.view_event', $event))
        {
            throw new HTTP_Exception_403('Access denied');
        }

        if ( ! $event->loaded())
        {
            throw new HTTP_Exception_404(__('Event not found.'));
        }

        $this->_meta['title'] = __('Event Details: :event_name', array(':event_name' => $event->event_name));

        $view = View::factory('erp208/event/detail')
            ->set('event', $event);

        $this->template->content = $view;
    }

    /**
     * Вспомогательный метод для нормализации payload в массив.
     * 
     * @param mixed $payload
     * @return array
     */
    protected function _normalize_payload($payload)
    {
        
        if (empty($payload))
        {
            return array();
        }

        if (is_array($payload))
        {
            return $payload;
        }

        if (is_string($payload))
        {
            $decoded = json_decode($payload, TRUE);
            
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded))
            {
                return $decoded;
            }
            return array($payload);
            //return array('data' => $payload);
        }
		return array($payload);
        //return array('data' => $payload);
    }

    /**
     * Создание и публикация нового события.
     */
    public function action_create_event()
    {
        $event  = ORM::factory('Erp208_Event');

        if ( ! $this->can('erp208_events.create_event', $event))
        {
            throw new HTTP_Exception_403('Access denied');
        }

        $this->_meta['title'] = __('Create New Event');

        $errors = array();

        if ($post = $this->request->post())
        {
            try
            {
                $event->event_name = isset($post['event_name']) ? trim($post['event_name']) : '';
                
                $payload = $this->_normalize_payload(isset($post['payload']) ? $post['payload'] : array());
                
                $event->payload = json_encode($payload, JSON_UNESCAPED_UNICODE);
                $event->save();

                Service_Erp208_EventBus::instance()->publish($event->event_name, $payload);
                // Event::fire('erp208_events.created', $event);

                Session::instance()->set('success_message', __('Event ":event_name" created and published successfully!', array(':event_name' => $event->event_name)));
                // $this->redirect(Route::url('erp208_events', array('action' => 'list_events')));
            }
            catch (ORM_Validation_Exception $e)
            {
                $errors = $e->errors('models');
            }
            catch (Exception $e)
            {
                Session::instance()->set('error_message', $e->getMessage());
            }
        }

        $view = View::factory('erp208/event/form')
            ->set('event', $event)
            ->set('errors', $errors);

        $this->template->content = $view;
    }

    /**
     * Редактирование события.
     */
    public function action_edit_event()
    {
        $id = (int) $this->request->param('id');
        $event = ORM::factory('Erp208_Event', $id);

        if ( ! $this->can('erp208_events.edit_event', $event))
        {
            throw new HTTP_Exception_403('Access denied');
        }

        if ( ! $event->loaded())
        {
            throw new HTTP_Exception_404(__('Event not found.'));
        }

        $this->_meta['title'] = __('Edit Event: :event_name', array(':event_name' => $event->event_name));

        $errors = array();

        if ($post = $this->request->post())
        {
            try
            {
                $event->event_name = isset($post['event_name']) ? trim($post['event_name']) : '';
                
                $payload = $this->_normalize_payload(isset($post['payload']) ? $post['payload'] : array());

                $event->payload = json_encode($payload, JSON_UNESCAPED_UNICODE);
                $event->save();
                // Event::fire('erp208_events.updated', $event);

                Session::instance()->set('success_message', __('Event ":event_name" updated successfully!', array(':event_name' => $event->event_name)));
                $this->redirect(Route::url('erp208_events', array('action' => 'view_event', 'id' => $event->id)));
            }
            catch (ORM_Validation_Exception $e)
            {
                $errors = $e->errors('models');
            }
        }

        $view = View::factory('erp208/event/form')
            ->set('event', $event)
            ->set('errors', $errors);

        $this->template->content = $view;
    }

    /**
     * Удаление события.
     */
    public function action_delete_event()
    {
        $id = (int) $this->request->param('id');
        $event = ORM::factory('Erp208_Event', $id);

        if ( ! $this->can('erp208_events.delete_event', $event))
        {
            throw new HTTP_Exception_403('Access denied');
        }

        if ($event->loaded())
        {
            $event_name = $event->event_name;
            $event->delete();
            Event::fire('erp208_events.deleted', $event);
            Session::instance()->set('success_message', __('Event ":event_name" deleted.', array(':event_name' => $event_name)));
        }
        else
        {
            Session::instance()->set('error_message', __('Event not found.'));
        }

        $this->redirect(Route::url('erp208_events', array('action' => 'list_events')));
    }

    /**
     * Список подписчиков.
     */
    public function action_list_subscribers()
    {
        if ( ! $this->can('erp208_events.list_subscribers'))
        {
            throw new HTTP_Exception_403('Доступ запрещен');
        }

        $this->_meta['title'] = __('Список подписчиков');
        $this->set_title($this->_meta['title']);

        $query_params = $this->request->query();
        $per_page = isset($query_params['per_page']) ? (int) $query_params['per_page'] : 20;
        $search   = isset($query_params['search']) ? trim($query_params['search']) : '';

        $subscribers_model = ORM::factory('Erp208_Subscriber');

        if ( ! empty($search))
        {
            $subscribers_model->where_open()
                ->where('event_name', 'LIKE', '%' . $search . '%')
                ->or_where('module_id', 'LIKE', '%' . $search . '%')
                ->where_close();
        }

        $total_items = $subscribers_model->reset(FALSE)->count_all();

        $pagination = Pagination::factory(array(
            'total_items'    => $total_items,
            'items_per_page' => $per_page,
            'current_page'   => array('source' => 'query_string', 'key' => 'page'),
        ));

        $subscribers = $subscribers_model
            ->order_by('id', 'DESC')
            ->limit($pagination->items_per_page)
            ->offset($pagination->offset)
            ->find_all();

        $view = View::factory('erp208/subscriber/list')
            ->set('subscribers', $subscribers)
            ->set('pagination', $pagination)
            ->set('search', $search);

        $this->template->content = $view;
    }

    /**
     * Управление подпиской.
     */
    public function action_manage_subscription()
    {
        $id = (int) $this->request->param('id');
        $subscriber = ORM::factory('Erp208_Subscriber', $id);

        if ( ! $this->can('erp208_events.manage_subscription', $subscriber))
        {
            throw new HTTP_Exception_403('Access denied');
        }

        $this->_meta['title'] = $subscriber->loaded() ? __('Edit Subscription') : __('Create New Subscription');

        $errors = array();
        $config = Kohana::$config->load('erp208_events');
        $available_events = $config->get('event_bus')['predefined_event_names'];
        
        // Получаем список доступных модулей из префиксов событий
        $available_modules = array();
        foreach ($available_events as $event_name) {
            $parts = explode('.', $event_name);
            if (count($parts) > 0) {
                $available_modules[] = $parts[0];
            }
        }
        $available_modules = array_unique($available_modules);

        if ($post = $this->request->post())
        {
            try
            {
                $subscriber->values($post, array('event_name', 'module_id', 'callback_method', 'is_active', 'is_notification', 'description'));
                
                if ( ! $subscriber->loaded() && $subscriber->is_duplicate($subscriber->event_name, $subscriber->module_id, $subscriber->callback_method))
                {
                    $errors['event_name'] = __('A subscription with these details already exists.');
                }
                else
                {
                    $subscriber->save();
                    Event::fire('erp208_events.subscription_saved', $subscriber);
                    Session::instance()->set('success_message', __('Subscription saved.'));
                    $this->redirect(Route::url('erp208_events', array('action' => 'list_subscribers')));
                }
            }
            catch (ORM_Validation_Exception $e)
            {
                $errors = $e->errors('models');
            }
        }

        $view = View::factory('erp208/subscriber/form')
            ->set('subscriber', $subscriber)
            ->set('available_events', $available_events)
            ->set('available_modules', $available_modules)
            ->set('errors', $errors);

        $this->template->content = $view;
    }

    /**
     * Удаление подписки.
     */
    public function action_delete_subscription()
    {
        $id = (int) $this->request->param('id');
        $subscriber = ORM::factory('Erp208_Subscriber', $id);

        if ( ! $this->can('erp208_events.delete_subscription', $subscriber))
        {
            throw new HTTP_Exception_403('Access denied');
        }

        if ($subscriber->loaded())
        {
            $subscriber->delete();
            Event::fire('erp208_events.subscription_deleted', $subscriber);
            Session::instance()->set('success_message', __('Subscription deleted.'));
        }
        else
        {
            Session::instance()->set('error_message', __('Subscription not found.'));
        }

        $this->redirect(Route::url('erp208_events', array('action' => 'list_subscribers')));
    }
}