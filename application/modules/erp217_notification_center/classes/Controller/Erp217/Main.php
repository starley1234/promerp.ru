<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Базовый контроллер модуля ERP217 "Центр уведомлений".
 * 
 * Отвечает за:
 * 1. Инициализацию общих переменных модуля.
 * 2. Проверку прав доступа (ACL).
 * 3. Построение навигационного меню модуля.
 */
abstract class Controller_Erp217_Main extends Controller_Base {

    /**
     * @var Model_User Текущий пользователь
     */
    protected $_user;

    /**
     * Выполняется перед вызовом основного action.
     */
    public function before()
    {
        parent::before();

        // 1. Проверка авторизации (базовая роль login)
        if (!$this->_current_user) {
            $this->redirect('/auth/login');
        }

        $this->_user = $this->_current_user;

        // 3. Построение меню модуля с учетом ролей
        $this->_init_module_menu();

        // 4. Установка заголовка по умолчанию
        $this->set_title(__('Центр уведомлений'));
    }

    /**
     * Инициализация меню модуля.
     * Фильтрация пунктов происходит автоматически в методе set_menu родительского класса.
     */
    protected function _init_module_menu()
    {
        $menu = [
            [
                'title' => __('Мои уведомления'),
                'href'  => '/erp217_index',
                'icon'  => 'fas fa-bell',
                'roles' => ['login']
            ],
            [
                'title' => __('Настройки'),
                'href'  => '/erp217_settings',
                'icon'  => 'fas fa-user-cog',
                'roles' => ['login']
            ],
            [
                'title' => __('Управление шаблонами'),
                'href'  => '/erp217_templates',
                'icon'  => 'fas fa-file-alt',
                'roles' => ['admin', 'director'] // Только для администрации
            ],
            [
                'title' => __('События'),
                'href'  => '/erp217_eventtype',
                'icon'  => 'fas fa-file-alt',
                'roles' => ['admin', 'director'] // Только для администрации
            ],
            [
                'title' => __('Очередь отправки'),
                'href'  => '/erp217_notification',
                'icon'  => 'fas fa-tasks',
                'roles' => ['admin', 'director', 'senior_manager']
            ],
        ];

        $this->set_menu($menu);
    }

    /**
     * Вспомогательный метод для проверки специфических прав доступа (Abilities).
     * 
     * @param string $ability Код права
     * @return bool
     */
    protected function can_do($ability)
    {
        switch ($ability) {
            case 'manage_templates':
                return $this->has_any_role(['admin', 'director']);
            
            case 'view_notification_log':
                return $this->has_any_role(['admin', 'director', 'senior_manager']);
            
            case 'manage_subscriptions':
                return $this->_user !== NULL;

            default:
                return FALSE;
        }
    }

    /**
     * Перехват исключений доступа
     */
    protected function check_access($ability)
    {
        if (!$this->can_do($ability)) {
            throw new HTTP_Exception_403('У вас недостаточно прав для выполнения этого действия.');
        }
    }
}