<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Controller_Erp212_Widget
 * 
 * HMVC виджет загрузчика файлов для вставки в другие модули.
 * Предоставляет компактную форму загрузки с настройками через GET-параметры.
 * 
 * @package Erp212
 * @version 1.0.0
 */
class Controller_Erp212_Widget extends Controller_Base {

    /**
     * Проверка прав доступа перед выполнением действия
     * Виджет доступен только авторизованным пользователям с правами на загрузку файлов.
     */
    public function before()
    {
        parent::before();
        
        // Проверяем авторизацию и права на загрузку файлов
        if (!$this->_current_user || !$this->can('erp212_storage.upload')) {
            throw HTTP_Exception::factory(403, 'Доступ к загрузчику запрещен. Требуются права на загрузку файлов.');
        }
    }

    /**
     * action_uploader
     * Возвращает HTML-виджет загрузчика файлов
     * 
     * GET-параметры:
     * - input_name (string): имя поля формы (по умолчанию 'file')
     * - multi (bool): разрешить множественную загрузку (по умолчанию false)
     * - upload_url (string): URL для загрузки (по умолчанию '/erp212_api/upload')
     * - max_size (int): максимальный размер файла в байтах (берется из конфига если не указан)
     * - allowed_types (string|array): разрешенные MIME-типы (берется из конфига если не указан)
     * 
     * Пример вызова из другого модуля:
     * Request::factory('erp212_widget/uploader?input_name=avatar&multi=true')->execute()
     */
    public function action_uploader()
    {
        // Устанавливаем мета-данные страницы
        $this->_meta['title'] = 'Виджет загрузчика файлов';
        $this->_meta['description'] = 'Виджет для загрузки файлов в систему хранения.';
        
        // Получаем все GET-параметры
        $query = $this->request->query();
        
        // Извлекаем параметры с проверкой isset()
        $input_name = Arr::get($query, 'input_name', 'file');
        $multi = (bool) Arr::get($query, 'multi', false);
        $upload_url = Arr::get($query, 'upload_url', '/erp212_api/upload');
        
        // Загружаем конфигурацию модуля
        $config = Kohana::$config->load('erp212_storage');
        
        // Получаем настройки из конфига или переопределяем через GET-параметры
        // (ключ конфига — upload_max_size, см. config/erp212_storage.php)
        $max_size = (int) Arr::get($query, 'max_size', Arr::get($config, 'upload_max_size', 10485760));
        $allowed_types = Arr::get($query, 'allowed_types', Arr::get($config, 'allowed_mime_types', []));
        
        // Если allowed_types передан как строка через запятую, преобразуем в массив
        if (is_string($allowed_types)) {
            $allowed_types = explode(',', $allowed_types);
        }
        
        // Подготавливаем данные для представления
        $view_data = [
            'input_name' => $input_name,
            'multi' => $multi,
            'upload_url' => $upload_url,
            'max_size' => $max_size,
            'allowed_types' => $allowed_types,
            'current_user_id' => $this->_current_user ? $this->_current_user->id : null,
            // CSRF-токен: у пользователя нет такого столбца — берём штатный токен Kohana
            'csrf_token' => class_exists('Security') ? Security::token() : NULL,
        ];
        
        // Создаем и возвращаем представление
        $view = View::factory('erp212/widgets/uploader', $view_data);
        $this->response->body($view);
    }

}