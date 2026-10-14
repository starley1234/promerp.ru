<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * Модель для работы с метаданными файлов (таблица erp_212_files).
 * Отвечает за хранение информации о файлах: оригинальное имя, путь в хранилище,
 * MIME-тип, размер, хэш, флаги публичности и временные метки.
 * Поддерживает связи с бэкендами хранилищ, пользователями-владельцами,
 * временными ссылками и правами доступа (ACL).
 * 
 * @property int $id ID файла (первичный ключ)
 * @property int $backend_id ID бэкенда хранилища (внешний ключ на erp_212_storage_backends.id)
 * @property int $user_id ID пользователя-владельца (внешний ключ на users.id, может быть NULL)
 * @property string $module_prefix Префикс модуля (например, "crm", "billing")
 * @property string $filename Оригинальное имя файла (например, "document.pdf")
 * @property string $storage_path Путь к файлу в хранилище (ключ в S3 или путь на диске)
 * @property string $mime_type MIME-тип файла (например, "application/pdf")
 * @property int $file_size Размер файла в байтах
 * @property string $extension Расширение файла (например, "pdf")
 * @property string $hash Хэш-сумма файла (SHA256/MD5) для проверки целостности, может быть NULL
 * @property int $is_public Флаг публичности (1 - доступен по прямой ссылке, 0 - только через ACL)
 * @property string $created_at Дата и время создания записи
 * @property string $updated_at Дата и время последнего обновления (может быть NULL)
 * @property string $deleted_at Дата и время мягкого удаления (может быть NULL)
 * 
 * @property Model_Erp212_Backend $backend Связанный бэкенд хранилища (модель erp_212_storage_backends)
 * @property Model_User $user Владелец файла (модель users)
 * @property Model_Erp212_Link[] $links Временные ссылки на файл (массив моделей erp_212_file_links)
 * @property Model_Erp212_Permission[] $permissions Права доступа к файлу (массив моделей erp_212_file_permissions)
 */
class Model_Erp212_File extends ORM {

    /**
     * Имя таблицы в базе данных с префиксом erp_212_
     * @var string
     */
    protected $_table_name = 'erp_212_files';

    /**
     * Включение мягкого удаления (soft delete)
     * При удалении записи устанавливается значение в поле deleted_at
     * @var bool
     */
    protected $_soft_delete = TRUE;

    /**
     * Имя колонки с датой мягкого удаления
     * @var string
     */
    protected $_deleted_column = 'deleted_at';

    /**
     * Автоматическое обновление поля created_at при создании записи
     * @var array
     */
    protected $_created_column = array('column' => 'created_at', 'format' => 'Y-m-d H:i:s');

    /**
     * Автоматическое обновление поля updated_at при обновлении записи
     * @var array
     */
    protected $_updated_column = array('column' => 'updated_at', 'format' => 'Y-m-d H:i:s');

    /**
     * Связь "принадлежит" (belongs_to) с бэкендом хранилища
     * Каждый файл хранится в одном бэкенде (local/s3/ftp)
     * @var array
     */
    protected $_belongs_to = array(
        'backend' => array(
            'model' => 'Erp212_Backend',
            'foreign_key' => 'backend_id',
        ),
        'user' => array(
            'model' => 'User',
            'foreign_key' => 'user_id',
        )
    );

    /**
     * Связь "имеет много" (has_many) с временными ссылками
     * Один файл может иметь несколько временных ссылок (токенов доступа)
     * @var array
     */
    protected $_has_many = array(
        'links' => array(
            'model' => 'Erp212_Link',
            'foreign_key' => 'file_id',
        ),
        'permissions' => array(
            'model' => 'Erp212_Permission',
            'foreign_key' => 'file_id',
        )
    );

    /**
     * Правила валидации для полей модели
     * @return array
     */
    public function rules()
    {
        return array(
            'backend_id' => array(
                array('not_empty'),
                array('digit'),
            ),
            'filename' => array(
                array('not_empty'),
                array('max_length', array(':value', 255))
            ),
            'storage_path' => array(
                array('not_empty')
            ),
            'mime_type' => array(
                array('not_empty'),
                array('max_length', array(':value', 100))
            ),
            'file_size' => array(
                array('not_empty'),
                array('digit'),
            ),
            'extension' => array(
                array('not_empty'),
                array('max_length', array(':value', 10))
            ),
            'hash' => array(
                array('max_length', array(':value', 64))
            ),
            'is_public' => array(
                array('digit'),
            ),
            'module_prefix' => array(
                array('max_length', array(':value', 50))
            )
        );
    }

    /**
     * Метки полей для форм
     * @return array
     */
    public function labels()
    {
        return array(
            'id' => 'ID',
            'backend_id' => 'Бэкенд хранилища',
            'user_id' => 'Владелец',
            'module_prefix' => 'Префикс модуля',
            'filename' => 'Имя файла',
            'storage_path' => 'Путь в хранилище',
            'mime_type' => 'MIME-тип',
            'file_size' => 'Размер (байт)',
            'extension' => 'Расширение',
            'hash' => 'Хэш-сумма',
            'is_public' => 'Публичный',
            'created_at' => 'Создан',
            'updated_at' => 'Обновлен',
            'deleted_at' => 'Удален'
        );
    }

    /**
     * Фильтры для полей перед сохранением
     * @return array
     */
    public function filters()
    {
        return array(
            'filename' => array(
                array('trim'),
                array('strip_tags')
            ),
            'storage_path' => array(
                array('trim')
            ),
            'mime_type' => array(
                array('trim')
            ),
            'extension' => array(
                array('trim'),
                array('strtolower')
            ),
            'hash' => array(
                array('trim')
            ),
            'module_prefix' => array(
                array('trim'),
                array('strtolower')
            )
        );
    }

    /**
     * Переопределение метода before_save для дополнительной логики
     * @return void
     */
    protected function before_save()
    {
        // Автоматическое вычисление расширения из имени файла, если не задано
        if (empty($this->extension) && !empty($this->filename))
        {
            $this->extension = strtolower(pathinfo($this->filename, PATHINFO_EXTENSION));
        }

        // Установка префикса по умолчанию
        if (empty($this->module_prefix))
        {
            $this->module_prefix = 'default';
        }

        parent::before_save();
    }

    /**
     * Проверка, имеет ли пользователь право на чтение файла
     * @param int $user_id ID пользователя
     * @return bool
     */
    public function can_read($user_id)
    {
        if ($this->is_public)
        {
            return TRUE;
        }

        if ($this->user_id == $user_id)
        {
            return TRUE;
        }

        $permission = ORM::factory('Erp212_Permission')
            ->where('file_id', '=', $this->id)
            ->where('user_id', '=', $user_id)
            ->where('can_read', '=', 1)
            ->find();

        return (bool) $permission->id;
    }

    /**
     * Проверка, имеет ли пользователь право на запись (изменение/удаление)
     * @param int $user_id ID пользователя
     * @return bool
     */
    public function can_write($user_id)
    {
        if ($this->user_id == $user_id)
        {
            return TRUE;
        }

        $permission = ORM::factory('Erp212_Permission')
            ->where('file_id', '=', $this->id)
            ->where('user_id', '=', $user_id)
            ->where('can_write', '=', 1)
            ->find();

        return (bool) $permission->id;
    }

    /**
     * Проверка, имеет ли пользователь право на удаление файла
     * @param int $user_id ID пользователя
     * @return bool
     */
    public function can_delete($user_id)
    {
        if ($this->user_id == $user_id)
        {
            return TRUE;
        }

        $permission = ORM::factory('Erp212_Permission')
            ->where('file_id', '=', $this->id)
            ->where('user_id', '=', $user_id)
            ->where('can_delete', '=', 1)
            ->find();

        return (bool) $permission->id;
    }

    /**
     * Получение URL для скачивания файла (с проверкой прав)
     * @param int $user_id ID пользователя (если NULL - проверка только на публичность)
     * @return string|bool URL для скачивания или FALSE если доступ запрещен
     */
    public function get_download_url($user_id = NULL)
    {
        if ($user_id !== NULL && !$this->can_read($user_id))
        {
            return FALSE;
        }

        if ($user_id === NULL && !$this->is_public)
        {
            return FALSE;
        }

        return URL::site('erp212_download/file/' . $this->id);
    }

    /**
     * Генерация временной ссылки (presigned URL) на файл
     * @param int $expires_in Срок действия в секундах (по умолчанию 3600 = 1 час)
     * @return string|bool Токен временной ссылки или FALSE при ошибке
     */
    public function generate_temp_link($expires_in = 3600)
    {
        $token = Text::random('alnum', 64);

        $link = ORM::factory('Erp212_Link');
        $link->file_id = $this->id;
        $link->token = $token;
        $link->expires_at = date('Y-m-d H:i:s', time() + $expires_in);
        
        try {
            $link->save();
        } catch (ReflectionException $e) {
            $link->save(NULL);
        }

        if (!$link->id)
        {
            return FALSE;
        }

        return URL::site('erp212_download/shared/' . $token);
    }

    /**
     * Получение статистики по использованию временных ссылок
     * @return array Статистика
     */
    public function get_links_stats()
    {
        $now = date('Y-m-d H:i:s');
        
        $total = ORM::factory('Erp212_Link')
            ->where('file_id', '=', $this->id)
            ->count_all();

        $active = ORM::factory('Erp212_Link')
            ->where('file_id', '=', $this->id)
            ->where('expires_at', '>', $now)
            ->count_all();

        $expired = ORM::factory('Erp212_Link')
            ->where('file_id', '=', $this->id)
            ->where('expires_at', '<=', $now)
            ->count_all();

        $total_clicks = DB::select(array(DB::expr('SUM(click_count)'), 'total'))
            ->from('erp_212_file_links')
            ->where('file_id', '=', $this->id)
            ->execute()
            ->get('total');

        return array(
            'total_links' => (int) $total,
            'active_links' => (int) $active,
            'expired_links' => (int) $expired,
            'total_clicks' => (int) $total_clicks
        );
    }

    /**
     * Очистка просроченных временных ссылок для этого файла
     * @return int Количество удаленных ссылок
     */
    public function cleanup_expired_links()
    {
        $now = date('Y-m-d H:i:s');
        
        $links = ORM::factory('Erp212_Link')
            ->where('file_id', '=', $this->id)
            ->where('expires_at', '<=', $now)
            ->find_all();

        $deleted = 0;
        foreach ($links as $link)
        {
            $link->delete();
            $deleted++;
        }

        return $deleted;
    }
}