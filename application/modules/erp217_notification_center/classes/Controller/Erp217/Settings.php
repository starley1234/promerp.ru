<?php defined('SYSPATH') or die('No direct script access.'); 

/**
 * Контроллер настроек уведомлений.
 */
class Controller_Erp217_Settings extends Controller_Erp217_Main {

    /**
     * Интерфейс управления подписками.
     */
    public function action_index()
    {
        $this->set_title(__('Настройки подписок'));
        $this->set_description(__('Управление персональными подписками на системные события.'));

        // Получаем роли текущего пользователя
        $user_roles = $this->_current_user->roles->find_all()->as_array('name', 'name');

        // Получаем типы событий из БД через модель EventType
        $event_types_models = ORM::factory('Erp217_EventType')
            ->where('is_deleted', '=', 0)
            ->find_all();

        $event_types = [];
        foreach ($event_types_models as $et) {
            // Проверка прав доступа по ролям
            $allowed = TRUE;
            if (!empty($et->allowed_roles)) {
                $allowed_roles = explode(',', $et->allowed_roles);
                $allowed_roles = array_map('trim', $allowed_roles);
                
                // Если нет пересечения ролей пользователя с разрешенными ролями события
                if (!count(array_intersect($user_roles, $allowed_roles))) {
                    $allowed = FALSE;
                }
            }

            if ($allowed) {
                $event_types[$et->alias] = $et->name;
            }
        }

        if (empty($event_types)) {
            $event_types = ['test' => 'Доступные типы событий не найдены'];
        }

        $channels = Kohana::$config->load('erp217_notification_center.channels');
        if (empty($channels)) {
            $channels = ['email' => 'Email', 'telegram' => 'Telegram', 'push' => 'Push', 'max' => 'Max'];
        }

        // Обычные подписки по типам событий
        $subscriptions = ORM::factory('Erp217_Subscription')
            ->where('user_id', '=', $this->_current_user->id)
            ->where('is_deleted', '=', 0)
            ->find_all();

        $user_settings = [];
        foreach ($subscriptions as $sub) {
            $user_settings[$sub->event_type][$sub->channel] = $sub->is_enabled;
        }

        // Подписки на конкретные сущности
        $entity_subscriptions = ORM::factory('Erp217_EntitySubscription')
            ->where('user_id', '=', $this->_current_user->id)
            ->where('is_deleted', '=', 0)
            ->find_all();

        $this->template->content = View::factory('erp217/settings/index')
            ->set('event_types', $event_types)
            ->set('channels', $channels)
            ->set('user_settings', $user_settings)
            ->set('entity_subscriptions', $entity_subscriptions);
    }
	
}