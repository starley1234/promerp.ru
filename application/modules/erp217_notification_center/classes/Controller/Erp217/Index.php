<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Controller_Erp217_Index - Основной интерфейс пользователя в Центре уведомлений.
 * 
 * Реализует:
 * 1. Просмотр истории уведомлений (из очереди и логов).
 * 2. Управление персональными подписками на системные события.
 * 3. Управление подписками на конкретные сущности (EntitySubscription).
 * 
 * @package    ERP217
 * @category   Controller
 * @author     Центр уведомлений
 */
class Controller_Erp217_Index extends Controller_Erp217_Main {

    /**
     * Список последних уведомлений пользователя (Push).
     */
    public function action_index()
    {
        $this->set_title(__('Мои уведомления'));
        
        // Получаем вид из куки напрямую через $_COOKIE, по умолчанию 'list_outlook'
        $view_mode = isset($_COOKIE['erp217_notification_view']) ? $_COOKIE['erp217_notification_view'] : 'list_outlook';
        
        $view_map = [
            'list_standard'     => 'erp217/index/list_standard',
            'list_outlook' 		=> 'erp217/index/list_outlook',
            'list_compact'     	=> 'erp217/index/list_compact'
        ];

        $view_name = isset($view_map[$view_mode]) ? $view_map[$view_mode] : 'erp217/index/list_standard';

        $this->_render_list(['channel' => 'push'], $view_name);
    }


    /**
     * Общий метод для рендеринга списка с фильтрацией и пагинацией
     */
    protected function _render_list(array $fixed_filters = [], $view_name = NULL)
    {
        $query = ORM::factory('Erp217_Notification')
            ->where('recipient_id', '=', $this->_current_user->id)
            ->where('is_deleted', '=', 0);

        // Применяем фиксированные фильтры (например, канал push для index)
        foreach ($fixed_filters as $field => $value) {
            $query->where($field, '=', $value);
        }

        // Фильтр: Только непрочитанные
        if ($this->request->query('unread') === '1') {
            $query->where('is_read', '=', 0);
        }

        // Фильтр: По содержимому subject
        if ($subject = $this->request->query('subject')) {
            $query->where('subject', 'LIKE', '%' . $subject . '%');
        }

        // Клонируем для подсчета общего количества перед пагинацией
        $count_query = clone $query;
        $total_items = $count_query->count_all();

        // Пагинация
        $pagination = Pagination::factory([
            'total_items'    => $total_items,
            'items_per_page' => 20,
            'view'           => 'pagination/floating',
        ]);

        $notifications = $query
            ->order_by('created_at', 'DESC')
            ->limit($pagination->items_per_page)
            ->offset($pagination->offset)
            ->find_all();

        if ($view_name === NULL) {
            $view_name = (isset($fixed_filters['channel']) && $fixed_filters['channel'] == 'push') 
                ? 'erp217/index/list_standard' 
                : 'erp217/index/list_compact';
        }

        $this->template->content = View::factory($view_name)
            ->set('notifications', $notifications)
            ->set('pagination', $pagination)
            ->set('filters', $this->request->query());
    }

    

    /**
     * Сохранение настроек подписок (AJAX).
     */
    public function action_save_subscription()
    {
        $this->auto_render = FALSE;

        if ($this->request->method() !== Request::POST) {
            throw (new HTTP_Exception_405('Method Not Allowed'))->allowed(array('POST'));
        }

        $event_type = $this->request->post('event_type');
        $channel    = $this->request->post('channel');
        $is_enabled = (int) $this->request->post('is_enabled');

        try {
            $subscription = ORM::factory('Erp217_Subscription')
                ->where('user_id', '=', $this->_current_user->id)
                ->where('event_type', '=', $event_type)
                ->where('channel', '=', $channel)
                ->find();

            $subscription->user_id    = $this->_current_user->id;
            $subscription->event_type = $event_type;
            $subscription->channel    = $channel;
            $subscription->is_enabled = $is_enabled;
            
            if ( ! $subscription->loaded()) {
                $subscription->created_by = $this->_current_user->id;
            }
            
            $subscription->save();

            $this->response->body(json_encode([
                'status'  => 'success',
                'message' => __('Настройки обновлены')
            ]));

        } catch (ORM_Validation_Exception $e) {
            $this->response->status(400)->body(json_encode([
                'status' => 'error',
                'errors' => $e->errors('models')
            ]));
        } catch (Exception $e) {
            $this->response->status(500)->body(json_encode([
                'status'  => 'error',
                'message' => $e->getMessage()
            ]));
        }
    }

    /**
     * Управление подпиской на конкретную сущность (AJAX).
     * Используется для "Подписаться на сделку/задачу"
     */
    public function action_toggle_entity_subscription()
    {
        $this->auto_render = FALSE;

        if ($this->request->method() !== Request::POST) {
            throw (new HTTP_Exception_405('Method Not Allowed'))->allowed(array('POST'));
        }

        $entity_type = $this->request->post('entity_type');
        $entity_id   = (int) $this->request->post('entity_id');
        $subscribe   = (int) $this->request->post('subscribe'); // 1 - подписаться, 0 - отписаться

        try {
            $sub = ORM::factory('Erp217_EntitySubscription')
                ->where('user_id', '=', $this->_current_user->id)
                ->where('entity_type', '=', $entity_type)
                ->where('entity_id', '=', $entity_id)
                ->find();

            if ($subscribe) {
                $sub->user_id     = $this->_current_user->id;
                $sub->entity_type = $entity_type;
                $sub->entity_id   = $entity_id;
                $sub->is_deleted  = 0;
                if (!$sub->loaded()) {
                    $sub->created_by = $this->_current_user->id;
                }
                $sub->save();
                $message = __('Подписка оформлена');
            } else {
                if ($sub->loaded()) {
                    $sub->is_deleted = 1;
                    $sub->save();
                }
                $message = __('Подписка отменена');
            }

            $this->response->body(json_encode([
                'status'  => 'success',
                'message' => $message
            ]));

        } catch (Exception $e) {
            $this->response->status(500)->body(json_encode([
                'status'  => 'error',
                'message' => $e->getMessage()
            ]));
        }
    }

    /**
     * Пометить уведомление как прочитанное
     */
    public function action_read()
    {
        $id = $this->request->param('id');
        $notification = ORM::factory('Erp217_Notification', $id);

        if ($notification->loaded() && $notification->recipient_id == $this->_current_user->id) {
            $notification->is_read = 1;
            $notification->status = Model_Erp217_Notification::STATUS_READ;
            $notification->save();
            
            if ($this->request->is_ajax()) {
                $this->auto_render = FALSE;
                $this->response->body(json_encode(['status' => 'success']));
                return;
            }
            
            $this->redirect($this->request->referrer() ?: '/erp217_index');
        }
        
        throw new HTTP_Exception_404();
    }
}