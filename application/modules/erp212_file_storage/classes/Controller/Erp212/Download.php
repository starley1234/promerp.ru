<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Controller_Erp212_Download
 * 
 * Обработка запросов на скачивание файлов с проверкой токенов и прав доступа.
 * 
 * @package Erp212
 * @version 1.0.0
 */
class Controller_Erp212_Download extends Controller_Erp212_Main {
    
    /**
     * Инициализация перед выполнением action
     */
    public function before()
    {
        parent::before();
        
        // Отключаем рендеринг шаблона для API
        $this->auto_render = FALSE;
        
        // Получаем экземпляр сервиса хранилища
        $this->storage_service = Service_Erp212_Storage::instance();

        $this->_meta['title'] = 'Скачивание файла';
        $this->_meta['description'] = 'Страница для скачивания файлов из системы ERP212.';
    }

    /**
     * Скачивание файла по ID с проверкой прав доступа
     * Роут: erp212_download/file/<id>
     */
    public function action_file()
    {
        $file_id = (int) $this->request->param('id');
        $user_id = $this->_current_user ? $this->_current_user->id : null;

        // Проверка прав доступа через ACL
        if (!$this->can('erp212_storage.download_file', (object)['id' => $file_id, 'user_id' => $user_id])) {
            throw new HTTP_Exception_403('Доступ к файлу запрещен');
        }

        try {
            $file_data = $this->storage_service->getFileForDownload($file_id, $user_id);
            
            if (!$file_data) {
                throw new HTTP_Exception_404('Файл не найден или доступ запрещен');
            }

            $this->streamFile(
                $file_data['stream'],
                $file_data['filename'],
                $file_data['mime_type']
            );

            // Вызов события после успешного скачивания
            Event::fire('erp212_storage.file_downloaded', (object)['file_id' => $file_id, 'user_id' => $user_id]);

        } catch (HTTP_Exception $e) {
            $this->response->status($e->getCode());
            $this->response->headers('Content-Type', 'application/json; charset=utf-8');
            $this->response->body(json_encode(['error' => $e->getMessage()]));
        } catch (Exception $e) {
            $this->response->status(500);
            $this->response->headers('Content-Type', 'application/json; charset=utf-8');
            $this->response->body(json_encode(['error' => 'Произошла внутренняя ошибка: ' . $e->getMessage()]));
        }
    }

    /**
     * Скачивание файла по временному токену
     * Роут: erp212_download/shared/<token>
     */
    public function action_shared()
    {
        $token = $this->request->param('token');

        // Проверка прав доступа через ACL (для токенов может быть отдельное правило или проверка внутри сервиса)
        if (!$this->can('erp212_storage.download_shared_file', (object)['token' => $token])) {
            throw new HTTP_Exception_403('Доступ по токену запрещен');
        }

        try {
            $file_data = $this->storage_service->getFileByToken($token);
            
            if (!$file_data) {
                throw new HTTP_Exception_404('Недействительный или просроченный токен');
            }

            $this->streamFile(
                $file_data['stream'],
                $file_data['filename'],
                $file_data['mime_type']
            );

            // Вызов события после успешного скачивания по токену
            Event::fire('erp212_storage.shared_file_downloaded', (object)['file_id' => $file_data['id'], 'token' => $token]);

        } catch (HTTP_Exception $e) {
            $this->response->status($e->getCode());
            $this->response->headers('Content-Type', 'application/json; charset=utf-8');
            $this->response->body(json_encode(['error' => $e->getMessage()]));
        } catch (Exception $e) {
            $this->response->status(500);
            $this->response->headers('Content-Type', 'application/json; charset=utf-8');
            $this->response->body(json_encode(['error' => 'Произошла внутренняя ошибка: ' . $e->getMessage()]));
        }
    }

    /**
     * Отправка файлового потока клиенту
     * 
     * @param mixed $stream Ресурс потока или путь к файлу
     * @param string $filename Имя файла для скачивания
     * @param string $mime MIME-тип файла
     * 
     * @throws HTTP_Exception_500
     */
    private function streamFile($stream, $filename, $mime)
    {
        $this->auto_render = FALSE;

        // Очищаем буфер вывода
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Подготовка имени файла для заголовка (защита от спецсимволов)
        $quoted_filename = rawurlencode($filename);

        // Устанавливаем заголовки для принудительного скачивания
        $this->response->headers('Content-Type', 'application/octet-stream');
        $this->response->headers('Content-Disposition', 'attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . $quoted_filename);
        $this->response->headers('Content-Transfer-Encoding', 'binary');
        $this->response->headers('Cache-Control', 'private, max-age=0, must-revalidate');
        $this->response->headers('Pragma', 'public');

        if (is_string($stream) && file_exists($stream)) {
            // Локальный файл
            $this->response->headers('Content-Length', (string) filesize($stream));
            $this->response->send_headers();
            readfile($stream);
        } elseif (is_resource($stream)) {
            // Поток (например, от S3)
            // Если размер известен, можно установить Content-Length
            // $meta = stream_get_meta_data($stream);
            // if (isset($meta['size'])) { // Предполагаем, что сервис может вернуть размер
            //     $this->response->headers('Content-Length', (string) $meta['size']);
            // }
            $this->response->send_headers();
            fpassthru($stream);
        } else {
            throw new HTTP_Exception_500('Некорректный поток данных');
        }

        // Завершаем выполнение после отправки файла
        exit;
    }

}