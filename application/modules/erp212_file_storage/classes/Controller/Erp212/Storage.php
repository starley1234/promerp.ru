<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Controller_Erp212Storage
 * 
 * Основной входной контроллер модуля erp212_storage.
 * Обеспечивает маршрутизацию и базовую логику отображения файлового менеджера.
 * 
 * @package Erp212
 * @version 1.0.3
 */
class Controller_Erp212_Storage extends Controller_Erp212_Main
{
    /**
     * @var Service_Erp212_Storage Экземпляр сервиса
     */
    protected $storage_service;

    /**
     * Инициализация
     */
    public function before()
    {
        parent::before();
        $this->storage_service = Service_Erp212_Storage::instance();
    }

    /**
     * Главная страница хранилища (Список файлов)
     * Перенаправляет на Index контроллер модуля
     */
    public function action_index()
    {
        $this->redirect('erp212_index');
    }

    /**
     * Просмотр информации о конкретном файле
     */
    public function action_file()
    {
        $id = (int) $this->request->param('id');
        $file = ORM::factory('Erp212_File', $id);

        if (!$file->loaded() || $file->deleted_at !== NULL) {
            throw new HTTP_Exception_404('Файл не найден или доступ ограничен');
        }

        if (!$this->can('erp212_storage.view_file', $file)) {
            throw new HTTP_Exception_403('Доступ к файлу запрещен');
        }

        $view = View::factory('erp212/storage/file_details')
            ->set('file', $file)
            ->set('service', $this->storage_service);

        $this->_meta['title'] = 'Файл: ' . $file->filename;
        $this->template->content = $view;
    }

    /**
     * Редактирование метаданных файла
     */
    public function action_edit()
    {
        $id = (int) $this->request->param('id');
        $file = ORM::factory('Erp212_File', $id);

        if (!$file->loaded() || $file->deleted_at !== NULL) {
            throw new HTTP_Exception_404('Файл не найден');
        }

        // Проверка прав на запись
        if (!$file->can_write(Auth::instance()->get_user()->id) && !$this->can('erp212_storage.edit_all_files')) {
            throw new HTTP_Exception_403('У вас нет прав для редактирования этого файла');
        }

        if ($this->request->method() === Request::POST) {
            try {
                $data = $this->request->post();
                if (!isset($data['is_public'])) {
                    $data['is_public'] = 0;
                }
                
                $file->values($data, array('filename', 'is_public', 'module_prefix'));
                $file->save();
                
                HTTP::redirect(Route::get('erp212_storage')->uri(array('action' => 'file', 'id' => $file->id)));
            } catch (ORM_Validation_Exception $e) {
                $errors = $e->errors('models');
            }
        }

        $view = View::factory('erp212/storage/edit')
            ->set('file', $file)
            ->set('errors', isset($errors) ? $errors : array());

        $this->_meta['title'] = 'Редактирование: ' . $file->filename;
        $this->template->content = $view;
    }

    /**
     * Настройки хранилища
     */
    public function action_settings()
    {
        if (!$this->can('erp212_storage.manage_settings')) {
            throw new HTTP_Exception_403('Доступ к настройкам ограничен');
        }

        $config = Kohana::$config->load('erp212_storage');
        $backends = $this->storage_service->get_active_backends();

        $view = View::factory('erp212/storage/settings')
            ->set('backends', $backends)
            ->set('config', $config->as_array());

        $this->_meta['title'] = 'Настройки хранилища';
        $this->template->content = $view;
    }
    
    /**
     * action_upload
     * Интерфейс загрузки файлов (форма)
     * 
     * @return View
     */
    public function action_upload()
    {
        if (!$this->can('erp212_storage.upload_file')) {
            $this->auto_render = FALSE;
            throw new HTTP_Exception_403('Доступ запрещен.');
        }
        
        $config = Kohana::$config->load('erp212_storage');
        
        $view = View::factory('erp212/storage/upload')
            ->set('max_size', $config['upload_max_size'])
            ->set('allowed_types', $config['allowed_mime_types'])
            ->set('upload_url', URL::site('erp212_api/upload'))
            ->set('backends', $this->storage_service->get_active_backends());
        
        $this->_meta['title'] = 'Загрузка файлов - Файловый менеджер';
        $this->template->content = $view;
    }

    /**
     * Отображение публичных файлов
     */
    public function action_public()
    {
        if (!$this->can('erp212_storage.view_public_files')) {
            throw new HTTP_Exception_403('Доступ к публичным файлам запрещен');
        }

        $query_params = $this->request->query();
        $page = isset($query_params['page']) ? (int)$query_params['page'] : 1;

        $result = $this->storage_service->get_files_list([
            'page' => $page,
            'per_page' => 20,
            'is_public' => 1
        ]);

        $view = View::factory('erp212/storage/public')
            ->set('files', $result['items'])
            ->set('total', $result['total'])
            ->set('page', $page);

        $this->_meta['title'] = 'Общие файлы';
        $this->template->content = $view;
    }
    
    /**
     * action_search
     * Алиас для action_index with акцентом на поиске
     * Поддерживает те же GET параметры, что и action_index
     * 
     * @return View
     */
    public function action_search()
    {
        // Просто вызываем action_index, так как логика идентична
        return $this->action_index();
    }
    
    /**
     * action_backends
     * Управление хранилищами
     * 
     * @return View
     */
    public function action_backends()
    {
        if (!$this->can('erp212_storage.manage_backends')) {
            $this->auto_render = FALSE;
            throw new HTTP_Exception_403('Доступ запрещен.');
        }
        
        $backends = $this->storage_service->get_all_backends();
        
        $view = View::factory('erp212/storage/backends')
            ->set('backends', $backends);
        
        $this->_meta['title'] = 'Управление хранилищами';
        $this->template->content = $view;
    }
    
    /**
     * action_logs
     * Журнал доступа к файлам
     * 
     * @return View
     */
    public function action_logs()
    {
        if (!$this->can('erp212_storage.view_logs')) {
            $this->auto_render = FALSE;
            throw new HTTP_Exception_403('Доступ запрещен.');
        }
        
        $query_params = $this->request->query();
        $page = isset($query_params['page']) ? (int)$query_params['page'] : 1;
        $per_page = 50;
        
        $result = $this->storage_service->get_access_logs([
            'page' => $page,
            'per_page' => $per_page
        ]);
        
        $view = View::factory('erp212/storage/logs')
            ->set('logs', $result['logs'])
            ->set('total', $result['total'])
            ->set('current_page', $page)
            ->set('per_page', $per_page)
            ->set('total_pages', ceil($result['total'] / $per_page));
        
        $this->_meta['title'] = 'Журнал доступа к файлам';
        $this->template->content = $view;
    }

    /**
     * Управление правами доступа к файлу
     */
    public function action_permissions()
    {
        $id = (int) $this->request->param('id');
        $file = ORM::factory('Erp212_File', $id);

        if (!$file->loaded() || $file->deleted_at !== NULL) {
            throw new HTTP_Exception_404('Файл не найден');
        }

        // Только владелец или администратор может управлять правами
        if (!$file->can_write(Auth::instance()->get_user()->id) && !$this->can('erp212_storage.manage_all_permissions')) {
            throw new HTTP_Exception_403('У вас нет прав для управления доступом к этому файлу');
        }

        if ($this->request->method() === Request::POST) {
            $user_id = (int) $this->request->post('user_id');
            $permissions = [
                'can_read'   => (bool) $this->request->post('can_read'),
                'can_write'  => (bool) $this->request->post('can_write'),
                'can_delete' => (bool) $this->request->post('can_delete'),
            ];

            ORM::factory('Erp212_Permission')->set_permission($file->id, $user_id, $permissions);
            $this->redirect($this->request->uri());
        }

        $permissions = ORM::factory('Erp212_Permission')->for_file($file->id);
        $users = ORM::factory('User')->find_all();

        $view = View::factory('erp212/storage/permissions')
            ->set('file', $file)
            ->set('permissions', $permissions)
            ->set('users', $users);

        $this->_meta['title'] = 'Права доступа: ' . $file->filename;
        $this->template->content = $view;
    }
}