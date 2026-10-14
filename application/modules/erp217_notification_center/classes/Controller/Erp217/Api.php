<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Controller_Erp217_Api - REST API контроллер для Центра уведомлений.
 * 
 * Обеспечивает:
 * 1. Получение статусов сообщений в очереди.
 * 2. Триггер тестовой отправки уведомлений.
 * 3. Получение краткой статистики для фронтенда.
 * 4. Управление статусами прочтения (Push).
 * 
 * @package    ERP217
 * @category   Controller
 * @author     Центр уведомлений
 */
class Controller_Erp217_Api extends Controller_Base {

    /**
     * Отключаем авто-рендер шаблона для всех API методов
     */
    public function before()
    {
        parent::before();
        $this->auto_render = FALSE;
        
        // Устанавливаем заголовок JSON
        $this->response->headers('Content-Type', 'application/json; charset=utf-8');
    }

    /**
     * Получение статуса конкретного сообщения или списка последних статусов.
     * GET /erp217_api/notification_status?id=123
     */
    public function action_notification_status()
    {
        $id = $this->request->query('id');
        
        if ($id) 
        {
            $item = ORM::factory('Erp217_Notification', $id);
            
            if ( ! $item->loaded())
            {
                return $this->_json_error('Message not found', 404);
            }

            // Проверка прав: админ видит всё, пользователь только своё
            if ( ! $this->has_role('admin') AND $item->recipient_id != $this->_current_user->id)
            {
                return $this->_json_error('Access denied', 403);
            }

            $result = [
                'id'         => $item->id,
                'status'     => $item->status,
                'channel'    => $item->channel,
                'attempts'   => $item->attempts,
                'sent_at'    => $item->sent_at,
                'last_error' => $item->last_error
            ];
        }
        else 
        {
            // Если ID не указан, возвращаем последние 10 уведомлений текущего пользователя
            $items = ORM::factory('Erp217_Notification')
                ->where('recipient_id', '=', $this->_current_user->id)
                ->order_by('created_at', 'DESC')
                ->limit(10)
                ->find_all();

            $result = [];
            foreach ($items as $item)
            {
                $result[] = [
                    'id'         => $item->id,
                    'subject'    => $item->subject,
                    'status'     => $item->status,
                    'created_at' => $item->created_at
                ];
            }
        }

        $this->response->body(json_encode(['status' => 'success', 'data' => $result]));
    }

    /**
     * Получение полного текста сообщения (для AJAX подгрузки длинных сообщений)
     * GET /erp217_api/get_message_body/<id>
     */
    public function action_get_message_body()
    {
        $id = $this->request->param('id');
        $item = ORM::factory('Erp217_Notification', $id);

        if ( ! $item->loaded())
        {
            return $this->_json_error('Message not found', 404);
        }

        // Проверка прав: админ или получатель
        if ( ! $this->has_role('admin') AND $item->recipient_id != $this->_current_user->id)
        {
            return $this->_json_error('Access denied', 403);
        }

        // Получаем данные из payload
        $payload = $item->get_payload();
        $body = '';

        // Если в очереди уже есть отрендеренное тело (сохраненное Bus при создании)
        if (isset($payload['body']))
        {
            $body = $payload['body'];
        }
        // Если тела нет, но есть шаблон и сырые данные - рендерим на лету
        elseif ($item->template->loaded() AND isset($payload['raw_data']))
        {
            $bus = Service_Erp217_Bus::instance();
            $body = $bus->render_template($item->template->body, $payload['raw_data']);
        }
        // Фолбэк на сырой payload
        else
        {
            $body = $item->payload;
        }

        $this->response->body(json_encode([
            'status' => 'success',
            'data'   => [
                'id'      => $item->id,
                'subject' => $item->subject,
                'body'    => $body
            ]
        ]));
    }

    /**
     * Тестовый триггер отправки уведомления.
     * GET /erp217_api/test_send?alias=welcome&user_id=1
     */
    public function action_test_send()
    {
        // Только для администраторов или менеджеров
        if ( ! $this->has_any_role(['admin', 'manager', 'senior_manager']))
        {
            return $this->_json_error('Insufficient permissions', 403);
        }

        $alias   = $this->request->query('alias');
        $user_id = $this->request->query('user_id') ?: $this->_current_user->id;
        
        if (empty($alias))
        {
            return $this->_json_error('Missing template alias', 400);
        }

        // Данные для подстановки в шаблон
        $test_data = [
            'user_name' => 'Тестовый Пользователь',
            'date'      => date('d.m.Y H:i'),
            'test_mode' => 'true'
        ];

        try 
        {
            $bus = Service_Erp217_Bus::instance();
            $notification_ids = $bus->send($alias, $user_id, $test_data);

            if (empty($notification_ids))
            {
                return $this->_json_error('No active subscriptions or templates found for this event/user', 404);
            }

            $this->response->body(json_encode([
                'status' => 'success',
                'message' => 'Notification(s) added to notification',
                'notification_ids' => $notification_ids
            ]));
        }
        catch (Exception $e)
        {
            return $this->_json_error($e->getMessage(), 500);
        }
    }

    /**
     * Получение статистики очереди (для дашборда админа)
     * GET /erp217_api/stats
     */
    public function action_stats()
    {
        if ( ! $this->has_role('admin'))
        {
            return $this->_json_error('Forbidden', 403);
        }

        $stats = DB::select(
                [DB::expr('COUNT(id)'), 'total'],
                [DB::expr("SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END)"), 'pending'],
                [DB::expr("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END)"), 'failed'],
                [DB::expr("SUM(CASE WHEN status = 'sent' AND sent_at >= CURRENT_DATE THEN 1 ELSE 0 END)"), 'sent_today']
            )
            ->from('erp_217_notification')
            ->where('is_deleted', '=', 0)
            ->execute()
            ->current();

        $this->response->body(json_encode(['status' => 'success', 'data' => $stats]));
    }

    /**
     * Принудительное обновление агрегированной статистики
     * POST /erp217_api/update_statistics
     */
    public function action_update_statistics()
    {
        if ( ! $this->has_role('admin'))
        {
            return $this->_json_error('Forbidden', 403);
        }

        $channel = $this->request->post('channel') ?: 'all';
        
        try 
        {
            ORM::factory('Erp217_Notification')->update_statistics($channel);
            $this->response->body(json_encode(['status' => 'success', 'message' => 'Statistics updated for channel: ' . $channel]));
        }
        catch (Exception $e)
        {
            return $this->_json_error($e->getMessage(), 500);
        }
    }

    /**
     * Получить количество непрочитанных уведомлений
     * GET /erp217_api/unread_count
     */
    public function action_unread_count()
    {
        $count = Service_Erp217_Push::get_unread_count($this->_current_user->id);
        $this->response->body(json_encode(['status' => 'success', 'count' => (int)$count]));
    }

    /**
     * Сбросить счетчик уведомлений для текущего пользователя
     * POST /erp217_api/mark_all_read
     */
    public function action_mark_all_read()
    {
        Service_Erp217_Push::reset_unread_count($this->_current_user->id);
        $this->response->body(json_encode(['status' => 'success']));
    }

    /**
     * Переключить статус прочтения конкретного уведомления (toggle)
     * POST /erp217_api/toggle_read/<id>
     */
    public function action_toggle_read()
    {
        $id = $this->request->param('id');
        $notification = ORM::factory('Erp217_Notification', $id);
        
        if ($notification->loaded() && $notification->recipient_id == $this->_current_user->id) 
        {
            // Переключаем статус
            $notification->is_read = $notification->is_read ? 0 : 1; 
            $notification->save();

            // Обновляем счетчик непрочитанных
            $push_service = new Service_Erp217_Push();
            $actual_count = $push_service->count_actual_unread($this->_current_user->id);
            $push_service->update_unread_counter($this->_current_user->id, $actual_count);

            $this->response->body(json_encode([
                'status' => 'success', 
                'is_read' => (int)$notification->is_read,
                'unread_count' => $actual_count
            ]));
        } 
        else 
        {
            $this->_json_error('Not found', 404);
        }
    }

    /**
     * Вспомогательный метод для вывода ошибок в JSON
     */
    protected function _json_error($message, $code = 400)
    {
        $this->response->status($code);
        $this->response->body(json_encode([
            'status' => 'error',
            'error'  => $message
        ]));
        return NULL;
    }
}