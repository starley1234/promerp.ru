<?php defined('SYSPATH') or die('No direct script access.');

/**
 * Service_Erp212_Storage - Основной сервис для работы с файловым хранилищем.
 * Реализована структура хранения {module_prefix}/{folder_index}/{filename}
 */
class Service_Erp212_Storage {

    /**
     * @var Service_Erp212_Storage|null Экземпляр класса (синглтон)
     */
    protected static $_instance = NULL;

    /**
     * @var Model_Erp212_Backend|null Текущий бэкенд
     */
    protected $_backend = NULL;

    /**
     * Получение экземпляра класса (синглтон)
     */
    public static function instance()
    {
        if (self::$_instance === NULL)
        {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Получение полного пути к файлу (для локального драйвера)
     */
    public function getFilePath($file_id)
    {
        $file = ORM::factory('Erp212_File', $file_id);
        
        if (!$file->loaded() || $file->deleted_at !== NULL) {
            return FALSE;
        }

        $backend = $file->backend;
        $config = $backend->get_config_array();

        if ($backend->driver === 'local') {
            $root = $this->_get_local_root($config);
            return $root . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $file->storage_path), DIRECTORY_SEPARATOR);
        }

        return FALSE;
    }

    /**
     * Получение данных файла для скачивания
     */
    public function getFileForDownload($file_id, $user_id = null)
    {
        $file = ORM::factory('Erp212_File', $file_id);
        
        if (!$file->loaded() || $file->deleted_at !== NULL) {
            return FALSE;
        }

        if (!$file->is_public && ($user_id === null || !$file->can_read($user_id))) {
            return FALSE;
        }

        return $this->_prepare_file_data($file);
    }

    /**
     * Получение данных файла по временному токену
     */
    public function getFileByToken($token)
    {
        $link = ORM::factory('Erp212_Link')
            ->where('token', '=', $token)
            ->where('expires_at', '>', date('Y-m-d H:i:s'))
            ->find();

        if (!$link->loaded()) {
            return FALSE;
        }

        $file = $link->file;
        if (!$file->loaded() || $file->deleted_at !== NULL) {
            return FALSE;
        }

        $link->click_count++;
        
        try {
            $link->save();
        } catch (ORM_Validation_Exception $e) {
            $link->save(NULL);
        }

        return $this->_prepare_file_data($file);
    }

    /**
     * Подготовка структуры данных для контроллера скачивания
     */
    protected function _prepare_file_data($file)
    {
        $backend = $file->backend;
        $config = $backend->get_config_array();
        $stream = null;

        if ($backend->driver === 'local') {
            $stream = $this->getFilePath($file->id);
        } 
        elseif ($backend->driver === 's3') {
            $s3 = $this->_get_s3_client($config);
            $result = $s3->getObject([
                'Bucket' => $config['bucket'],
                'Key'    => $file->storage_path
            ]);
            $stream = $result['Body']->detach();
        }

        return [
            'stream'    => $stream,
            'filename'  => $file->filename,
            'mime_type' => $file->mime_type,
            'size'      => $file->file_size
        ];
    }

    /**
     * Получение списка файлов
     */
    public function get_files_list(array $params = [])
    {
        $user_id = isset($params['user_id']) ? $params['user_id'] : null;
        $search = isset($params['search']) ? $params['search'] : null;
        $is_public = isset($params['is_public']) ? $params['is_public'] : null;
        $module = isset($params['module']) ? $params['module'] : null;
        $page = isset($params['page']) ? (int)$params['page'] : 1;
        $per_page = isset($params['per_page']) ? (int)$params['per_page'] : 20;

        $files = ORM::factory('Erp212_File')
            ->where('deleted_at', 'IS', NULL);

        if ($is_public !== null) {
            $files->where('is_public', '=', (int)$is_public);
        }

        if ($module !== null) {
            $files->where('module_prefix', '=', $module);
        }

        if ($user_id !== null && $is_public === null) {
            $files->and_where_open()
                ->where('user_id', '=', $user_id)
                ->or_where('is_public', '=', 1)
                ->and_where_close();
        } elseif ($user_id !== null && $is_public !== null) {
            $files->where('user_id', '=', $user_id);
        }

        if (!empty($search)) {
            $files->where('filename', 'LIKE', '%' . $search . '%');
        }

        $count_query = clone $files;
        $total_count = $count_query->count_all();

        $items = $files->order_by('created_at', 'DESC')
            ->limit($per_page)
            ->offset(($page - 1) * $per_page)
            ->find_all();

        return [
            'items' => $items,
            'total' => (int)$total_count,
            'pages' => ceil($total_count / $per_page),
            'current_page' => $page
        ];
    }

    /**
     * Получение общей статистики хранилища
     */
    public function get_storage_stats(array $params = [])
    {
        $user_id = isset($params['user_id']) ? $params['user_id'] : null;

        $query = DB::select(
            [DB::expr('COUNT(id)'), 'total_files'],
            [DB::expr('SUM(file_size)'), 'total_size']
        )->from('erp_212_files')
         ->where('deleted_at', 'IS', NULL);

        if ($user_id !== null) {
            $query->where('user_id', '=', $user_id);
        }

        $result = $query->execute()->current();
        $total_size = (float) ($result['total_size'] ?? 0);

        /**
         * PHP 8.4 Fix: Text::bytes() calls strpos() on the third parameter ($force_unit).
         * In Kohana_Text::bytes, if $force_unit is NULL, it causes Deprecated error in PHP 8.4.
         * We pass an empty string '' instead of NULL to satisfy strpos() requirements.
         * Note: The Kohana_Text::bytes signature is (bytes, force_unit, format, si).
         * In the error trace, it shows $force_unit is the second argument passed as NULL.
         */
        return [
            'total_files' => (int) ($result['total_files'] ?? 0),
            'total_size'  => $total_size,
            'formatted_size' => Text::bytes($total_size, '')
        ];
    }

    /**
     * Получение списка всех активных бэкендов
     */
    public function get_active_backends()
    {
        return ORM::factory('Erp212_Backend')
            ->where('is_active', '=', 1)
            ->find_all();
    }

    /**
     * Инициализация бэкенда
     */
    public function set_driver($driver_name)
    {
        $backend = ORM::factory('Erp212_Backend')
            ->where('driver', '=', $driver_name)
            ->where('is_active', '=', 1)
            ->find();

        if (!$backend->loaded())
        {
            throw new Exception('Backend with driver "'.$driver_name.'" not found or inactive');
        }

        $this->_backend = $backend;
        return $this;
    }

    /**
     * Получение текущего активного бэкенда
     */
    protected function _get_backend()
    {
        if ($this->_backend === NULL)
        {
            $backend = ORM::factory('Erp212_Backend')
                ->where('is_active', '=', 1)
                ->find();

            if (!$backend->loaded())
            {
                throw new Exception('No active storage backend configured');
            }

            $this->_backend = $backend;
        }

        return $this->_backend;
    }

    /**
     * Сохранение файла из сырых данных
     */
    public function saveRaw($content, $filename, array $metadata = [])
    {
        $tmp_file = tempnam(sys_get_temp_dir(), 'erp_storage_');
        file_put_contents($tmp_file, $content);
        
        try {
            $file_data = [
                'tmp_name' => $tmp_file,
                'name' => $filename,
                'size' => strlen($content),
                'type' => $this->_detect_mime($tmp_file)
            ];
            
            $file_id = $this->save($file_data, $metadata);
            @unlink($tmp_file);
            return $file_id;
        } catch (Exception $e) {
            @unlink($tmp_file);
            throw $e;
        }
    }

    /**
     * Сохранение файла с новой структурой путей
     */
    public function save($file, array $metadata = [])
    {
        $file_path = is_array($file) ? $file['tmp_name'] : $file;
        
        if (!file_exists($file_path)) {
            throw new Exception("Source file not found: " . $file_path);
        }

        $mime_type = is_array($file) ? $file['type'] : $this->_detect_mime($file_path);
        $file_size = is_array($file) ? $file['size'] : filesize($file_path);

        if (!$this->validate_mime($mime_type))
        {
            throw new Exception('MIME type "'.$mime_type.'" not allowed');
        }

        if ($file_size > $this->get_max_size())
        {
            throw new Exception('File size exceeds maximum allowed');
        }

        $backend_id = isset($metadata['backend_id']) ? (int)$metadata['backend_id'] : $this->_get_default_backend_id();
        $backend = ORM::factory('Erp212_Backend', $backend_id);
        
        if (!$backend->loaded() || !$backend->is_active)
        {
            throw new Exception('Backend ID '.$backend_id.' not found or inactive');
        }

        // 1. Создаем запись в БД для получения ID
        $file_model = ORM::factory('Erp212_File');
        $file_model->backend_id = $backend->id;
        $file_model->user_id = isset($metadata['user_id']) ? (int)$metadata['user_id'] : NULL;
        $file_model->filename = is_array($file) ? $file['name'] : basename($file);
        $file_model->mime_type = $mime_type;
        $file_model->file_size = $file_size;
        $file_model->extension = $this->_get_extension($file);
        $file_model->hash = hash_file('sha256', $file_path);
        $file_model->is_public = isset($metadata['is_public']) ? (int)$metadata['is_public'] : 0;
        $file_model->module_prefix = isset($metadata['module']) ? $metadata['module'] : 'default';
        
        // Временный путь для прохождения валидации not_empty в модели
        $file_model->storage_path = 'pending/' . $file_model->filename;

        try {
            $file_model->save();
        } catch (ORM_Validation_Exception $e) {
            // Если валидация не прошла, пробуем сохранить без валидации (Kohana ORM специфично)
            $file_model->save(NULL);
        }

        // 2. Генерируем путь на основе ID и префикса
        $folder_index = str_pad(floor($file_model->id / 1000), 3, '0', STR_PAD_LEFT);
        $storage_path = $file_model->module_prefix . '/' . $folder_index . '/' . $file_model->id . '_' . md5(uniqid().$file_model->id) . '.' . $file_model->extension;

        // 3. Физическое сохранение
        $driver_meta = array_merge($metadata, [
            'mime_type' => $mime_type,
            'is_public' => $file_model->is_public
        ]);

        try {
            $this->_physical_save($backend, $file_path, $storage_path, $driver_meta);
            
            // 4. Обновляем путь в БД
            $file_model->storage_path = $storage_path;
            try {
                $file_model->save();
            } catch (ORM_Validation_Exception $e) {
                $file_model->save(NULL);
            }
        } catch (Exception $e) {
            $file_model->delete();
            throw $e;
        }

        Event::fire('erp212_storage.uploaded', $this->get_extended_metadata($file_model->id));

        return $file_model->id;
    }

    /**
     * Удаление файла (с проверкой прав текущего пользователя).
     */
    public function delete($file_id)
    {
        $file = ORM::factory('Erp212_File', $file_id);
        if (!$file->loaded() || $file->deleted_at !== NULL) return FALSE;

        if (!$this->_check_permission($file, 'delete'))
        {
            throw new Exception('Access denied for file ID '.$file_id);
        }

        return $this->_delete($file);
    }

    /**
     * Удаление файла вне пользовательского контекста (регламентные задачи, cron).
     *
     * Отличие от delete(): права не проверяются, потому что в CLI пользователя нет.
     * Использовать только в задачах обслуживания (например, erp212:storage:cleanup).
     *
     * @param  int $file_id
     * @return bool
     */
    public function purge($file_id)
    {
        $file = ORM::factory('Erp212_File', $file_id);
        if (!$file->loaded() || $file->deleted_at !== NULL) return FALSE;

        return $this->_delete($file);
    }

    /**
     * Физическое и логическое удаление файла.
     *
     * @param  Model_Erp212_File $file
     * @return bool
     */
    protected function _delete($file)
    {
        $metadata = $this->get_extended_metadata($file->id);
        $backend = ORM::factory('Erp212_Backend', $file->backend_id);

        try {
            $this->_physical_delete($backend, $file->storage_path);
            $file->delete();

            Event::fire('erp212_storage.deleted', (object)$metadata);

            return TRUE;
        } catch (Exception $e) {
            Kohana::$log->add(Log::ERROR, 'Delete error: ' . $e->getMessage());
            return FALSE;
        }
    }

    /**
     * Генерация URL
     */
    public function generateUrl($file_id, $expires_seconds = 3600)
    {
        $file = ORM::factory('Erp212_File', $file_id);
        if (!$file->loaded() || $file->deleted_at !== NULL) throw new Exception('File not found');

        $backend = ORM::factory('Erp212_Backend', $file->backend_id);
        $config = $backend->get_config_array();

        if ($backend->driver === 's3') {
            return $this->_s3_generate_url($config, $file->storage_path, $expires_seconds);
        }

        $token = bin2hex(random_bytes(32));
        $link = ORM::factory('Erp212_Link');
        $link->file_id = $file_id;
        $link->token = $token;
        $link->expires_at = date('Y-m-d H:i:s', time() + $expires_seconds);
        
        try {
            $link->save();
        } catch (ORM_Validation_Exception $e) {
            $link->save(NULL);
        }

        return URL::site('erp212_download/shared/'.$token);
    }

    /**
     * Получение расширенных метаданных файла
     */
    public function get_extended_metadata($file_id)
    {
        $file = ORM::factory('Erp212_File', $file_id);
        
        if (!$file->loaded() || $file->deleted_at !== NULL) {
            return NULL;
        }

        $user_data = null;
        if ($file->user_id) {
            $user = ORM::factory('User', $file->user_id);
            if ($user->loaded()) {
                $user_data = [
                    'id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email
                ];
            }
        }

        $file_size = (float) ($file->file_size ?? 0);

        /**
         * PHP 8.4 Fix: Text::bytes() calls strpos() on the second parameter ($force_unit).
         * We pass an empty string '' instead of NULL to avoid Deprecated: strpos() error.
         */
        return [
            'id'            => (int)$file->id,
            'filename'      => $file->filename,
            'extension'     => $file->extension,
            'mime_type'     => $file->mime_type,
            'size'          => (int)$file_size,
            'size_human'    => Text::bytes($file_size, ''),
            'hash'          => $file->hash,
            'is_public'     => (bool)$file->is_public,
            'module'        => $file->module_prefix,
            'created_at'    => $file->created_at,
            'storage_path'  => $file->storage_path,
            'backend_id'    => (int)$file->backend_id,
            'user'          => $user_data,
            'url'           => $file->is_public ? $this->generateUrl($file->id) : null
        ];
    }

    protected function _physical_save($backend, $source, $path, $meta)
    {
        $config = $backend->get_config_array();
        
        if ($backend->driver === 'local') {
            $root = $this->_get_local_root($config);
            $full_path = $root . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
            $dir = dirname($full_path);
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            
            $success = is_uploaded_file($source) ? move_uploaded_file($source, $full_path) : copy($source, $full_path);

            if (!$success) {
                throw new Exception("Failed to copy file to $full_path");
            }

            @chmod($full_path, isset($config['chmod']) ? octdec($config['chmod']) : 0644);
        } 
        elseif ($backend->driver === 's3') {
            $s3 = $this->_get_s3_client($config);
            $s3->putObject([
                'Bucket'      => $config['bucket'],
                'Key'         => $path,
                'SourceFile'  => $source,
                'ContentType' => $meta['mime_type'] ?? 'application/octet-stream',
                'ACL'         => !empty($meta['is_public']) ? 'public-read' : 'private'
            ]);
        }
    }

    protected function _physical_delete($backend, $path)
    {
        $config = $backend->get_config_array();
        if ($backend->driver === 'local') {
            $root = $this->_get_local_root($config);
            $full_path = $root . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
            if (is_file($full_path)) unlink($full_path);
        } 
        elseif ($backend->driver === 's3') {
            $s3 = $this->_get_s3_client($config);
            $s3->deleteObject(['Bucket' => $config['bucket'], 'Key' => $path]);
        }
    }

    protected function _get_local_root($config)
    {
        if (isset($config['root_path'])) {
            $path = $config['root_path'];
        } else {
            $storage_config = Kohana::$config->load('erp212_storage');
            $local_paths = $storage_config->get('local_storage_paths');
            $path = $local_paths['default'];
        }

        if (!is_dir($path)) mkdir($path, 0755, true);
        return rtrim(realpath($path), DIRECTORY_SEPARATOR);
    }

    protected function _get_s3_client($config)
    {
        if (!class_exists('Aws\S3\S3Client')) throw new Exception('AWS SDK not found');
        $s3_config = [
            'version' => 'latest',
            'region'  => isset($config['region']) ? $config['region'] : 'us-east-1',
            'credentials' => ['key' => $config['key'], 'secret' => $config['secret']]
        ];
        if (!empty($config['endpoint'])) {
            $s3_config['endpoint'] = $config['endpoint'];
            $s3_config['use_path_style_endpoint'] = isset($config['use_path_style']) ? $config['use_path_style'] : false;
        }
        return new Aws\S3\S3Client($s3_config);
    }

    protected function _s3_generate_url($config, $path, $expires)
    {
        $s3 = $this->_get_s3_client($config);
        $cmd = $s3->getCommand('GetObject', ['Bucket' => $config['bucket'], 'Key' => $path]);
        return (string) $s3->createPresignedRequest($cmd, "+{$expires} seconds")->getUri();
    }

    public function validate_mime($mime_type)
    {
        $config = Kohana::$config->load('erp212_storage');
        $allowed = $config->get('allowed_mime_types');
        return in_array($mime_type, (array)$allowed);
    }

    public function get_max_size()
    {
        $config = Kohana::$config->load('erp212_storage');
        return (int) $config->get('upload_max_size') ?: 10485760;
    }

    protected function _check_permission($file, $action)
    {
        if (Kohana::$environment === Kohana::DEVELOPMENT) return TRUE;
        $user = Auth::instance()->get_user();
        if (!$user) return FALSE;
        if ($file->user_id == $user->id) return TRUE;
        if ($file->is_public && $action == 'read') return TRUE;
        return FALSE;
    }

    protected function _detect_mime($path)
    {
        if (!file_exists($path)) return 'application/octet-stream';
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $path);
        finfo_close($finfo);
        return $mime ?: 'application/octet-stream';
    }

    protected function _get_extension($file)
    {
        $name = is_array($file) ? $file['name'] : $file;
        return strtolower(pathinfo($name, PATHINFO_EXTENSION));
    }

    protected function _get_default_backend_id()
    {
        $backend = ORM::factory('Erp212_Backend')->where('is_active', '=', 1)->find();
        return $backend->id;
    }
}