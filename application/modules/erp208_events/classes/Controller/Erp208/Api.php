<?php defined('SYSPATH') or die('No direct script access.');

/**
 * @package Erp208
 * @version 1.0.0
 * @author AI
 */
class Controller_Erp208_Api extends Controller_Base {

    /**
     * @var bool Отключаем автоматический рендеринг шаблона для API-контроллера.
     */
    public $auto_render = FALSE;

    /**
     * @var Service_Erp208_EventBus Экземпляр сервиса шины событий.
     */
    protected $_event_bus;

    /**
     * Инициализация контроллера.
     * Получаем экземпляр EventBus.
     */
    public function before()
    {
        parent::before();

        // Устанавливаем тип контента для всех ответов API
        $this->response->headers('Content-Type', 'application/json; charset=' . Kohana::$charset);

        // Получаем экземпляр сервиса EventBus
        $this->_event_bus = Service_Erp208_EventBus::instance();
    }

    /**
     * Обрабатывает POST-запрос для публикации нового события.
     *
     * POST-параметры:
     * - event_name (string, required): Название события (например, 'erp502.task_done').
     * - payload (json string, optional): JSON-строка с данными события.
     *
     * @return void
     */
    public function action_publish_event()
    {
        if ( ! $this->can('erp208_events.publish_event'))
        {
            $this->response->status(403);
            $this->response->body(json_encode(array(
                'status'  => 'error',
                'message' => 'Доступ запрещен. У пользователя нет прав на публикацию событий.'
            )));
            return;
        }

        if ($post = $this->request->post())
        {
            $event_name = isset($post['event_name']) ? (string) $post['event_name'] : NULL;
            $payload_raw = isset($post['payload']) ? (string) $post['payload'] : '{}';

            if (empty($event_name))
            {
                $this->response->status(400);
                $this->response->body(json_encode(array(
                    'status'  => 'error',
                    'message' => 'Название события обязательно.'
                )));
                return;
            }

            $payload = json_decode($payload_raw, TRUE);
            if (json_last_error() !== JSON_ERROR_NONE)
            {
                $this->response->status(400);
                $this->response->body(json_encode(array(
                    'status'  => 'error',
                    'message' => 'Неверный формат JSON для данных события: ' . json_last_error_msg()
                )));
                return;
            }

            try
            {
                $event_model = $this->_event_bus->publish($event_name, $payload);

                $this->response->status(200);
                $this->response->body(json_encode(array(
                    'status'    => 'success',
                    'message'   => 'Событие успешно опубликовано.',
                    'event_name' => $event_name,
                    'event_id'  => $event_model->loaded() ? $event_model->id : NULL
                )));
                Event::fire('erp208_events.published', $event_model);
            }
            catch (Exception $e)
            {
                Kohana::$log->add(Log::ERROR, 'Ошибка публикации события через API: :error', array(':error' => $e->getMessage()));
                $this->response->status(500);
                $this->response->body(json_encode(array(
                    'status'  => 'error',
                    'message' => 'Внутренняя ошибка при публикации события.'
                )));
            }
        }
        else
        {
            $this->response->status(405); // Method Not Allowed
            $this->response->body(json_encode(array(
                'status'  => 'error',
                'message' => 'Для этой конечной точки разрешены только POST-запросы.'
            )));
        }
    }

    /**
     * Возвращает список событий в формате JSON.
     *
     * GET-параметры:
     * - page (int, optional): Номер страницы для пагинации, по умолчанию 1.
     * - limit (int, optional): Количество событий на странице, по умолчанию 10.
     * - event_name (string, optional): Фильтр по названию события.
     * - start_date (string, optional): Фильтр по дате начала (YYYY-MM-DD HH:MM:SS).
     * - end_date (string, optional): Фильтр по дате окончания (YYYY-MM-DD HH:MM:SS).
     *
     * @return void
     */
    public function action_get_events()
    {
        if ( ! $this->can('erp208_events.view_events'))
        {
            $this->response->status(403);
            $this->response->body(json_encode(array(
                'status'  => 'error',
                'message' => 'Доступ запрещен. У пользователя нет прав на просмотр событий.'
            )));
            return;
        }

        $query_params = $this->request->query();

        $page = isset($query_params['page']) ? (int) $query_params['page'] : 1;
        $limit = isset($query_params['limit']) ? (int) $query_params['limit'] : 10;
        $event_name_filter = isset($query_params['event_name']) ? (string) $query_params['event_name'] : NULL;
        $start_date_filter = isset($query_params['start_date']) ? (string) $query_params['start_date'] : NULL;
        $end_date_filter = isset($query_params['end_date']) ? (string) $query_params['end_date'] : NULL;

        // Убедимся, что page и limit положительны
        $page = max(1, $page);
        $limit = max(1, $limit);

        try
        {
            $event_model = ORM::factory('Erp208_Event');

            if ( ! empty($event_name_filter))
            {
                $event_model->where('event_name', 'LIKE', '%' . $event_name_filter . '%');
            }
            if ( ! empty($start_date_filter))
            {
                $event_model->where('created_at', '>=', $start_date_filter);
            }
            if ( ! empty($end_date_filter))
            {
                $event_model->where('created_at', '<=', $end_date_filter);
            }

            $total_events = (clone $event_model)->count_all();

            $events = $event_model
                ->order_by('created_at', 'DESC')
                ->limit($limit)
                ->offset(($page - 1) * $limit)
                ->find_all();

            $data = array();
            foreach ($events as $event)
            {
                $data[] = array(
                    'id'         => $event->id,
                    'event_name' => $event->event_name,
                    'payload'    => $event->get_payload_array(), // Декодируем payload
                    'created_at' => $event->created_at,
                );
            }

            $this->response->status(200);
            $this->response->body(json_encode(array(
                'status'       => 'success',
                'total_items'  => $total_events,
                'current_page' => $page,
                'per_page'     => $limit,
                'total_pages'  => ceil($total_events / $limit),
                'data'         => $data
            )));
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::ERROR, 'Ошибка получения списка событий через API: :error', array(':error' => $e->getMessage()));
            $this->response->status(500);
            $this->response->body(json_encode(array(
                'status'  => 'error',
                'message' => 'Внутренняя ошибка при получении списка событий.'
            )));
        }
    }

    /**
     * Возвращает детальную информацию о конкретном событии в формате JSON.
     *
     * URL-параметры:
     * - id (int, required): ID события.
     *
     * @return void
     */
    public function action_get_event()
    {
        if ( ! $this->can('erp208_events.view_event'))
        {
            $this->response->status(403);
            $this->response->body(json_encode(array(
                'status'  => 'error',
                'message' => 'Доступ запрещен. У пользователя нет прав на просмотр события.'
            )));
            return;
        }

        $event_id = (int) $this->request->param('id');

        if (empty($event_id))
        {
            $this->response->status(400);
            $this->response->body(json_encode(array(
                'status'  => 'error',
                'message' => 'Идентификатор события обязателен.'
            )));
            return;
        }

        try
        {
            $event = ORM::factory('Erp208_Event', $event_id);

            if ( ! $event->loaded())
            {
                $this->response->status(404);
                $this->response->body(json_encode(array(
                    'status'  => 'error',
                    'message' => 'Событие не найдено.'
                )));
                return;
            }

            $this->response->status(200);
            $this->response->body(json_encode(array(
                'status'     => 'success',
                'data'       => array(
                    'id'         => $event->id,
                    'event_name' => $event->event_name,
                    'payload'    => $event->get_payload_array(), // Декодируем payload
                    'created_at' => $event->created_at,
                )
            )));
        }
        catch (Exception $e)
        {
            Kohana::$log->add(Log::ERROR, 'Ошибка получения события :id через API: :error', array(
                ':id'    => $event_id,
                ':error' => $e->getMessage()
            ));
            $this->response->status(500);
            $this->response->body(json_encode(array(
                'status'  => 'error',
                'message' => 'Внутренняя ошибка при получении события.'
            )));
        }
    }

    /**
     * Регистрирует новую подписку на событие.
     *
     * POST-параметры:
     * - event_name (string, required): Название события, на которое подписываемся.
     * - module_id (string, required): Идентификатор модуля-подписчика.
     * - callback_method (string, required): Метод или функция, которая будет вызвана.
     * - is_active (bool, optional): Активна ли подписка, по умолчанию TRUE.
     *
     * @return void
     */
    public function action_subscribe()
    {
        if ( ! $this->can('erp208_events.manage_subscriptions'))
        {
            $this->response->status(403);
            $this->response->body(json_encode(array(
                'status'  => 'error',
                'message' => 'Доступ запрещен. У пользователя нет прав на управление подписками.'
            )));
            return;
        }

        if ($post = $this->request->post())
        {
            $event_name      = isset($post['event_name']) ? (string) $post['event_name'] : NULL;
            $module_id       = isset($post['module_id']) ? (string) $post['module_id'] : NULL;
            $callback_method = isset($post['callback_method']) ? (string) $post['callback_method'] : NULL;
            $is_active       = isset($post['is_active']) ? (bool) $post['is_active'] : TRUE;

            if (empty($event_name) || empty($module_id) || empty($callback_method))
            {
                $this->response->status(400);
                $this->response->body(json_encode(array(
                    'status'  => 'error',
                    'message' => 'Название события, идентификатор модуля и метод обратного вызова обязательны.'
                )));
                return;
            }

            try
            {
                $subscriber = ORM::factory('Erp208_Subscriber');

                // Проверяем на дубликат
                if ($subscriber->is_duplicate($event_name, $module_id, $callback_method))
                {
                    $this->response->status(409); // Conflict
                    $this->response->body(json_encode(array(
                        'status'  => 'error',
                        'message' => 'Подписка с такими параметрами уже существует.'
                    )));
                    return;
                }

                $subscriber->event_name      = $event_name;
                $subscriber->module_id       = $module_id;
                $subscriber->callback_method = $callback_method;
                $subscriber->is_active       = $is_active;
                $subscriber->save();

                $this->response->status(201); // Created
                $this->response->body(json_encode(array(
                    'status'        => 'success',
                    'message'       => 'Подписка успешно создана.',
                    'subscriber_id' => $subscriber->id
                )));
                Event::fire('erp208_events.subscribed', $subscriber);
            }
            catch (ORM_Validation_Exception $e)
            {
                $errors = $e->errors('models');
                $this->response->status(400);
                $this->response->body(json_encode(array(
                    'status'  => 'error',
                    'message' => 'Ошибка валидации.',
                    'errors'  => $errors
                )));
            }
            catch (Exception $e)
            {
                Kohana::$log->add(Log::ERROR, 'Ошибка создания подписки через API: :error', array(':error' => $e->getMessage()));
                $this->response->status(500);
                $this->response->body(json_encode(array(
                    'status'  => 'error',
                    'message' => 'Внутренняя ошибка при создании подписки.'
                )));
            }
        }
        else
        {
            $this->response->status(405); // Method Not Allowed
            $this->response->body(json_encode(array(
                'status'  => 'error',
                'message' => 'Для этой конечной точки разрешены только POST-запросы.'
            )));
        }
    }

    /**
     * Отменяет подписку на событие (удаляет или деактивирует).
     *
     * POST-параметры:
     * - subscriber_id (int, required): ID подписки для отмены.
     * - deactivate_only (bool, optional): Если TRUE, подписка деактивируется, но не удаляется. По умолчанию FALSE (удаление).
     *
     * @return void
     */
    public function action_unsubscribe()
    {
        if ( ! $this->can('erp208_events.manage_subscriptions'))
        {
            $this->response->status(403);
            $this->response->body(json_encode(array(
                'status'  => 'error',
                'message' => 'Доступ запрещен. У пользователя нет прав на управление подписками.'
            )));
            return;
        }

        if ($post = $this->request->post())
        {
            $subscriber_id   = isset($post['subscriber_id']) ? (int) $post['subscriber_id'] : NULL;
            $deactivate_only = isset($post['deactivate_only']) ? (bool) $post['deactivate_only'] : FALSE;

            if (empty($subscriber_id))
            {
                $this->response->status(400);
                $this->response->body(json_encode(array(
                    'status'  => 'error',
                    'message' => 'Идентификатор подписки обязателен.'
                )));
                return;
            }

            try
            {
                $subscriber = ORM::factory('Erp208_Subscriber', $subscriber_id);

                if ( ! $subscriber->loaded())
                {
                    $this->response->status(404);
                    $this->response->body(json_encode(array(
                        'status'  => 'error',
                        'message' => 'Подписка не найдена.'
                    )));
                    return;
                }

                if ($deactivate_only)
                {
                    $subscriber->is_active = FALSE;
                    $subscriber->save();
                    $message = 'Подписка успешно деактивирована.';
                    Event::fire('erp208_events.deactivated', $subscriber);
                }
                else
                {
                    $subscriber_data = clone $subscriber; // Клонируем для передачи в событие
                    $subscriber->delete();
                    $message = 'Подписка успешно удалена.';
                    Event::fire('erp208_events.unsubscribed', $subscriber_data);
                }

                $this->response->status(200);
                $this->response->body(json_encode(array(
                    'status'  => 'success',
                    'message' => $message
                )));
            }
            catch (Exception $e)
            {
                Kohana::$log->add(Log::ERROR, 'Ошибка отмены/деактивации подписки :id через API: :error', array(
                    ':id'    => $subscriber_id,
                    ':error' => $e->getMessage()
                ));
                $this->response->status(500);
                $this->response->body(json_encode(array(
                    'status'  => 'error',
                    'message' => 'Внутренняя ошибка при обработке подписки.'
                )));
            }
        }
        else
        {
            $this->response->status(405); // Method Not Allowed
            $this->response->body(json_encode(array(
                'status'  => 'error',
                'message' => 'Для этой конечной точки разрешены только POST-запросы.'
            )));
        }
    }
}