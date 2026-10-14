<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Widget Controller for Notification Center (ERP217).
 * Предназначен для HMVC вызовов, в частности для отображения "колокольчика" в шапке сайта.
 * 
 * @package    ERP217
 * @category   Controller
 * @author     Development Team
 */
class Controller_Erp217_Widget extends Controller {

    /**
     * @var bool  Рендерить шаблон после действия
     */
    public $auto_render = TRUE;


    /**
     * Виджет "Колокольчик" уведомлений.
     * Вызывается через: Request::factory('erp217/widget/bell')->execute();
     */
    public function action_bell()
    {
        // Получаем текущего пользователя через Auth
        $user = Auth::instance()->get_user();
        
        if ( ! $user)
        {
            // Если пользователь не авторизован, не отображаем виджет
            $this->response->body('');
            return;
        }

        /**
         * В данной архитектуре "внутренние уведомления" (Push) хранятся в таблице erp_217_notification
         * со статусом 'pending' или 'sent' (если уже показаны, но не прочитаны).
         * Для виджета мы считаем количество записей в очереди для пользователя по каналу 'push',
         * которые еще не были обработаны или помечены как прочитанные.
         */
        $unread_count = ORM::factory('Erp217_Notification')
            ->where('recipient_id', '=', $user->id)
            ->where('channel', '=', 'push')
            ->where('status', '=', 'pending') // Или 'sent' в зависимости от логики "прочтения"
            ->where('is_deleted', '=', 0)
            ->count_all();

        // Получаем последние 5 уведомлений для выпадающего списка
        $notifications = ORM::factory('Erp217_Notification')
            ->where('recipient_id', '=', $user->id)
            ->where('channel', '=', 'push')
            ->where('is_deleted', '=', 0)
            ->order_by('created_at', 'DESC')
            ->limit(5)
            ->find_all();

        // Рендерим представление
        $view = View::factory('erp217/widgets/bell')
            ->set('unread_count', $unread_count)
            ->set('notifications', $notifications)
            ->set('user_id', $user->id);

        $this->response->body($view->render());
    }

    /**
     * API-метод для быстрого получения количества уведомлений (для JS-поллинга)
     */
    public function action_get_count()
    {
        $this->auto_render = FALSE;
        $user = Auth::instance()->get_user();

        if ( ! $user)
        {
            $this->response->status(403);
            return;
        }

        $count = ORM::factory('Erp217_Notification')
            ->where('recipient_id', '=', $user->id)
            ->where('channel', '=', 'push')
            ->where('status', '=', 'pending')
            ->where('is_deleted', '=', 0)
            ->count_all();

        $this->response->headers('Content-Type', 'application/json');
        $this->response->body(json_encode([
            'status' => 'success',
            'count'  => (int) $count
        ]));
    }
}