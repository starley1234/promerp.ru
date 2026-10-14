<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Контроллер мониторинга очереди отправки уведомлений.
 * Модуль ERP217: Центр уведомлений
 * 
 * @package    ERP217
 * @category   Controller
 */
class Controller_Erp217_Notification extends Controller_Erp217_Main {

    /**
     * Проверка прав доступа перед выполнением действий
     */
    public function before()
    {
        parent::before();

        if ( ! $this->can('erp217_notification_center.view'))
        {
            throw new HTTP_Exception_403('У вас недостаточно прав для просмотра очереди сообщений.');
        }
    }

    /**
     * Список сообщений в очереди с фильтрацией
     */
    public function action_index()
    {
        $this->_meta['title'] = __('Мониторинг очереди уведомлений');
        $this->_meta['description'] = __('Просмотр и управление очередью отправки уведомлений');

        // Обновляем статистику перед отображением
        ORM::factory('Erp217_Notification')->update_statistics();

        // Получение параметров фильтрации
        $status = $this->request->query('status');
        $channel = $this->request->query('channel');
        $recipient_id = $this->request->query('recipient_id');

        // Инициализация модели с фильтрами
        $notification_items = ORM::factory('Erp217_Notification')
            ->where('is_deleted', '=', 0);

        if ($status)
        {
            $notification_items->where('status', '=', $status);
        }

        if ($channel)
        {
            $notification_items->where('channel', '=', $channel);
        }

        if ($recipient_id)
        {
            $notification_items->where('recipient_id', '=', (int)$recipient_id);
        }

        // Пагинация
        $count_all = clone $notification_items;
        $total_items = $count_all->count_all();

        $pagination = Pagination::factory(array(
            'total_items'    => $total_items,
            'items_per_page' => 30,
        ));

        $items = $notification_items
            ->limit($pagination->items_per_page)
            ->offset($pagination->offset)
            ->order_by('created_at', 'DESC')
            ->find_all();

        // Получение агрегированной статистики для вида
        $stats_row = DB::select()->from('erp_217_statistics')->where('channel', '=', 'all')->execute()->current();
        $stats = array(
            'total'      => isset($stats_row['total_in_notification']) ? $stats_row['total_in_notification'] : 0,
            'pending'    => isset($stats_row['pending_count']) ? $stats_row['pending_count'] : 0,
            'failed'     => isset($stats_row['error_count']) ? $stats_row['error_count'] : 0,
            'sent_today' => isset($stats_row['sent_last_24h']) ? $stats_row['sent_last_24h'] : 0,
        );
        
        $profiles = Model_User::get_all_names();

        // Передача данных в вид
        $this->template->content = View::factory('erp217/notification/list')
        // $this->template->content = View::factory('erp217/notification/list_outlook')
            ->set('items', $items)
            ->set('profiles', $profiles)
            ->set('pagination', $pagination)
            ->set('stats', $stats)
            ->set('filters', array(
                'status'  => $status,
                'channel' => $channel,
            ));
    }

    /**
     * Просмотр детальной информации о записи в очереди и логов отправки
     */
    public function action_view()
    {
        $id = $this->request->param('id');
        $item = ORM::factory('Erp217_Notification', $id);

        if ( ! $item->loaded())
        {
            throw new HTTP_Exception_404('Запись в очереди не найдена');
        }

        $this->_meta['title'] = __('Детали уведомления #:id', array(':id' => $id));

        // Получаем логи, связанные с этой записью. 
        // Используем фильтр по queue_id, так как в БД колонка называется queue_id
        $logs = ORM::factory('Erp217_Log')
            ->where('queue_id', '=', $item->id)
            ->order_by('created_at', 'DESC')
            ->find_all();

        $this->template->content = View::factory('erp217/notification/view')
            ->set('item', $item)
            ->set('logs', $logs);
    }

    /**
     * Ручной перезапуск отправки сообщения (сброс статуса в pending)
     */
    public function action_retry()
    {
        $id = $this->request->param('id');
        $item = ORM::factory('Erp217_Notification', $id);

        if ($item->loaded() AND $this->can('erp217_notification_center.edit', $item))
        {
            $item->status = Model_Erp217_Notification::STATUS_PENDING;
            $item->attempts = 0;
            $item->last_error = NULL;
            $item->scheduled_at = date('Y-m-d H:i:s');
            $item->save();

            Event::fire('erp217_notification_center.retry', $item);
            Message::success(__('Сообщение поставлено в очередь на повторную отправку'));
        }

        $this->redirect('erp217_notification/index');
    }

    /**
     * AJAX исполнение элемента очереди
     */
    public function action_ajax_execute()
    {
        $this->auto_render = FALSE;
        $this->response->headers('Content-Type', 'application/json');

        $id = $this->request->post('id');
        $item = ORM::factory('Erp217_Notification', $id);

        if ( ! $item->loaded())
        {
            $this->response->body(json_encode(['success' => false, 'error' => 'Item not found']));
            return;
        }

        if ( ! $this->can('erp217_notification_center.edit', $item))
        {
            $this->response->body(json_encode(['success' => false, 'error' => 'Access denied']));
            return;
        }

        try 
        {
            $bus = Service_Erp217_Bus::instance();
            $result = $bus->process_item($item);

            if ($result)
            {
                Event::fire('erp217_notification_center.executed', $item);
                $this->response->body(json_encode(['success' => true]));
            }
            else
            {
                $item->reload();
                $this->response->body(json_encode(['success' => false, 'error' => $item->last_error]));
            }
        }
        catch (Exception $e)
        {
            $this->response->body(json_encode(['success' => false, 'error' => $e->getMessage()]));
        }
    }

    /**
     * AJAX удаление элемента очереди
     */
    public function action_ajax_delete()
    {
        $this->auto_render = FALSE;
        $this->response->headers('Content-Type', 'application/json');

        if ($this->request->method() !== HTTP_Request::POST)
        {
            $this->response->body(json_encode(['success' => false, 'error' => 'Method not allowed']));
            return;
        }

        $id = $this->request->post('id');
        $item = ORM::factory('Erp217_Notification', $id);

        if ($item->loaded() AND $this->can('erp217_notification_center.delete', $item))
        {
            try 
            {
                $item->is_deleted = 1;
                $item->save();
                
                Event::fire('erp217_notification_center.delete', $item);
                $this->response->body(json_encode(['success' => true]));
            }
            catch (Exception $e)
            {
                $this->response->body(json_encode(['success' => false, 'error' => $e->getMessage()]));
            }
        }
        else
        {
            $this->response->body(json_encode(['success' => false, 'error' => 'Item not found or access denied']));
        }
    }

    /**
     * Очистка старых логов и успешно отправленных сообщений
     */
    public function action_cleanup()
    {
        if ( ! $this->can('erp217_notification_center.delete'))
        {
            throw new HTTP_Exception_403('Access denied');
        }

        $date_limit = date('Y-m-d H:i:s', strtotime('-30 days'));
        
        $items_to_delete = ORM::factory('Erp217_Notification')
            ->where('status', '=', Model_Erp217_Notification::STATUS_SENT)
            ->where('created_at', '<', $date_limit)
            ->find_all();

        $count = 0;
        foreach ($items_to_delete as $item)
        {
            $item->delete();
            $count++;
        }
		
        $this->template->content = __('Очищено записей: :count', array(':count' => $count));
    }
}