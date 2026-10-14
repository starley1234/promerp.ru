<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Controller_Erp212_Index
 * 
 * @package Erp212
 * @version 1.0.0
 * 
 * Основной контроллер модуля хранилища:
 * - Отображение списка файлов с пагинацией
 * - Поиск файлов по имени и метаданным
 * - Интерфейс управления (просмотр, загрузка через HMVC)
 * 
 * Требуемые права доступа: erp212_storage.view_files, erp212_storage.upload_files, erp212_storage.manage_files
 */
class Controller_Erp212_Index extends Controller_Erp212_Main
{
    /**
     * @var Service_Erp212_Storage Экземпляр сервиса хранилища
     */
    protected $storage_service;
    
    /**
     * @var int Размер страницы по умолчанию
     */
    const DEFAULT_PER_PAGE = 20;
    
    /**
     * Выполняется перед каждым action
     * Инициализирует сервис и проверяет права доступа
     */
    public function before()
    {
        parent::before();
        
        // Инициализация сервиса хранилища
        $this->storage_service = Service_Erp212_Storage::instance();
        
        // Установка мета-данных по умолчанию
        $this->_meta['title'] = 'Файловый менеджер';
        $this->_meta['description'] = 'Управление файлами, загрузка, поиск и контроль доступа';
    }
    
    /**
     * action_index
     * Отображение списка файлов с пагинацией и поиском
     * 
     * GET параметры:
     * - page (int) - номер страницы (по умолчанию 1)
     * - per_page (int) - количество элементов на странице (по умолчанию 20)
     * - q (string) - поисковый запрос
     * 
     * @return void
     * @throws HTTP_Exception_403 Если нет прав на просмотр файлов
     */
    public function action_index()
    {
        if (!$this->can('erp212_storage.view_files')) {
            throw new HTTP_Exception_403('Доступ запрещен. У вас нет прав на просмотр файлов.');
        }

        $query_params = $this->request->query();
        
        $page = (int) Arr::get($query_params, 'page', 1);
        $per_page = (int) Arr::get($query_params, 'per_page', self::DEFAULT_PER_PAGE);
        $search = trim((string) Arr::get($query_params, 'q'));
        
        $result = $this->storage_service->get_files_list([
            'user_id' => $this->_current_user ? $this->_current_user->id : null,
            'search' => $search,
            'page' => $page,
            'per_page' => $per_page,
            'with_permissions' => true
        ]);
        
        $backends = $this->storage_service->get_active_backends();
        
        $stats = $this->storage_service->get_storage_stats([
            'user_id' => $this->_current_user ? $this->_current_user->id : null
        ]);
        
        $view = View::factory('erp212/storage/index')
            ->set('files', $result['items'])
            ->set('total_files', $result['total'])
            ->set('current_page', $page)
            ->set('per_page', $per_page)
            ->set('total_pages', ceil($result['total'] / $per_page))
            ->set('backends', $backends)
            ->set('stats', $stats)
            ->set('search', $search)
            ->set('current_user', $this->_current_user);
        
        if ($search) {
            $this->_meta['title'] = 'Поиск: ' . HTML::chars($search) . ' - Файловый менеджер';
        }
        
        $this->template->content = $view;
    }
    
    /**
     * action_upload
     * Отображает форму загрузки файлов и обрабатывает загрузку.
     * 
     * @return void
     * @throws HTTP_Exception_403 Если нет прав на загрузку файлов
     */
    public function action_upload()
    {
        if (!$this->can('erp212_storage.upload_files')) {
            throw new HTTP_Exception_403('Доступ запрещен. У вас нет прав на загрузку файлов.');
        }

        $this->_meta['title'] = 'Загрузка файлов';

        $errors = [];
        $success_message = null;

        if ($this->request->method() === Request::POST) {
            try {
                $uploaded_files = Arr::get($_FILES, 'files', []);
                if (empty($uploaded_files['name'][0])) {
                    throw new Service_Erp212_Storage_Exception('Не выбраны файлы для загрузки.');
                }

                $files_data = [];
                foreach ($uploaded_files['name'] as $key => $name) {
                    if ($uploaded_files['error'][$key] === UPLOAD_ERR_OK) {
                        $files_data[] = [
                            'name' => $name,
                            'type' => $uploaded_files['type'][$key],
                            'tmp_name' => $uploaded_files['tmp_name'][$key],
                            'error' => $uploaded_files['error'][$key],
                            'size' => $uploaded_files['size'][$key],
                        ];
                    } else {
                        $errors[] = 'Ошибка загрузки файла ' . HTML::chars($name) . ': ' . Upload::error_string($uploaded_files['error'][$key]);
                    }
                }

                if (!empty($files_data)) {
                    $uploaded_items = $this->storage_service->upload_files($files_data, $this->_current_user ? $this->_current_user->id : null);
                    foreach ($uploaded_items as $item) {
                        Event::fire('erp212_storage.file_uploaded', $item);
                    }
                    $success_message = 'Файлы успешно загружены.';
                }
            } catch (Service_Erp212_Storage_Exception $e) {
                $errors[] = $e->getMessage();
            } catch (Exception $e) {
                $errors[] = 'Произошла непредвиденная ошибка: ' . $e->getMessage();
                Kohana::$log->add(Kohana_Log::ERROR, 'File upload error: ' . $e->getMessage());
            }
        }

        $view = View::factory('erp212/storage/upload')
            ->set('errors', $errors)
            ->set('success_message', $success_message);

        $this->template->content = $view;
    }

}