<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * Модель пользователя системы ERP100.
 * Расширяет стандартную модель Auth_User для поддержки профилей и специфичных настроек.
 */
class Model_Erp100_User extends Model_Auth_User {

    /**
     * ВРУЧНУЮ прописываем колонки.
     * Теперь ORM вообще не будет опрашивать базу о структуре.
     */
    protected $_table_columns = [
        'id'                    => ['type' => 'int'],
        'email'                 => ['type' => 'string'],
        'username'              => ['type' => 'string'],
        'password'              => ['type' => 'string'],
        'logins'                => ['type' => 'int'],
        'last_login'            => ['type' => 'int'],
        'last_ip'               => ['type' => 'string'],
        'email_confirmed'       => ['type' => 'int'],
        'confirmation_token'    => ['type' => 'string'],
        'failed_login_attempts' => ['type' => 'int'],
        'last_failed_login'     => ['type' => 'int'],
        'reset_token'           => ['type' => 'string'],
        'reset_expires'         => ['type' => 'int'],
        'created_at'            => ['type' => 'string'],
        'updated_at'            => ['type' => 'string'],
        'status'                => ['type' => 'string'],
        'blocked_at'            => ['type' => 'string'],
        'blocked_reason'        => ['type' => 'string'],
        'session_version'       => ['type' => 'int'],
        'access_policy'         => ['type' => 'string'],
    ];

    /**
     * @var array Связи типа "один ко многим"
     */
    protected $_has_many = array(
        'user_tokens' => array('model' => 'User_Token'),
        'roles'       => array('model' => 'Role', 'through' => 'roles_users'),
        'oauth'       => array('model' => 'UserOauth', 'foreign_key' => 'user_id'),
    );

    /**
     * @var array Связи типа "один к одному"
     */
    protected $_has_one = array(
        'profile'     => array('model' => 'Erp100_User_Profile', 'foreign_key' => 'user_id'),
        'setting'     => array('model' => 'Erp100_User_Setting', 'foreign_key' => 'user_id'),
    );
    
    /**
     * @var array|null Статический кеш имен пользователей для оптимизации повторных вызовов
     */
    protected static $_names_cache = null;

    /**
     * Получает список всех имен и должностей пользователей из профилей.
     * Использует статический кеш для предотвращения повторных запросов к БД.
     *
     * @return array Массив данных профилей, индексированный по user_id
     */
    public static function get_all_names() {
        if (self::$_names_cache === null) {
            self::$_names_cache = DB::select('user_id', 'full_name', 'position', 'first_name', 'last_name')
                ->from('user_profiles')
                ->execute()
                ->as_array('user_id');
        }
        return self::$_names_cache;
    }

    /**
     * Получает массив данных профилей пользователей, обладающих определенной ролью.
     *
     * @param string $role_name Название роли (например, 'admin', 'manager')
     * @return array Массив данных профилей, индексированный по user_id
     */
    public static function get_profiles_by_role($role_name) {
        return DB::select(
                array('up.user_id', 'user_id'), 
                'up.full_name', 
                'up.position', 
                'up.first_name', 
                'up.last_name'
            )
            ->from(array('user_profiles', 'up'))
            ->join(array('roles_users', 'ru'), 'INNER')->on('up.user_id', '=', 'ru.user_id')
            ->join(array('roles', 'r'), 'INNER')->on('ru.role_id', '=', 'r.id')
            ->where('r.name', '=', $role_name)
            ->execute()
            ->as_array('user_id');
    }

    /**
     * Получает названия ролей пользователя.
     * Оптимизировано с использованием as_array()
     *
     * @return array Массив названий ролей.
     */
    public function get_role_names()
    {
        if (!$this->loaded())
        {
            return [];
        }

        return $this->roles
            ->find_all()
            ->as_array(NULL, 'name');
    }

    /**
     * Переопределение метода создания пользователя.
     * Автоматически создает связанную запись в таблице профилей после успешного создания пользователя.
     * 
     * @param Validation|null $validation Объект валидации
     * @return $this
     */
    public function create(?Validation $validation = NULL)
    {
        $result = parent::create($validation);

        if ($result->loaded())
        {
            // Создаем пустой профиль для нового пользователя
            ORM::factory('Erp100_User_Profile')
                ->set('user_id', $result->id)
                ->create();
        }

        return $result;
    }

}