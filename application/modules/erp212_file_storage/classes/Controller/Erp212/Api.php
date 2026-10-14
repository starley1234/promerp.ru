<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Controller_Erp212_Api
 * 
 * REST API для работы с файловым хранилищем
 * 
 * @package Erp212
 * @version 1.2.0
 */
class Controller_Erp212_Api extends Controller_Base
{
    /**
     * @var Service_Erp212_Storage
     */
    protected $storage_service;
    
    /**
     * Инициализация перед выполнением action
     */
    public function before()
    {
        parent::before();
        
        // Отключаем рендеринг шаблона для API
        $this->auto_render = FALSE;
        
        // Устанавливаем заголовок JSON
        $this->response->headers('Content-Type', 'application/json; charset=utf-8');
        
        // Получаем экземпляр сервиса хранилища
        $this->storage_service = Service_Erp212_Storage::instance();

        // Устанавливаем мета-данные для API
        $this->_meta['title'] = 'API файлового хранилища';
        $this->_meta['description'] = 'Интерфейс для взаимодействия с файловым хранилищем Erp212';
    }
    
    /**
     * Загрузка файла (multipart/form-data)
     * POST /erp212_api/upload
     * 
     * @return JSON
     */
    public function action_upload()
    {
        // Проверяем метод запроса
        if ($this->request->method() !== HTTP_Request::POST) {
            $this->_error_response(405, 'Метод не разрешен. Используйте POST.');
            return;
        }

        // Проверка ACL
        if (!$this->can('erp212_storage.upload')) {
            $this->_error_response(403, 'Доступ запрещен. Недостаточно прав для загрузки файлов.');
            return;
        }
        
        $files = $_FILES;
        
        if (empty($files['file']) || !is_uploaded_file($files['file']['tmp_name'])) {
            $this->_error_response(400, 'Файл обязателен для загрузки.');
            return;
        }
        
        $post = $this->request->post();
        $is_public = isset($post['is_public']) ? (bool)$post['is_public'] : false;
        $backend_id = isset($post['backend_id']) ? (int)$post['backend_id'] : null;
        $module = isset($post['module']) ? (string)$post['module'] : 'default';
        $user_id = $this->_current_user ? $this->_current_user->id : null;
        
        try { 
            // Вызываем сервис для сохранения файла
            // Внутри save() используется структура {module_prefix}/{folder_index}/{filename}
            $file_id = $this->storage_service->save(
                $files['file'],
                [
                    'is_public' => $is_public,
                    'backend_id' => $backend_id,
                    'module' => $module,
                    'user_id' => $user_id
                ]
            );
            
            $this->_success_response($file_id, 'Файл успешно загружен.');
           
        } catch (Exception $e) {
            $this->_error_response(500, 'Ошибка при загрузке файла: ' . $e->getMessage());
        }
    }
    
    /**
     * Получение списка файлов с фильтрацией
     * GET /erp212_api/list
     * 
     * @return JSON
     */
    public function action_list()
    {
        if ($this->request->method() !== HTTP_Request::GET) {
            $this->_error_response(405, 'Метод не разрешен. Используйте GET.');
            return;
        }

        $params = $this->request->query();
        
        // Если не админ, ограничиваем выборку только своими файлами или публичными
        if (!$this->can('erp212_storage.admin')) {
            $params['user_id'] = $this->_current_user ? $this->_current_user->id : -1;
        }

        try {
            $result = $this->storage_service->get_files_list($params);
            
            // Преобразуем ORM объекты в массивы метаданных
            $items = [];
            foreach ($result['items'] as $file) {
                $items[] = $this->storage_service->get_extended_metadata($file->id);
            }
            $result['items'] = $items;

            $this->_success_response($result);
        } catch (Exception $e) {
            $this->_error_response(500, 'Ошибка при получении списка: ' . $e->getMessage());
        }
    }

    /**
     * Получение метаданных файла
     * GET /erp212_api/get_metadata/<id>
     * 
     * @return JSON
     */
    public function action_get_metadata()
    {
        if ($this->request->method() !== HTTP_Request::GET) {
            $this->_error_response(405, 'Метод не разрешен. Используйте GET.');
            return;
        }
        
        $file_id = (int)$this->request->param('id');
        
        if ($file_id <= 0) {
            $this->_error_response(400, 'Неверный ID файла.');
            return;
        }
        
        try {
            $metadata = $this->storage_service->get_extended_metadata($file_id);
            
            if (!$metadata) {
                $this->_error_response(404, 'Файл не найден.');
                return;
            }
            
            // Проверяем права доступа
            if (!$this->can('erp212_storage.read', (object)$metadata)) {
                $this->_error_response(403, 'Доступ запрещен. Недостаточно прав для просмотра метаданных файла.');
                return;
            }
            
            $this->_success_response($metadata);
            
        } catch (Exception $e) {
            $this->_error_response(500, 'Ошибка при получении метаданных файла: ' . $e->getMessage());
        }
    }

    /**
     * Удаление файла (мягкое удаление)
     * DELETE /erp212_api/delete/<id>
     * 
     * @return JSON
     */
    public function action_delete()
    {
        if ($this->request->method() !== HTTP_Request::DELETE) {
            $this->_error_response(405, 'Метод не разрешен. Используйте DELETE.');
            return;
        }
        
        $file_id = (int)$this->request->param('id');
        
        if ($file_id <= 0) {
            $this->_error_response(400, 'Неверный ID файла.');
            return;
        }
        
        try {
            $file = ORM::factory('Erp212_File', $file_id);
            if (!$file->loaded() || $file->deleted_at !== NULL) {
                $this->_error_response(404, 'Файл не найден.');
                return;
            }

            if (!$this->can('erp212_storage.delete', $file)) {
                $this->_error_response(403, 'Доступ запрещен. Недостаточно прав для удаления файла.');
                return;
            }
            
            $result = $this->storage_service->delete($file_id);
            
            if ($result) {
                $this->_success_response(['id' => $file_id], 'Файл успешно удален.');
            } else {
                $this->_error_response(500, 'Не удалось удалить файл.');
            }
            
        } catch (Exception $e) {
            $this->_error_response(500, 'Ошибка при удалении файла: ' . $e->getMessage());
        }
    }
    
    /**
     * Универсальный метод для успешного JSON-ответа
     */
    protected function _success_response($data, $message = 'Успешно')
    {
        $response = [
            'success' => true,
            'message' => $message,
            'data' => $data,
            'timestamp' => time()
        ];
        
        $this->response->body(json_encode($response, JSON_UNESCAPED_UNICODE));
    }
    
    /**
     * Универсальный метод для ошибки JSON-ответа
     */
    protected function _error_response($status_code, $message = 'Ошибка')
    {
        $this->response->status($status_code);
        
        $response = [
            'success' => false,
            'error' => $message,
            'code' => $status_code,
            'timestamp' => time()
        ];
        
        $this->response->body(json_encode($response, JSON_UNESCAPED_UNICODE));
    }
}